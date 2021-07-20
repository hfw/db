<?php

namespace Helix;

use ArrayAccess;
use Helix\DB\EntityInterface;
use Helix\DB\Fluent\ExpressionInterface;
use Helix\DB\Junction;
use Helix\DB\Migrator;
use Helix\DB\Record;
use Helix\DB\Schema;
use Helix\DB\Statement;
use Helix\DB\Table;
use Helix\DB\Transaction;
use LogicException;
use PDO;
use ReflectionFunction;

/**
 * Extends `PDO` and acts as a central access point for the schema.
 */
class DB extends PDO implements ArrayAccess
{

    /**
     * @var Junction[]
     */
    protected $junctions = [];

    /**
     * Notified whenever a query is executed or a statement is prepared.
     * This is a stub-closure by default.
     *
     * `fn($sql): void`
     *
     * @var callable
     */
    protected $logger;

    /**
     * The migrations directory for this connection.
     *
     * This can be set via class override, or configuration file (recommended).
     *
     * When using {@link DB::fromConfig()}, this defaults to `migrations/<CONNECTION NAME>`
     *
     * @var string
     */
    protected $migrations = 'migrations/default';

    /**
     * @var Record[]
     */
    protected $records = [];

    /**
     * @var Schema
     */
    protected $schema;

    /**
     * The count of open transactions/savepoints.
     *
     * @var int
     */
    protected $transactions = 0;

    /**
     * Returns a new connection using a configuration file.
     *
     * See `db.config.php` in the `test` directory for an example.
     *
     * @param string $connection
     * @param string $file
     * @return static
     */
    public static function fromConfig(string $connection = 'default', string $file = 'db.config.php')
    {
        $config = (include "{$file}")[$connection];
        $args = [
            $config['dsn'],
            $config['username'] ?? null,
            $config['password'] ?? null,
            $config['options'] ?? []
        ];
        $class = $config['class'] ?? static::class;
        $db = new $class(...$args);
        $db->logger = $config['logger'] ?? $db->logger;
        $db->migrations = $config['migrations'] ?? "migrations/{$connection}";
        return $db;
    }

    /**
     * Returns an array of `?` placeholder expressions.
     *
     * @param int $count
     * @return ExpressionInterface[]
     */
    public static function marks(int $count)
    {
        static $mark;
        $mark ??= new class implements ExpressionInterface {

            public function __toString()
            {
                return '?';
            }
        };
        return array_fill(0, $count, $mark);
    }

    /**
     * Converts an array of columns to `:named` placeholders for prepared queries.
     *
     * Qualified columns are slotted as `qualifier__column` (two underscores).
     *
     * @param string[] $columns
     * @return string[] `["column" => ":column"]`
     */
    public static function slots(array $columns)
    {
        $slots = [];
        foreach ($columns as $column) {
            $slots[$column] = ':' . str_replace('.', '__', $column);
        }
        return $slots;
    }

    /**
     * Sets various attributes to streamline operations.
     *
     * Registers missing SQLite functions.
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array $options
     */
    public function __construct($dsn, $username = null, $password = null, array $options = [])
    {
        $options[self::ATTR_STATEMENT_CLASS] ??= [Statement::class, [$this]];
        parent::__construct($dsn, $username, $password, $options);
        $this->logger ??= fn() => null;
        $this->schema ??= Schema::factory($this);

        // these options must not be changed.
        $this->setAttribute(self::ATTR_DEFAULT_FETCH_MODE, self::FETCH_ASSOC);
        $this->setAttribute(self::ATTR_EMULATE_PREPARES, false);
        $this->setAttribute(self::ATTR_ERRMODE, self::ERRMODE_EXCEPTION);
        $this->setAttribute(self::ATTR_STRINGIFY_FETCHES, false);

        // invoke driver-specific hooks
        $this->{"__construct_{$this->getDriver()}"}();
    }

    /**
     * MySQL construction hook.
     */
    protected function __construct_mysql(): void
    {
        $this->exec("SET time_zone = 'UTC'");
    }

    /**
     * SQLite construction hook.
     */
    protected function __construct_sqlite(): void
    {
        // polyfill deterministic sqlite functions
        $this->sqliteCreateFunctions([
            // https://www.sqlite.org/lang_mathfunc.html
            'ACOS' => 'acos',
            'ASIN' => 'asin',
            'ATAN' => 'atan',
            'CEIL' => 'ceil',
            'COS' => 'cos',
            'DEGREES' => 'rad2deg',
            'EXP' => 'exp',
            'FLOOR' => 'floor',
            'LN' => 'log',
            'LOG' => fn($b, $x) => log($x, $b),
            'LOG10' => 'log10',
            'LOG2' => fn($x) => log($x, 2),
            'PI' => 'pi',
            'POW' => 'pow',
            'RADIANS' => 'deg2rad',
            'SIN' => 'sin',
            'SQRT' => 'sqrt',
            'TAN' => 'tan',

            // these are not in sqlite at all but are in other dbms
            'CONV' => 'base_convert',
            'SIGN' => fn($x) => $x <=> 0
        ]);

        // non-deterministic
        $this->sqliteCreateFunctions([
            'RAND' => fn() => mt_rand() / mt_getrandmax(),
        ], false);
    }

    /**
     * Allows nested transactions by using `SAVEPOINT`
     *
     * Use {@link DB::newTransaction()} to work with {@link Transaction} instead.
     *
     * @return true
     */
    public function beginTransaction()
    {
        assert($this->transactions >= 0);
        if ($this->transactions === 0) {
            $this->log("BEGIN TRANSACTION");
            parent::beginTransaction();
        } else {
            $this->exec("SAVEPOINT SAVEPOINT_{$this->transactions}");
        }
        $this->transactions++;
        return true;
    }

    /**
     * Allows nested transactions by using `RELEASE SAVEPOINT`
     *
     * Use {@link DB::newTransaction()} to work with {@link Transaction} instead.
     *
     * @return true
     */
    public function commit()
    {
        assert($this->transactions > 0);
        if ($this->transactions === 1) {
            $this->log("COMMIT TRANSACTION");
            parent::commit();
        } else {
            $savepoint = $this->transactions - 1;
            $this->exec("RELEASE SAVEPOINT SAVEPOINT_{$savepoint}");
        }
        $this->transactions--;
        return true;
    }

    /**
     * Notifies the logger and executes.
     *
     * @param string $sql
     * @return int
     */
    public function exec($sql): int
    {
        $this->log($sql);
        return parent::exec($sql);
    }

    /**
     * Central point of object creation.
     *
     * Override this to override classes.
     *
     * The only thing that calls this should be {@link \Helix\DB\FactoryTrait}
     *
     * @param string $class
     * @param mixed ...$args
     * @return mixed
     */
    public function factory(string $class, ...$args)
    {
        return new $class($this, ...$args);
    }

    /**
     * The driver's name.
     *
     * - mysql
     * - pgsql
     * - sqlite
     *
     * @return string
     */
    final public function getDriver(): string
    {
        return $this->getAttribute(self::ATTR_DRIVER_NAME);
    }

    /**
     * Returns a {@link Junction} access object based on an annotated interface.
     *
     * @param string $interface
     * @return Junction
     */
    public function getJunction(string $interface)
    {
        return $this->junctions[$interface] ??= Junction::factory($this, $interface);
    }

    /**
     * @return callable
     */
    final public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param string $dir
     * @return Migrator
     */
    public function getMigrator()
    {
        return Migrator::factory($this, $this->migrations);
    }

    /**
     * Returns a {@link Record} access object based on an annotated class.
     *
     * @param string|EntityInterface $class
     * @return Record
     */
    public function getRecord($class)
    {
        $name = is_object($class) ? get_class($class) : $class;
        return $this->records[$name] ??= Record::factory($this, $class);
    }

    /**
     * @return Schema
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * @return bool
     */
    final public function isMySQL(): bool
    {
        return $this->getDriver() === 'mysql';
    }

    /**
     * @return bool
     */
    final public function isPostgreSQL(): bool
    {
        return $this->getDriver() === 'pgsql';
    }

    /**
     * @return bool
     */
    final public function isSQLite(): bool
    {
        return $this->getDriver() === 'sqlite';
    }

    /**
     * @param string $sql
     */
    public function log(string $sql): void
    {
        call_user_func($this->logger, $sql);
    }

    /**
     * Returns a scoped transaction.
     *
     * @return Transaction
     */
    public function newTransaction()
    {
        return Transaction::factory($this);
    }

    /**
     * Whether a table exists.
     *
     * @param string $table
     * @return bool
     */
    final public function offsetExists($table): bool
    {
        return (bool)$this->offsetGet($table);
    }

    /**
     * Returns a table by name.
     *
     * @param string $table
     * @return null|Table
     */
    public function offsetGet($table)
    {
        return $this->schema->getTable($table);
    }

    /**
     * Throws.
     *
     * @param $offset
     * @param $value
     * @throws LogicException
     */
    final public function offsetSet($offset, $value)
    {
        throw new LogicException('The schema cannot be altered this way.');
    }

    /**
     * Throws.
     *
     * @param $offset
     * @throws LogicException
     */
    final public function offsetUnset($offset)
    {
        throw new LogicException('The schema cannot be altered this way.');
    }

    /**
     * Notifies the logger and prepares a statement.
     *
     * @param string $sql
     * @param array $options
     * @return Statement
     */
    public function prepare($sql, $options = [])
    {
        $this->log($sql);
        return parent::{'prepare'}($sql, $options);
    }

    /**
     * Notifies the logger and queries.
     *
     * @param string $sql
     * @param int $mode
     * @param mixed $arg3
     * @param array $ctorargs
     * @return Statement
     */
    public function query($sql, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = [])
    {
        $this->log($sql);
        return parent::{'query'}(...func_get_args());
    }

    /**
     * Quotes a value, with special considerations.
     *
     * - {@link ExpressionInterface} instances are returned as-is.
     * - Booleans and integers are returned as unquoted integer-string.
     * - Everything else is returned as a quoted string.
     *
     * @param bool|number|string|object $value
     * @param int $type Ignored.
     * @return string|ExpressionInterface
     */
    public function quote($value, $type = self::PARAM_STR)
    {
        if ($value instanceof ExpressionInterface) {
            return $value;
        }
        if ($value instanceof EntityInterface) {
            return (string)$value->getId();
        }
        switch (gettype($value)) {
            case 'integer' :
            case 'boolean' :
            case 'resource' :
                return (string)(int)$value;
            default:
                return parent::quote((string)$value);
        }
    }

    /**
     * Quotes an array of values. Keys are preserved.
     *
     * @param array $values
     * @return string[]
     */
    public function quoteArray(array $values)
    {
        return array_map([$this, 'quote'], $values);
    }

    /**
     * Returns a quoted, comma-separated list.
     *
     * @param array $values
     * @return string
     */
    public function quoteList(array $values): string
    {
        return implode(',', $this->quoteArray($values));
    }

    /**
     * Allows nested transactions by using `ROLLBACK TO SAVEPOINT`
     *
     * Use {@link DB::newTransaction()} to work with {@link Transaction} instead.
     *
     * @return true
     */
    public function rollBack()
    {
        assert($this->transactions > 0);
        if ($this->transactions === 1) {
            $this->log("ROLLBACK TRANSACTION");
            parent::rollBack();
        } else {
            $savepoint = $this->transactions - 1;
            $this->exec("ROLLBACK TO SAVEPOINT SAVEPOINT_{$savepoint}");
        }
        $this->transactions--;
        return true;
    }

    /**
     * @param callable $logger
     * @return $this
     */
    public function setLogger(callable $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Installs a {@link Record} for a third-party class that cannot be annotated.
     *
     * @param string $class
     * @param Record $record
     * @return $this
     */
    public function setRecord(string $class, Record $record)
    {
        $this->records[$class] = $record;
        return $this;
    }

    /**
     * Create multiple SQLite functions at a time.
     *
     * @param callable[] $callables Keyed by function name.
     * @param bool $deterministic Whether the callables aren't random / are without side-effects.
     */
    public function sqliteCreateFunctions(array $callables, bool $deterministic = true): void
    {
        $deterministic = $deterministic ? self::SQLITE_DETERMINISTIC : 0;
        foreach ($callables as $name => $callable) {
            $argc = (new ReflectionFunction($callable))->getNumberOfRequiredParameters();
            $this->sqliteCreateFunction($name, $callable, $argc, $deterministic);
        }
    }

    /**
     * Performs work within a scoped transaction.
     *
     * The work is rolled back if an exception is thrown.
     *
     * @param callable $work `fn(): mixed`
     * @return mixed The return value of `$work`
     */
    public function transact(callable $work)
    {
        $transaction = $this->newTransaction();
        $return = call_user_func($work);
        $transaction->commit();
        return $return;
    }
}
