<?php

namespace Helix\DB;

use Closure;
use Helix\DB;

/**
 * Table manipulation using arrays.
 *
 * Accessing the table as an array produces {@link Column} instances.
 *
 * @immutable Mutations operate on and return clones.
 *
 * @method static static factory(DB $db, string $name, array $columns)
 */
class Table extends AbstractTable {

    use FactoryTrait;

    /**
     * Prepared query cache.
     *
     * @var Statement[]
     */
    protected $_cache = [];

    /**
     * `[name => Column]`
     *
     * @var Column[]
     */
    protected $columns = [];

    /**
     * @var string
     */
    protected $name;

    /**
     * @param DB $db
     * @param string $name
     * @param string[] $columns
     */
    public function __construct (DB $db, string $name, array $columns) {
        parent::__construct($db);
        $this->name = $name;
        foreach ($columns as $column) {
            $this->columns[$column] = Column::factory($db, $column, $this);
        }
    }

    /**
     * Returns the table name.
     *
     * @return string
     */
    final public function __toString () {
        return $this->name;
    }

    /**
     * `INSERT IGNORE`
     *
     * @param array $values
     * @return int Rows affected.
     */
    public function apply (array $values): int {
        $columns = implode(',', array_keys($values));
        $values = $this->db->quoteList($values);
        switch ($this->db) {
            case 'sqlite':
                return $this->db->exec(
                    "INSERT OR IGNORE INTO {$this} ({$columns}) VALUES ({$values})"
                );
            default:
                return $this->db->exec(
                    "INSERT IGNORE INTO {$this} ({$columns}) VALUES ({$values})"
                );
        }
    }

    /**
     * Caches a prepared query.
     *
     * @param string $key
     * @param Closure $prepare `():Statement`
     * @return Statement
     */
    protected function cache (string $key, Closure $prepare) {
        return $this->_cache[$key] ?? $this->_cache[$key] = $prepare->__invoke();
    }

    /**
     * @param array $match `[a => b]`
     * @return int
     */
    public function count (array $match = []) {
        $select = $this->select(['COUNT(*)']);
        foreach ($match as $a => $b) {
            $select->where($this->db->match($this[$a] ?? $a, $b));
        }
        return (int)$select->execute()->fetchColumn();
    }

    /**
     * Executes a deletion using arbitrary columns.
     *
     * @see DB::match()
     *
     * @param array $match
     * @return int Rows affected.
     */
    public function delete (array $match): int {
        foreach ($match as $a => $b) {
            $match[$a] = $this->db->match($this[$a] ?? $a, $b);
        }
        $match = implode(' AND ', $match);
        return $this->db->exec("DELETE FROM {$this} WHERE {$match}");
    }

    /**
     * @return Column[]
     */
    final public function getColumns (): array {
        return $this->columns;
    }

    /**
     * @return string
     */
    final public function getName (): string {
        return $this->name;
    }

    /**
     * Executes an insertion using arbitrary columns.
     *
     * @param array $values
     * @return Statement
     */
    public function insert (array $values) {
        $columns = implode(',', array_keys($values));
        $values = $this->db->quoteList($values);
        return $this->db->query("INSERT INTO {$this} ($columns) VALUES ($values)");
    }

    /**
     * @param int|string $column
     * @return Column
     */
    public function offsetGet ($column) {
        if (is_int($column)) {
            return current(array_slice($this->columns, $column, 1)) ?: null;
        }
        return $this->columns[$column] ?? null;
    }

    /**
     * Returns a selection object for columns in the table.
     *
     * @param string[] $columns Defaults to all columns.
     * @return Select
     */
    public function select (array $columns = []) {
        if (empty($columns)) {
            $columns = $this->columns;
        }
        return Select::factory($this->db, $this, $columns);
    }

    /**
     * Returns a clone with a different name. Columns are also re-qualified.
     *
     * @param string $name
     * @return Table
     */
    public function setName (string $name) {
        $clone = clone $this;
        $clone->name = $name;
        foreach ($this->columns as $name => $column) {
            $clone->columns[$name] = $column->setQualifier($clone);
        }
        return $clone;
    }

    /**
     * Executes an update using arbitrary columns.
     *
     * @see DB::match()
     *
     * @param array $values
     * @param array $match
     * @return int Rows affected.
     */
    public function update (array $values, array $match): int {
        foreach ($this->db->quoteArray($values) as $key => $value) {
            $values[$key] = "{$key} = {$value}";
        }
        $values = implode(', ', $values);
        foreach ($match as $a => $b) {
            $match[$a] = $this->db->match($this[$a] ?? $a, $b);
        }
        $match = implode(' AND ', $match);
        return $this->db->exec("UPDATE {$this} SET {$values} WHERE {$match}");
    }
}