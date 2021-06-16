<?php

namespace Helix\DB;

use ArrayAccess;
use Helix\DB;
use LogicException;

/**
 * Schema control and metadata.
 *
 * The column definition constants are two bytes each, used in bitwise composition.
 * - The high-byte (`<I_CONST>`) is used for the specific index type.
 *      - Ascending index priority. For example, `I_AUTOINCREMENT` is `0xff00`
 * - The low-byte (`<T_CONST>`) is used for the specific storage type.
 *      - The final bit `0x01` flags `NOT NULL`
 * - The literal values may change in the future, do not hard code them.
 * - The values may expand to use a total of 4 or 8 bytes to accommodate more stuff.
 *
 * Definition constants are never returned by this class' methods. The methods can only receive them.
 *
 * @method static static factory(DB $db)
 */
class Schema implements ArrayAccess {

    use FactoryTrait;

    /**
     * `<TABLE_CONST>`: Multi-column primary key.
     */
    const TABLE_PRIMARY = 0;

    /**
     * `<TABLE_CONST>`: Groups of columns are unique together.
     */
    const TABLE_UNIQUE = 1;

    /**
     * `<TABLE_CONST>`: Associative foreign keys.
     */
    const TABLE_FOREIGN = 2;

    /**
     * Higher byte mask (column index type).
     */
    protected const I_MASK = 0xff00;

    /**
     * Partial definition for `T_AUTOINCREMENT`, use that in compositions instead of this.
     */
    protected const I_AUTOINCREMENT = 0xff00;

    /**
     * `<I_CONST>`: Column is the primary key.
     */
    const I_PRIMARY = 0xfe00;

    /**
     * `<I_CONST>`: Column is unique.
     */
    const I_UNIQUE = 0xfd00;

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
     * Maps native/annotated types to `T_CONST` names.
     * This is used when generating migrations on the command-line.
     */
    const T_CONST_NAMES = [
        'bool' => 'T_BOOL',
        'boolean' => 'T_BOOL',      // gettype()
        'DateTime' => 'T_DATETIME', // dehydrated (see Record)
        'double' => 'T_BLOB',       // gettype()
        'float' => 'T_FLOAT',
        'int' => 'T_INT',
        'integer' => 'T_INT',       // gettype()
        'string' => 'T_STRING',
        'String' => 'T_TEXT',       // @var String
        'STRING' => 'T_BLOB',       // @var STRING
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
            self::I_AUTOINCREMENT => 'PRIMARY KEY AUTO_INCREMENT',
            self::I_PRIMARY => 'PRIMARY KEY',
            self::I_UNIQUE => 'UNIQUE',
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
            self::I_AUTOINCREMENT => 'PRIMARY KEY AUTOINCREMENT',
            self::I_PRIMARY => 'PRIMARY KEY',
            self::I_UNIQUE => 'UNIQUE',
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
    public function __construct (DB $db) {
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
    public function addColumn (string $table, string $column, int $type = self::T_STRING_NULL) {
        $type = $this->colDefs[$type & self::T_MASK];
        $this->db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
        unset($this->tables[$table]);
        return $this;
    }

    /**
     * `CREATE TABLE $table ...`
     *
     * At least one column must be given.
     *
     * `$constraints` is a multidimensional array of table-level constraints.
     * - `TABLE_PRIMARY => [col, col, col]`
     *      - String list of columns composing the primary key.
     *      - Not needed for single-column primary keys. Use `I_PRIMARY` or `T_AUTOINCREMENT` for that.
     * - `TABLE_UNIQUE => [ [col, col, col] , ... ]`
     *      - One or more string lists of columns, each grouping composing a unique key together.
     * - `TABLE_FOREIGN => [ col => <External Column> ]`
     *      - Associative columns that are each foreign keys to a {@link Column} instance.
     *
     * @param string $table
     * @param int[] $columns `[ name => <I_CONST> | <T_CONST> ]`
     * @param array[] $constraints `[ <TABLE_CONST> => spec ]`
     * @return $this
     */
    public function createTable (string $table, array $columns, array $constraints = []) {
        $defs = $this->toColumnDefinitions($columns);

        /** @var string[] $pk */
        if ($pk = $constraints[self::TABLE_PRIMARY] ?? []) {
            $defs[] = $this->toPrimaryKeyConstraint($table, $pk);
        }

        /** @var string[] $unique */
        foreach ($constraints[self::TABLE_UNIQUE] ?? [] as $unique) {
            $defs[] = $this->toUniqueKeyConstraint($table, $unique);
        }

        /** @var string $local */
        /** @var Column $foreign */
        foreach ($constraints[self::TABLE_FOREIGN] ?? [] as $local => $foreign) {
            $defs[] = $this->toForeignKeyConstraint($table, $local, $columns[$local], $foreign);
        }

        $sql = sprintf(
            "CREATE TABLE %s (%s)",
            $table,
            implode(', ', $defs)
        );

        $this->db->exec($sql);
        return $this;
    }

    /**
     * `ALTER TABLE $table DROP COLUMN $column`
     *
     * @param string $table
     * @param string $column
     * @return $this
     */
    public function dropColumn (string $table, string $column) {
        $this->db->exec("ALTER TABLE {$table} DROP COLUMN {$column}");
        unset($this->tables[$table]);
        return $this;
    }

    /**
     * `DROP TABLE IF EXISTS $table`
     *
     * @param string $table
     */
    public function dropTable (string $table): void {
        $this->db->exec("DROP TABLE IF EXISTS {$table}");
        unset($this->tables[$table]);
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
    public function getColumnInfo (string $table): array {
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
    public function getDb () {
        return $this->db;
    }

    /**
     * @param string $name
     * @return null|Table
     */
    public function getTable (string $name) {
        if (!isset($this->tables[$name])) {
            if ($this->db->isSQLite()) {
                $info = $this->db->query("PRAGMA table_info({$name})")->fetchAll();
                $cols = array_column($info, 'name');
            }
            else {
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
     * Whether a table exists.
     *
     * @param string $table
     * @return bool
     */
    final public function offsetExists ($table): bool {
        return (bool)$this->offsetGet($table);
    }

    /**
     * Returns a table by name.
     *
     * @param string $table
     * @return null|Table
     */
    public function offsetGet ($table) {
        return $this->getTable($table);
    }

    /**
     * @param $offset
     * @param $value
     * @throws LogicException
     */
    final public function offsetSet ($offset, $value) {
        throw new LogicException('The schema cannot be altered this way.');
    }

    /**
     * @param $offset
     * @throws LogicException
     */
    final public function offsetUnset ($offset) {
        throw new LogicException('The schema cannot be altered this way.');
    }

    /**
     * `ALTER TABLE $table RENAME COLUMN $oldName TO $newName`
     *
     * @param string $table
     * @param string $oldName
     * @param string $newName
     * @return $this
     */
    public function renameColumn (string $table, string $oldName, string $newName) {
        $this->db->exec("ALTER TABLE {$table} RENAME COLUMN {$oldName} TO {$newName}");
        unset($this->tables[$table]);
        return $this;
    }

    /**
     * `ALTER TABLE $oldName RENAME TO $newName`
     *
     * @param string $oldName
     * @param string $newName
     * @return $this
     */
    public function renameTable (string $oldName, string $newName) {
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
    protected function sortColumns (array $types): array {
        uksort($types, function(string $a, string $b) use ($types) {
            // descending type constant, ascending name
            return $types[$b] <=> $types[$a] ?: $a <=> $b;
        });
        return $types;
    }

    /**
     * @param int[] $columns `[ name => <I_CONST> | <T_CONST> ]`
     * @return string[]
     */
    protected function toColumnDefinitions (array $columns): array {
        assert(count($columns) > 0);
        $columns = $this->sortColumns($columns);
        $defs = [];

        /**
         * @var string $name
         * @var int $type
         */
        foreach ($columns as $name => $type) {
            $defs[$name] = sprintf("%s %s", $name, $this->colDefs[$type & self::T_MASK]);
            if ($indexSql = $type & self::I_MASK) {
                $defs[$name] .= " {$this->colDefs[$indexSql]}";
            }
        }

        return $defs;
    }

    /**
     * @param string $table
     * @param string $local
     * @param int $type
     * @param Column $foreign
     * @return string
     */
    protected function toForeignKeyConstraint (string $table, string $local, int $type, Column $foreign): string {
        return sprintf(
            'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(%s) ON UPDATE CASCADE ON DELETE %s',
            $this->toForeignKeyConstraint_name($table, $local),
            $local,
            $foreign->getQualifier(),
            $foreign->getName(),
            $type | self::T_STRICT ? 'CASCADE' : 'SET NULL'
        );
    }

    /**
     * `FK_TABLE__COLUMN`
     *
     * @param string $table
     * @param string $column
     * @return string
     */
    protected function toForeignKeyConstraint_name (string $table, string $column): string {
        return 'FK_' . $table . '__' . $column;
    }

    /**
     * @param string $table
     * @param string[] $columns
     * @return string
     */
    protected function toPrimaryKeyConstraint (string $table, array $columns): string {
        return sprintf(
            'CONSTRAINT %s PRIMARY KEY (%s)',
            $this->toPrimaryKeyConstraint_name($table, $columns),
            implode(',', $columns)
        );
    }

    /**
     * `PK_TABLE__COLUMN__COLUMN__COLUMN`
     *
     * @param string $table
     * @param string[] $columns
     * @return string
     */
    protected function toPrimaryKeyConstraint_name (string $table, array $columns): string {
        sort($columns, SORT_STRING);
        return 'PK_' . $table . '__' . implode('__', $columns);
    }

    /**
     * @param string $table
     * @param string[] $columns
     * @return string
     */
    protected function toUniqueKeyConstraint (string $table, array $columns): string {
        return sprintf(
            'CONSTRAINT %s UNIQUE (%s)',
            $this->toUniqueKeyConstraint_name($table, $columns),
            implode(',', $columns)
        );
    }

    /**
     * `UQ_TABLE__COLUMN__COLUMN__COLUMN`
     *
     * @param string $table
     * @param string[] $columns
     * @return string
     */
    protected function toUniqueKeyConstraint_name (string $table, array $columns): string {
        sort($columns, SORT_STRING);
        return 'UQ_' . $table . '__' . implode('__', $columns);
    }
}