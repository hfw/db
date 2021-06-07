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
     * Compiled source table.
     *
     * @internal
     * @var string
     */
    protected $_table;

    /**
     * Compiled predicates.
     *
     * @internal
     * @var string
     */
    protected $_where = '';

    /**
     * Human-readable alias.
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
     * The original table given to the constructor.
     *
     * @var AbstractTable
     */
    protected $table;

    /**
     * @param DB $db
     * @param string|AbstractTable $table
     * @param string[] $columns
     */
    public function __construct (DB $db, $table, array $columns = ['*']) {
        static $autoAlias = 0;
        $autoAlias++;
        parent::__construct($db);
        if ($table instanceof Select) {
            $this->_table = $table->toSubquery();
            $this->alias = "_anon{$autoAlias}_{$table->alias}";
        }
        else {
            if (is_string($table)) {
                $table = $db->getTable($table);
                assert(isset($table));
            }
            $this->_table = (string)$table;
            $this->alias = "_anon{$autoAlias}_{$table}";
        }
        $this->table = $table;
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
     * @return Column[]
     */
    public function getColumns () {
        return $this->refs;
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
     * Executes and returns the first column of the first row.
     * Use this for reductive queries that only have a single result.
     *
     * @return mixed
     */
    public function getResult (array $args = []) {
        return $this->execute($args)->fetchColumn();
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
     * Adds conditions to the `HAVING` clause.
     *
     * @param string ...$conditions
     * @return $this
     */
    public function having (string ...$conditions) {
        assert(count($conditions) > 0);
        $conditions = implode(' AND ', $conditions);
        if (!strlen($this->_having)) {
            $this->_having = " HAVING {$conditions}";
        }
        else {
            $this->_having .= " AND {$conditions}";
        }
        return $this;
    }

    /**
     * `INTERSECT SELECT ...`
     *
     * > Note: MySQL does not support `INTERSECT`. An `INNER JOIN` on every column is used instead.
     *
     * @param Select $select
     * @return $this
     */
    public function intersect (Select $select) {
        if ($this->db->isMySQL()) {
            // to be standards compliant, this hack must fail if they don't have the same cols.
            assert(count($this->refs) === count($select->refs) and !array_diff_key($this->refs, $select->refs));
            $this->join($select, ...array_map(function(string $alias, Column $ref) {
                return $ref->is($this->refs[$alias]);
            }, array_keys($select->refs), $select->refs));
            return $this;
        }
        $select = clone $select;
        $select->_order = '';
        $select->_limit = '';
        $this->_import .= " INTERSECT {$select->toSql()}";
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
     * Adds `INNER JOIN $table ON $conditions`
     *
     * @param string|Select $table
     * @param string ...$conditions
     * @return $this
     */
    public function join ($table, string ...$conditions) {
        assert(count($conditions) > 0);
        if ($table instanceof Select) {
            $table = $table->toSubquery();
        }
        $conditions = implode(' AND ', $conditions);
        $this->_join .= " INNER JOIN {$table} ON {$conditions}";
        return $this;
    }

    /**
     * Adds `LEFT JOIN $table ON $conditions`
     *
     * @param string|Select $table
     * @param string ...$conditions
     * @return $this
     */
    public function joinLeft ($table, string ...$conditions) {
        assert(count($conditions) > 0);
        if ($table instanceof Select) {
            $table = $table->toSubquery();
        }
        $conditions = implode(' AND ', $conditions);
        $this->_join .= " LEFT JOIN {$table} ON {$conditions}";
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
     * Unless an alias is given for such expressions, they can't be referenced externally.
     *
     * @param string[] $expressions Keyed by alias if applicable.
     * @return $this
     */
    public function setColumns (array $expressions = ['*']) {
        if ($expressions === ['*']) {
            $expressions = array_keys($this->table->getColumns());
        }
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
        $sql = "SELECT {$this->_columns} FROM {$this->_table}";
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
     * `UNION SELECT ...`
     *
     * @param Select $select
     * @return $this
     */
    public function union (Select $select) {
        $select = clone $select;
        $select->_order = '';
        $select->_limit = '';
        $this->_import .= " UNION {$select->toSql()}";
        return $this;
    }

    /**
     * `UNION ALL SELECT ...`
     *
     * @param Select $select
     * @return $this
     */
    public function unionAll (Select $select) {
        $select = clone $select;
        $select->_order = '';
        $select->_limit = '';
        $this->_import .= " UNION ALL {$select->toSql()}";
        return $this;
    }

    /**
     * Adds conditions to the `WHERE` clause.
     *
     * @param string ...$conditions
     * @return $this
     */
    public function where (string ...$conditions) {
        assert(count($conditions) > 0);
        $conditions = implode(' AND ', $conditions);
        if (!strlen($this->_where)) {
            $this->_where = " WHERE {$conditions}";
        }
        else {
            $this->_where .= " AND {$conditions}";
        }
        return $this;
    }
}