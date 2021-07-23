<?php

namespace Helix\DB;

use ArrayAccess;
use Helix\DB;
use LogicException;

/**
 * Schema control and metadata.
 *
 * The column definition constants are two bytes each, used in bitwise composition.
 * - The high-byte (`<I_CONST>`) is used for the specific primary index type.
 * - The low-byte (`<T_CONST>`) is used for the specific storage type.
 *      - The final bit `0x01` flags `NOT NULL`
 * - The literal values may change in the future, do not hard code them.
 * - The values may expand to use a total of 4 or 8 bytes to accommodate more stuff.
 *
 * Definition constants are never returned by this class' methods. The methods can only receive them.
 *
 * @method static static factory(DB $db)
 */
class Schema implements ArrayAccess
{

    use FactoryTrait;

    /**
     * Higher byte mask (column index type).
     */
    protected const I_MASK = 0xff00;

    /**
     * Partial definition for `T_AUTOINCREMENT`, use that instead.
     */
    protected const I_AUTOINCREMENT = self::I_PRIMARY | 0x0100; // 0xff00

    /**
     * `<I_CONST>`: One or more columns compose the primary key.
     */
    const I_PRIMARY = 0xfe00;

    /**
     * Lower-byte mask (column storage type).
     */
    protected const T_MASK = 0xff;

    /**
     * `<T_CONST>`: Column is the primary key and auto-increments (8-byte signed integer).
     */
    const T_AUTOINCREMENT = self::I_AUTOINCREMENT | self::T_INT;

    /**
     * Flags whether a type is `NOT NULL`
     */
    protected const T_STRICT = 0x01;

    /**
     * `<T_CONST>`: Boolean analog (numeric).
     */
    const T_BOOL = 0xff;
    const T_BOOL_NULL = 0xfe;

    /**
     * `<T_CONST>`: 8-byte signed integer.
     */
    const T_INT = 0xfd;
    const T_INT_NULL = 0xfc;

    /**
     * `<T_CONST>`: 8-byte IEEE floating point number.
     */
    const T_FLOAT = 0xfb;
    const T_FLOAT_NULL = 0xfa;

    /**
     * `<T_CONST>`: Native `DATETIME` type, stored as `YYYY-MM-DD hh:mm:ss` UTC.
     */
    const T_DATETIME = 0xf9;
    const T_DATETIME_NULL = 0xf8;
    const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * `<T_CONST>`: UTF-8 up to 255 bytes.
     */
    const T_STRING = 0xf7;
    const T_STRING_NULL = 0xf6;

    /**
     * `<T_CONST>`: UTF-8 up to 64KiB.
     */
    const T_TEXT = 0x05;
    const T_TEXT_NULL = 0x04;

    /**
     * `<T_CONST>`: Arbitrary binary data up to 4GiB.
     */
    const T_BLOB = 0x03;
    const T_BLOB_NULL = 0x02;

    /**
     * Maps storage types to `T_CONST` names.
     *
     * Resolved storage types in {@link Record} are keys here.
     *
     * This is also used when generating migrations on the command-line.
     */
    const T_CONST_NAMES = [
        'bool' => 'T_BOOL',
        'DateTime' => 'T_DATETIME',
        'float' => 'T_FLOAT',
        'int' => 'T_INT',
        'string' => 'T_STRING',
        'String' => 'T_TEXT',
        'STRING' => 'T_BLOB',
    ];

    /**
     * Maps column types reported by the database into PHP native/annotated types.
     * This is used by {@link Schema::getColumnInfo()}
     */
    protected const PHP_TYPES = [
        // bool
        'BOOLEAN' => 'bool',
        // int
        'BIGINT' => 'int',  // mysql
        'INTEGER' => 'int', // sqlite (must be this type to allow AUTOINCREMENT)
        // float
        'DOUBLE PRECISION' => 'float',
        // string <= 255
        'VARCHAR(255)' => 'string',
        // string <= 64k
        'TEXT' => 'String',     // @var String
        // string > 64k
        'BLOB' => 'STRING',     // @var STRING (sqlite)
        'LONGBLOB' => 'STRING', // @var STRING (mysql)
        // DateTime
        'DATETIME' => 'DateTime',
    ];

    /**
     * Driver-specific schema phrases.
     */
    protected const COLUMN_DEFINITIONS = [
        'mysql' => [
            self::T_AUTOINCREMENT => 'BIGINT NOT NULL PRIMARY KEY AUTO_INCREMENT',
            self::T_BLOB => 'LONGBLOB NOT NULL DEFAULT ""',
            self::T_BOOL => 'BOOLEAN NOT NULL DEFAULT 0',
            self::T_FLOAT => 'DOUBLE PRECISION NOT NULL DEFAULT 0',
            self::T_INT => 'BIGINT NOT NULL DEFAULT 0',
            self::T_STRING => 'VARCHAR(255) NOT NULL DEFAULT ""',
            self::T_TEXT => 'TEXT NOT NULL DEFAULT ""',
            self::T_DATETIME => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            self::T_BLOB_NULL => 'LONGBLOB NULL DEFAULT NULL',
            self::T_BOOL_NULL => 'BOOLEAN NULL DEFAULT NULL',
            self::T_FLOAT_NULL => 'DOUBLE PRECISION NULL DEFAULT NULL',
            self::T_INT_NULL => 'BIGINT NULL DEFAULT NULL',
            self::T_STRING_NULL => 'VARCHAR(255) NULL DEFAULT NULL',
            self::T_TEXT_NULL => 'TEXT NULL DEFAULT NULL',
            self::T_DATETIME_NULL => 'DATETIME NULL DEFAULT NULL',
        ],
        'sqlite' => [
            self::T_AUTOINCREMENT => 'INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT',
            self::T_BLOB => 'BLOB NOT NULL DEFAULT ""',
            self::T_BOOL => 'BOOLEAN NOT NULL DEFAULT 0',
            self::T_FLOAT => 'DOUBLE PRECISION NOT NULL DEFAULT 0',
            self::T_INT => 'INTEGER NOT NULL DEFAULT 0',
            self::T_STRING => 'VARCHAR(255) NOT NULL DEFAULT ""',
            self::T_TEXT => 'TEXT NOT NULL DEFAULT ""',
            self::T_DATETIME => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            self::T_BLOB_NULL => 'BLOB DEFAULT NULL',
            self::T_BOOL_NULL => 'BOOLEAN DEFAULT NULL',
            self::T_FLOAT_NULL => 'DOUBLE PRECISION DEFAULT NULL',
            self::T_INT_NULL => 'INTEGER DEFAULT NULL',
            self::T_STRING_NULL => 'VARCHAR(255) DEFAULT NULL',
            self::T_TEXT_NULL => 'TEXT DEFAULT NULL',
            self::T_DATETIME_NULL => 'DATETIME NULL DEFAULT NULL',
        ]
    ];

    /**
     * @var int[]
     */
    protected $colDefs;

    /**
     * @var DB
     */
    protected $db;

    /**
     * @var Table[]
     */
    protected $tables = [];

    /**
     * @param DB $db
     */
    public function __construct(DB $db)
    {
        $this->db = $db;
        $this->colDefs ??= self::COLUMN_DEFINITIONS[$db->getDriver()];
    }

    /**
     * `ALTER TABLE $table ADD COLUMN $column ...`
     *
     * @param string $table
     * @param string $column
     * @param int $type
     * @return $this
     */
    public function addColumn(string $table, string $column, int $type = self::T_STRING_NULL)
    {
        $type = $this->colDefs[$type & self::T_MASK];
        $this->db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
        unset($this->tables[$table]);
        return $this;
    }

    /**
     * Driver-appropriate constraint creation.
     *
     * @param string $table
     * @param string[] $columns
     * @return $this
     */
    public function addUniqueKey(string $table, array $columns)
    {
        $name = $this->getUniqueKeyName($table, $columns);
        $columns = implode(',', $columns);
        if ($this->db->isSQLite()) {
            $this->db->exec("CREATE UNIQUE INDEX {$name} ON {$table} ({$columns})");
        } else {
            $this->db->exec("ALTER TABLE {$table} ADD CONSTRAINT {$name} UNIQUE ({$columns})");
        }
        return $this;
    }

    /**
     * `CREATE TABLE $table ...`
     *
     * At least one column must be given.
     *
     * @param string $table
     * @param int[] $columns `[ name => <I_CONST> | <T_CONST> ]`
     * @param Column[] $foreign `[ column name => <External Column> ]`
     * @return $this
     */
    public function createTable(string $table, array $columns, array $foreign = [])
    {
        assert(count($columns) > 0);
        $columns = $this->sortColumns($columns);
        $colDefs = [];
        $primaryKey = [];

        // column list
        foreach ($columns as $name => $type) {
            if ($type === self::T_AUTOINCREMENT) {
                $typeDef = $this->colDefs[self::T_AUTOINCREMENT];
            } else {
                $typeDef = $this->colDefs[$type & self::T_MASK];
                if ($type & self::I_PRIMARY) {
                    $primaryKey[] = $name;
                }
            }
            $colDefs[$name] = "{$name} {$typeDef}";
        }

        // non auto-increment primary key
        if ($primaryKey) {
            $colDefs[] = sprintf(
                'CONSTRAINT %s PRIMARY KEY (%s)',
                $this->getPrimaryKeyName($table, $primaryKey),
                implode(',', $primaryKey)
            );
        }

        // foreign keys
        foreach ($foreign as $local => $external) {
            $colDefs[] = sprintf(
                'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(%s) ON UPDATE CASCADE ON DELETE %s',
                $this->getForeignKeyName($table, $local),
                $local,
                $external->getQualifier(),
                $external->getName(),
                $columns[$local] | self::T_STRICT ? 'CASCADE' : 'SET NULL'
            );
        }

        $this->db->exec(sprintf(
            "CREATE TABLE %s (%s)",
            $table,
            implode(', ', $colDefs)
        ));

        return $this;
    }

    /**
     * `ALTER TABLE $table DROP COLUMN $column`
     *
     * SQLite does not support this, so it's skipped.
     * It's beyond the scope of this method (for now) to do table recreation for SQLite.
     *
     * @param string $table
     * @param string $column
     * @return $this
     */
    public function dropColumn(string $table, string $column)
    {
        if (!$this->db->isSQLite()) {
            $this->db->exec("ALTER TABLE {$table} DROP COLUMN {$column}");
        }
        unset($this->tables[$table]);
        return $this;
    }

    /**
     * `DROP TABLE IF EXISTS $table`
     *
     * @param string $table
     * @return $this
     */
    public function dropTable(string $table)
    {
        $this->db->exec("DROP TABLE IF EXISTS {$table}");
        unset($this->tables[$table]);
        return $this;
    }

    /**
     * Driver-appropriate constraint deletion.
     *
     * @param string $table
     * @param string[] $columns
     * @return $this
     */
    public function dropUniqueKey(string $table, array $columns)
    {
        $name = $this->getUniqueKeyName($table, $columns);
        if ($this->db->isSQLite()) {
            $this->db->exec("DROP INDEX {$name}");
        } else {
            $this->db->exec("DROP INDEX {$name} ON {$table}");
        }
        return $this;
    }

    /**
     * Returns column metadata in an associative array.
     *
     * Elements are:
     * - `name`
     * - `type`: PHP native/annotated type (as a string)
     * - `nullable`: boolean
     *
     * The returned `type` can be used to get a `T_CONST` name from {@link Schema::T_CONST_NAMES}
     *
     * @param string $table
     * @param string $column
     * @return array[] Keyed by name.
     */
    public function getColumnInfo(string $table): array
    {
        if ($this->db->isSQLite()) {
            $info = $this->db->query("PRAGMA table_info({$table})")->fetchAll();
            return array_combine(array_column($info, 'name'), array_map(fn(array $each) => [
                'name' => $each['name'],
                'type' => static::PHP_TYPES[$each['type']] ?? 'string',
                'nullable' => !$each['notnull'],
            ], $info));
        }
        $info = $this->db->query("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = \"{$table}\" ORDER BY ordinal_position")->fetchAll();
        return array_combine(array_column($info, 'column_name'), array_map(fn(array $each) => [
            'name' => $each['column_name'],
            'type' => static::PHP_TYPES[$each['data_type']] ?? 'string',
            'nullable' => $each['is_nullable'] === 'YES',
        ], $info));
    }

    /**
     * @return DB
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * `FK_TABLE__COLUMN`
     *
     * @param string $table
     * @param string $column
     * @return string
     */
    final public function getForeignKeyName(string $table, string $column): string
    {
        return 'FK_' . $table . '__' . $column;
    }

    /**
     * `PK_TABLE__COLUMN__COLUMN__COLUMN`
     *
     * @param string $table
     * @param string[] $columns
     * @return string
     */
    final public function getPrimaryKeyName(string $table, array $columns): string
    {
        sort($columns, SORT_STRING);
        return 'PK_' . $table . '__' . implode('__', $columns);
    }

    /**
     * @param string $name
     * @return null|Table
     */
    public function getTable(string $name)
    {
        if (!isset($this->tables[$name])) {
            if ($this->db->isSQLite()) {
                $info = $this->db->query("PRAGMA table_info({$name})")->fetchAll();
                $cols = array_column($info, 'name');
            } else {
                $cols = $this->db->query("SELECT column_name FROM information_schema.tables WHERE table_name = \"{$name}\"")->fetchAll(DB::FETCH_COLUMN);
            }
            if (!$cols) {
                return null;
            }
            $this->tables[$name] = Table::factory($this->db, $name, $cols);
        }
        return $this->tables[$name];
    }

    /**
     * `UQ_TABLE__COLUMN__COLUMN__COLUMN`
     *
     * @param string $table
     * @param string[] $columns
     * @return string
     */
    final public function getUniqueKeyName(string $table, array $columns): string
    {
        sort($columns, SORT_STRING);
        return 'UQ_' . $table . '__' . implode('__', $columns);
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
        return $this->getTable($table);
    }

    /**
     * @param $offset
     * @param $value
     * @throws LogicException
     */
    final public function offsetSet($offset, $value)
    {
        throw new LogicException('The schema cannot be altered this way.');
    }

    /**
     * @param $offset
     * @throws LogicException
     */
    final public function offsetUnset($offset)
    {
        throw new LogicException('The schema cannot be altered this way.');
    }

    /**
     * `ALTER TABLE $oldName RENAME TO $newName`
     *
     * @param string $oldName
     * @param string $newName
     * @return $this
     */
    public function renameTable(string $oldName, string $newName)
    {
        $this->db->exec("ALTER TABLE {$oldName} RENAME TO {$newName}");
        unset($this->tables[$oldName]);
        return $this;
    }

    /**
     * Sorts according to index priority, storage size/complexity, and name.
     *
     * @param int[] $types
     * @return int[]
     */
    protected function sortColumns(array $types): array
    {
        uksort($types, function (string $a, string $b) use ($types) {
            // descending type constant, ascending name
            return $types[$b] <=> $types[$a] ?: $a <=> $b;
        });
        return $types;
    }

}
