<?php

namespace Helix\DB;

use Closure;
use Countable;
use Generator;
use Helix\DB;
use Helix\DB\SQL\ExpressionInterface;
use Helix\DB\SQL\Predicate;
use IteratorAggregate;

/**
 * Represents a `SELECT` query.
 *
 * @method static static factory(DB $db, $table, array $columns)
 */
class Select extends AbstractTable implements Countable, IteratorAggregate, ExpressionInterface {

    use FactoryTrait;

    /**
     * Compiled column list.
     *
     * @internal
     * @var string
     */
    protected $_columns = '';

    /**
     * Compiled column list.
     *
     * @internal
     * @var string
     */
    protected $_group = '';

    /**
     * Compiled predicates.
     *
     * @internal
     * @var string
     */
    protected $_having = '';

    /**
     * Compiled unions and intersections.
     *
     * @internal
     * @var string
     */
    protected $_import = '';

    /**
     * Compiled joins.
     *
     * @internal
     * @var string
     */
    protected $_join = '';

    /**
     * Compiled limit and offset.
     *
     * @internal
     * @var string
     */
    protected $_limit = '';

    /**
     * Compiled column list.
     *
     * @internal
     * @var string
     */
    protected $_order = '';

    /**
     * Compiled predicates.
     *
     * @internal
     * @var string
     */
    protected $_where = '';

    /**
     * Human-readable alias.
     * This is initialized using `uniqid()` and the table's name.
     *
     * @var string
     */
    protected $alias;

    /**
     * A callback to yield each result.
     * Defaults to yielding directly from the statement.
     *
     * @var Closure `(Statement $statement):Generator`
     */
    protected $fetcher;

    /**
     * Columns that can be accessed by an outer query.
     *
     * @var Column[]
     */
    protected $refs = [];

    /**
     * @var string
     */
    protected $table;

    /**
     * @param DB $db
     * @param string|Select $table
     * @param string[] $columns
     */
    public function __construct (DB $db, $table, array $columns) {
        parent::__construct($db);
        if ($table instanceof Select) {
            $this->table = $table->toSubquery();
            $this->alias = uniqid('_') . "_{$table->alias}";
        }
        else {
            $this->table = (string)$table;
            $this->alias = uniqid('_') . "__{$table}";
        }
        $this->setColumns($columns);
        $this->fetcher = function(Statement $statement) {
            yield from $statement;
        };
    }

    /**
     * @param array $args
     * @return Statement
     */
    public function __invoke (array $args = []) {
        return $this->execute($args);
    }

    /**
     * Returns the alias.
     *
     * @return string
     */
    final public function __toString () {
        return $this->alias;
    }

    /**
     * Clones the instance and selects `COUNT(*)`, using the given execution arguments.
     *
     * @param array $args Execution arguments.
     * @return int
     */
    public function count (array $args = []): int {
        $clone = clone $this;
        $clone->_columns = 'COUNT(*)';
        $clone->_order = '';
        return (int)$clone->execute($args)->fetchColumn();
    }

    /**
     * Executes the select, preparing a statement first if arguments are used.
     *
     * @param array $args
     * @return Statement
     */
    public function execute (array $args = []) {
        if (empty($args)) {
            return $this->db->query($this->toSql());
        }
        return $this->prepare()->__invoke($args);
    }

    /**
     * Executes and fetches all results.
     *
     * @see fetcher
     *
     * @param array $args Execution arguments.
     * @return array
     */
    public function getAll (array $args = []): array {
        return iterator_to_array($this->fetcher->__invoke($this->execute($args)));
    }

    /**
     * Executes and yields from the fetcher.
     * This is preferable over `fetchAll()` for iterating large result sets.
     *
     * @see fetcher
     *
     * @param array $args Execution arguments.
     * @return Generator
     */
    public function getEach (array $args = []) {
        yield from $this->fetcher->__invoke($this->execute($args));
    }

    /**
     * Executes and returns from the fetcher.
     *
     * @see fetcher
     *
     * @param array $args
     * @return mixed
     */
    public function getFirst (array $args = []) {
        return $this->getEach($args)->current();
    }

    /**
     * Executes without arguments and yields from the fetcher.
     *
     * @see fetcher
     *
     * @return Generator
     */
    public function getIterator () {
        yield from $this->getEach();
    }

    /**
     * Adds a column to the `GROUP BY` clause.
     *
     * @param string $column
     * @return $this
     */
    public function group (string $column) {
        if (!strlen($this->_group)) {
            $this->_group = " GROUP BY {$column}";
        }
        else {
            $this->_group .= ", {$column}";
        }
        return $this;
    }

    /**
     * Adds a condition to the `HAVING` clause.
     *
     * @param string $condition
     * @return $this
     */
    public function having (string $condition) {
        if (!strlen($this->_having)) {
            $this->_having = " HAVING {$condition}";
        }
        else {
            $this->_having .= " AND {$condition}";
        }
        return $this;
    }

    /**
     * `INTERSECT` or `INTERSECT ALL`
     *
     * @param Select $select
     * @param bool $all
     * @return $this
     */
    public function intersect (Select $select, $all = false) {
        $select = clone $select;
        $select->_order = '';
        $select->_limit = '';
        if ($all) {
            $this->_import .= " INTERSECT ALL {$select->toSql()}";
        }
        else {
            $this->_import .= " INTERSECT {$select->toSql()}";
        }
        return $this;
    }

    /**
     * `NOT EXISTS (SELECT ...)`
     *
     * @return Predicate
     */
    public function isEmpty () {
        return Predicate::factory($this->db, "NOT EXISTS ({$this->toSql()})");
    }

    /**
     * `EXISTS (SELECT ...)`
     *
     * @return Predicate
     */
    public function isNotEmpty () {
        return Predicate::factory($this->db, "EXISTS ({$this->toSql()})");
    }

    /**
     * Adds a `JOIN` clause.
     *
     * @param string|Select $table
     * @param string $condition
     * @param string $type
     * @return $this
     */
    public function join ($table, string $condition, string $type = 'INNER') {
        if ($table instanceof Select) {
            $table = $table->toSubquery();
        }
        $this->_join .= " {$type} JOIN {$table} ON {$condition}";
        return $this;
    }

    /**
     * Sets the `LIMIT` clause.
     *
     * @param int $limit
     * @param int $offset
     * @return $this
     */
    public function limit (int $limit, int $offset = 0) {
        if ($limit == 0) {
            $this->_limit = '';
        }
        else {
            $this->_limit = " LIMIT {$limit}";
            if ($offset > 1) {
                $this->_limit .= " OFFSET {$offset}";
            }
        }
        return $this;
    }

    /**
     * Returns a reference {@link Column} for an outer query, qualified by the instance's alias.
     *
     * @param int|string $ref Ordinal or reference name.
     * @return null|Column
     */
    public function offsetGet ($ref) {
        if (is_int($ref)) {
            return current(array_slice($this->refs, $ref, 1)) ?: null;
        }
        return $this->refs[$ref] ?? null;
    }

    /**
     * Sets the `ORDER BY` clause.
     *
     * @param string $order
     * @return $this
     */
    public function order (string $order) {
        if (strlen($order)) {
            $order = " ORDER BY {$order}";
        }
        $this->_order = $order;
        return $this;
    }

    /**
     * @return Statement
     */
    public function prepare () {
        return $this->db->prepare($this->toSql());
    }

    /**
     * @param string $alias
     * @return $this
     */
    public function setAlias (string $alias) {
        $this->alias = $alias;
        foreach ($this->refs as $k => $column) {
            $this->refs[$k] = $column->setQualifier($alias);
        }
        return $this;
    }

    /**
     * Compiles the column list and exposed reference columns.
     *
     * Columns may be expressions, like `COUNT(*)`
     *
     * Unless an alias is given for such columns, they can't be referenced externally.
     *
     * @param string[] $expressions Keyed by alias if applicable.
     * @return $this
     */
    public function setColumns (array $expressions) {
        $this->refs = [];
        $_columns = [];
        foreach ($expressions as $alias => $expr) {
            preg_match('/^([a-z_][a-z0-9_]+\.)?(?<name>[a-z_][a-z0-9_]+)$/i', $expr, $match);
            $name = $match['name'] ?? null;
            if (is_int($alias)) {
                $alias = $name;
            }
            elseif ($alias !== $name) {
                $expr .= " AS {$alias}";
            }
            if (isset($alias)) {
                $this->refs[$alias] = Column::factory($this->db, $alias, $this->alias);
            }
            $_columns[] = "{$expr}";
        }
        $this->_columns = implode(', ', $_columns);
        return $this;
    }

    /**
     * @param Closure $fetcher
     * @return $this
     */
    public function setFetcher (Closure $fetcher) {
        $this->fetcher = $fetcher;
        return $this;
    }

    /**
     * `SELECT ...`
     *
     * @return string
     */
    public function toSql (): string {
        $sql = "SELECT {$this->_columns} FROM {$this->table}";
        $sql .= $this->_join;
        $sql .= $this->_where;
        $sql .= $this->_group;
        $sql .= $this->_having;
        $sql .= $this->_import;
        $sql .= $this->_order;
        $sql .= $this->_limit;
        return $sql;
    }

    /**
     * `(SELECT ...) AS ALIAS`
     *
     * @return string
     */
    public function toSubquery (): string {
        return "({$this->toSql()}) AS {$this->alias}";
    }

    /**
     * `UNION` or `UNION ALL`
     *
     * @param Select $select
     * @param bool $all
     * @return $this
     */
    public function union (Select $select, $all = false) {
        $select = clone $select;
        $select->_order = '';
        $select->_limit = '';
        if ($all) {
            $this->_import .= " UNION ALL {$select->toSql()}";
        }
        else {
            $this->_import .= " UNION {$select->toSql()}";
        }
        return $this;
    }

    /**
     * Adds a condition to the `WHERE` clause.
     *
     * @param string $condition
     * @return $this
     */
    public function where (string $condition) {
        if (!strlen($this->_where)) {
            $this->_where = " WHERE {$condition}";
        }
        else {
            $this->_where .= " AND {$condition}";
        }
        return $this;
    }
}