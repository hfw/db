<?php

namespace Helix;

use ArrayAccess;
use Closure;
use Helix\DB\EntityInterface;
use Helix\DB\Junction;
use Helix\DB\Record;
use Helix\DB\Select;
use Helix\DB\SQL\ExpressionInterface;
use Helix\DB\SQL\Predicate;
use Helix\DB\Statement;
use Helix\DB\Table;
use LogicException;
use PDO;

/**
 * Extends `PDO` and acts as a central access point for the schema.
 */
class DB extends PDO implements ArrayAccess {

    /**
     * @var string
     */
    private $driver;

    /**
     * @var Junction[]
     */
    protected $junctions = [];

    /**
     * Notified whenever a query is executed or a statement is prepared.
     * This is a stub closure by default.
     *
     * `fn($sql):void`
     *
     * @var Closure
     */
    protected $logger;

    /**
     * @var Record[]
     */
    protected $records = [];

    /**
     * @var Table[]
     */
    protected $tables = [];

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
    public function __construct ($dsn, $username = null, $password = null, array $options = []) {
        $options += [
            self::ATTR_STATEMENT_CLASS => [Statement::class, [$this]]
        ];
        parent::__construct($dsn, $username, $password, $options);
        $this->setAttribute(self::ATTR_DEFAULT_FETCH_MODE, self::FETCH_ASSOC);
        $this->setAttribute(self::ATTR_EMULATE_PREPARES, false);
        $this->setAttribute(self::ATTR_ERRMODE, self::ERRMODE_EXCEPTION);
        $this->setAttribute(self::ATTR_STRINGIFY_FETCHES, false);
        $this->logger ??= fn() => null;
        $this->driver = $this->getAttribute(self::ATTR_DRIVER_NAME);
        if ($this->isSQLite()) {
            $this->sqliteCreateFunction('CEIL', 'ceil');
            $this->sqliteCreateFunction('FLOOR', 'floor');
            $this->sqliteCreateFunction('POW', 'pow');
        }
    }

    /**
     * Returns the driver.
     *
     * @return string
     */
    final public function __toString () {
        return $this->driver;
    }

    /**
     * Notifies the logger.
     *
     * @param string $sql
     * @return int
     */
    public function exec ($sql): int {
        $this->logger->__invoke($sql);
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
    public function factory (string $class, ...$args) {
        return new $class($this, ...$args);
    }

    /**
     * @return string
     */
    final public function getDriver (): string {
        return $this->driver;
    }

    /**
     * Returns a {@link Junction} access object based on an annotated interface.
     *
     * @param string $interface
     * @return Junction
     */
    public function getJunction ($interface) {
        return $this->junctions[$interface] ??= Junction::fromInterface($this, $interface);
    }

    /**
     * @return Closure
     */
    public function getLogger () {
        return $this->logger;
    }

    /**
     * Returns a {@link Record} access object based on an annotated class.
     *
     * @param string|EntityInterface $class
     * @return Record
     */
    public function getRecord ($class) {
        if (is_object($class)) {
            $class = get_class($class);
        }
        return $this->records[$class] ??= Record::fromClass($this, $class);
    }

    /**
     * @param string $name
     * @return null|Table
     */
    public function getTable (string $name) {
        if (!isset($this->tables[$name])) {
            if ($this->isSQLite()) {
                $info = $this->query("PRAGMA table_info({$this->quote($name)})")->fetchAll();
                $cols = array_column($info, 'name');
            }
            else {
                $cols = $this->query(
                    "SELECT column_name FROM information_schema.tables WHERE table_name = {$this->quote($name)}"
                )->fetchAll(self::FETCH_COLUMN);
            }
            if (!$cols) {
                return null;
            }
            $this->tables[$name] = Table::factory($this, $name, $cols);
        }
        return $this->tables[$name];
    }

    /**
     * @return bool
     */
    final public function isMySQL (): bool {
        return $this->driver === 'mysql';
    }

    /**
     * @return bool
     */
    final public function isPostgreSQL (): bool {
        return $this->driver === 'pgsql';
    }

    /**
     * @return bool
     */
    final public function isSQLite (): bool {
        return $this->driver === 'sqlite';
    }

    /**
     * Generates an equality {@link Predicate} from mixed arguments.
     *
     * If `$b` is a closure, returns from `$b($a, DB $this)`
     *
     * If `$a` is an integer (enumerated item), returns `$b` as a {@link Predicate}
     *
     * If `$b` is an array, returns `$a IN (...quoted $b)`
     *
     * If `$b` is a {@link Select}, returns `$a IN ($b->toSql())`
     *
     * Otherwise predicates `$a = quoted $b`
     *
     * @param mixed $a
     * @param mixed $b
     * @return Predicate
     */
    public function match ($a, $b) {
        if ($b instanceof Closure) {
            return $b->__invoke($a, $this);
        }
        if (is_int($a)) {
            return Predicate::factory($this, $b);
        }
        if (is_array($b)) {
            return Predicate::factory($this, "{$a} IN ({$this->quoteList($b)})");
        }
        if ($b instanceof Select) {
            return Predicate::factory($this, "{$a} IN ({$b->toSql()})");
        }
        return Predicate::factory($this, "{$a} = {$this->quote($b)}");
    }

    /**
     * Whether a table exists.
     *
     * @param string $table
     * @return bool
     */
    final public function offsetExists ($table): bool {
        return (bool)$this->getTable($table);
    }

    /**
     * Returns a table by name.
     *
     * @param string $table
     * @return null|Table
     */
    final public function offsetGet ($table) {
        return $this->getTable($table);
    }

    /**
     * @param $offset
     * @param $value
     * @throws LogicException
     */
    final public function offsetSet ($offset, $value) {
        throw new LogicException('Raw table access is immutable.');
    }

    /**
     * @param $offset
     * @throws LogicException
     */
    final public function offsetUnset ($offset) {
        throw new LogicException('Raw table access is immutable.');
    }

    /**
     * Notifies the logger.
     *
     * @param string $sql
     * @param array $options
     * @return Statement
     */
    public function prepare ($sql, $options = []) {
        $this->logger->__invoke($sql);
        /** @var Statement $statement */
        $statement = parent::prepare($sql, $options);
        return $statement;
    }

    /**
     * Notifies the logger and executes.
     *
     * @param string $sql
     * @param int $mode
     * @param mixed $arg3 Optional.
     * @param array $ctorargs Optional.
     * @return Statement
     */
    public function query ($sql, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = []) {
        $this->logger->__invoke($sql);
        /** @var Statement $statement */
        $statement = parent::query(...func_get_args());
        return $statement;
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
    public function quote ($value, $type = self::PARAM_STR) {
        if ($value instanceof ExpressionInterface) {
            return $value;
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
    public function quoteArray (array $values) {
        return array_map([$this, 'quote'], $values);
    }

    /**
     * Returns a quoted, comma-separated list.
     *
     * @param array $values
     * @return string
     */
    public function quoteList (array $values): string {
        return implode(',', $this->quoteArray($values));
    }

    /**
     * Forwards to the entity's {@link Record}
     *
     * @param EntityInterface $entity
     * @return int ID
     */
    public function save (EntityInterface $entity): int {
        return $this->getRecord($entity)->save($entity);
    }

    /**
     * @param string $interface
     * @param Junction $junction
     * @return $this
     */
    public function setJunction (string $interface, Junction $junction) {
        $this->junctions[$interface] = $junction;
        return $this;
    }

    /**
     * @param Closure $logger
     * @return $this
     */
    public function setLogger (Closure $logger) {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @param string $class
     * @param Record $record
     * @return $this
     */
    public function setRecord (string $class, Record $record) {
        $this->records[$class] = $record;
        return $this;
    }
}