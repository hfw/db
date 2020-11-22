<?php

namespace Helix\DB\SQL;

use Helix\DB;
use Helix\DB\Select;

/**
 * Produces comparative expressions for the instance.
 *
 * Because SQLite lacks the `ANY` and `ALL` comparison operators,
 * subqueries are instead nested and correlated using `EXISTS` or `NOT EXISTS`.
 *
 * This also requires that the subquery's first column is referable.
 */
trait ComparisonTrait {

    /**
     * @var DB
     */
    protected $db;

    /**
     * `COALESCE($this, ...$values)`
     *
     * @param array $values
     * @return Value
     */
    public function coalesce (array $values) {
        array_unshift($values, $this);
        $values = $this->db->quoteList($values);
        return $this->db->factory(Value::class, $this->db, "COALESCE({$values})");
    }

    /**
     * Null-safe equality.
     *
     * - Mysql: `$this <=> $arg`, or `$this <=> ANY ($arg)`
     * - SQLite: `$this IS $arg`, or `EXISTS (... WHERE $this IS $arg[0])`
     *
     * @param null|bool|number|string|Select $arg
     * @return Predicate
     */
    public function is ($arg): Predicate {
        if ($arg instanceof Select) {
            if ($this->db->isSQLite()) {
                /** @var Select $sub */
                $sub = $this->db->factory(Select::class, $this->db, $arg, [$arg[0]]);
                return $sub->where("{$this} IS {$arg[0]}")->isNotEmpty();
            }
            return $this->db->factory(Predicate::class, "{$this} <=> ANY ({$arg->toSql()})");
        }
        if ($arg === null or is_bool($arg)) {
            $arg = ['' => 'NULL', 1 => 'TRUE', 0 => 'FALSE'][$arg];
        }
        else {
            $arg = $this->db->quote($arg);
        }
        if ($this->db->isMySQL()) {
            return $this->db->factory(Predicate::class, "{$this} <=> {$arg}");
        }
        return $this->db->factory(Predicate::class, "{$this} IS {$arg}");
    }

    /**
     * `$this BETWEEN $min AND $max` (inclusive)
     *
     * @param number $min
     * @param number $max
     * @return Predicate
     */
    public function isBetween ($min, $max) {
        $min = $this->db->quote($min);
        $max = $this->db->quote($max);
        return $this->db->factory(Predicate::class, "{$this} BETWEEN {$min} AND {$max}");
    }

    /**
     * `$this = $arg` or `$this IN ($arg)`
     *
     * @param bool|number|string|array|Select $arg
     * @return Predicate
     */
    public function isEqual ($arg) {
        return $this->db->match($this, $arg);
    }

    /**
     * `$this IS FALSE`
     *
     * @return Predicate
     */
    public function isFalse () {
        return $this->db->factory(Predicate::class, "{$this} IS FALSE");
    }

    /**
     * `$this > $arg`, or driver-specific subquery comparison.
     *
     * - MySQL: `$this > ALL ($arg)` or `$this > ANY ($arg)`
     * - SQLite:
     *      - ALL: `NOT EXISTS (... WHERE $this <= $arg[0])`
     *      - ANY: `EXISTS (... WHERE $this > $arg[0])`
     *
     * @param number|string|Select $arg
     * @param string $multi `ALL|ANY`
     * @return Predicate
     */
    public function isGreater ($arg, string $multi = 'ALL') {
        if ($arg instanceof Select) {
            switch ($this->db) {
                case 'sqlite':
                    /** @var Select $sub */
                    $sub = $this->db->factory(Select::class, $this->db, $arg, [$arg[0]]);
                    switch ($multi) {
                        case 'ANY':
                            return $sub->where("{$this} > {$arg[0]}")->isNotEmpty();
                        default:
                            return $sub->where("{$this} <= {$arg[0]}")->isEmpty();
                    }
                default:
                    return $this->db->factory(Predicate::class, "{$this} > {$multi} ({$arg->toSql()})");
            }
        }
        return $this->db->factory(Predicate::class, "{$this} > {$this->db->quote($arg)}");
    }

    /**
     * `$this >= $arg`, or driver-specific subquery comparison.
     *
     * - MySQL: `$this >= ALL ($arg)` or `$this >= ANY ($arg)`
     * - SQLite:
     *      - ALL: `NOT EXISTS (... WHERE $this < $arg[0])`
     *      - ANY: `EXISTS (... WHERE $this >= $arg[0])`
     *
     * @param number|string|Select $arg
     * @param string $multi `ALL|ANY`
     * @return Predicate
     */
    public function isGreaterOrEqual ($arg, string $multi = 'ALL') {
        if ($arg instanceof Select) {
            switch ($this->db) {
                case 'sqlite':
                    /** @var Select $sub */
                    $sub = $this->db->factory(Select::class, $this->db, $arg, [$arg[0]]);
                    switch ($multi) {
                        case 'ANY':
                            return $sub->where("{$this} >= {$arg[0]}")->isNotEmpty();
                        default:
                            return $sub->where("{$this} < {$arg[0]}")->isEmpty();
                    }
                default:
                    return $this->db->factory(Predicate::class, "{$this} >= {$multi} ({$arg->toSql()})");
            }
        }
        return $this->db->factory(Predicate::class, "{$this} >= {$this->db->quote($arg)}");
    }

    /**
     * `$this < $arg`, or driver-specific subquery comparison.
     *
     * - MySQL: `$this < ALL ($arg)` or `$this < ANY ($arg)`
     * - SQLite:
     *      - ALL: `NOT EXISTS (... WHERE $this >= $arg[0])`
     *      - ANY: `EXISTS (... WHERE $this < $arg[0])`
     *
     * @param number|string|Select $arg
     * @param string $multi `ALL|ANY`
     * @return Predicate
     */
    public function isLess ($arg, string $multi = 'ALL') {
        if ($arg instanceof Select) {
            switch ($this->db) {
                case 'sqlite':
                    /** @var Select $sub */
                    $sub = $this->db->factory(Select::class, $this->db, $arg, [$arg[0]]);
                    switch ($multi) {
                        case 'ANY':
                            return $sub->where("{$this} < {$arg[0]}")->isNotEmpty();
                        default:
                            return $sub->where("{$this} >= {$arg[0]}")->isEmpty();
                    }
                default:
                    return $this->db->factory(Predicate::class, "{$this} < {$multi} ({$arg->toSql()})");
            }
        }
        return $this->db->factory(Predicate::class, "{$this} < {$this->db->quote($arg)}");
    }

    /**
     * `$this <= $arg`, or driver-specific subquery comparison.
     *
     * - MySQL: `$this <= ALL ($arg)` or `$this <= ANY ($arg)`
     * - SQLite:
     *      - ALL: `NOT EXISTS (... WHERE $this > $arg[0])`
     *      - ANY: `EXISTS (... WHERE $this <= $arg[0])`
     *
     * @param number|string|Select $arg
     * @param string $multi `ALL|ANY`
     * @return Predicate
     */
    public function isLessOrEqual ($arg, string $multi = 'ALL') {
        if ($arg instanceof Select) {
            switch ($this->db) {
                case 'sqlite':
                    /** @var Select $sub */
                    $sub = $this->db->factory(Select::class, $this->db, $arg, [$arg[0]]);
                    switch ($multi) {
                        case 'ANY':
                            return $sub->where("{$this} <= {$arg[0]}")->isNotEmpty();
                        default:
                            return $sub->where("{$this} > {$arg[0]}")->isEmpty();
                    }
                default:
                    return $this->db->factory(Predicate::class, "{$this} <= {$multi} ({$arg->toSql()})");
            }
        }
        return $this->db->factory(Predicate::class, "{$this} <= {$this->db->quote($arg)}");
    }

    /**
     * `$this LIKE $pattern`
     *
     * @param string $pattern
     * @return Predicate
     */
    public function isLike (string $pattern) {
        $pattern = $this->db->quote($pattern);
        return $this->db->factory(Predicate::class, "{$this} LIKE {$pattern}");
    }

    /**
     * Null-safe inequality.
     *
     * @param null|bool|number|string|Select $arg
     * @return Predicate
     */
    public function isNot ($arg) {
        return $this->is($arg)->not();
    }

    /**
     * `$this NOT BETWEEN $min AND $max` (inclusive)
     *
     * @param number $min
     * @param number $max
     * @return Predicate
     */
    public function isNotBetween ($min, $max) {
        $min = $this->db->quote($min);
        $max = $this->db->quote($max);
        return $this->db->factory(Predicate::class, "{$this} NOT BETWEEN {$min} AND {$max}");
    }

    /**
     * `$this <> $arg` or `$this NOT IN ($arg)`
     *
     * @param bool|number|string|array|Select $arg
     * @return Predicate
     */
    public function isNotEqual ($arg) {
        if ($arg instanceof Select) {
            return $this->db->factory(Predicate::class, "{$this} NOT IN ({$arg->toSql()})");
        }
        if (is_array($arg)) {
            return $this->db->factory(Predicate::class, "{$this} NOT IN ({$this->db->quoteList($arg)})");
        }
        return $this->db->factory(Predicate::class, "{$this} <> {$this->db->quote($arg)}");
    }

    /**
     * `$this NOT LIKE $pattern`
     *
     * @param string $pattern
     * @return Predicate
     */
    public function isNotLike (string $pattern) {
        $pattern = $this->db->quote($pattern);
        return $this->db->factory(Predicate::class, "{$this} NOT LIKE {$pattern}");
    }

    /**
     * `$this IS NOT NULL`
     *
     * @return Predicate
     */
    public function isNotNull () {
        return $this->db->factory(Predicate::class, "{$this} IS NOT NULL");
    }

    /**
     * `$this NOT REGEXP $pattern`
     *
     * @param string $pattern
     * @return Predicate
     */
    public function isNotRegExp (string $pattern) {
        $pattern = $this->db->quote($pattern);
        return $this->db->factory(Predicate::class, "{$this} NOT REGEXP {$pattern}");
    }

    /**
     * `$this IS NULL`
     *
     * @return Predicate
     */
    public function isNull () {
        return $this->db->factory(Predicate::class, "{$this} IS NULL");
    }

    /**
     * `$this REGEXP $pattern`
     *
     * @param string $pattern
     * @return Predicate
     */
    public function isRegExp (string $pattern) {
        $pattern = $this->db->quote($pattern);
        return $this->db->factory(Predicate::class, "{$this} REGEXP {$pattern}");
    }

    /**
     * `CASE $this ... END`
     *
     * > :warning: If `$values` are given, the keys are quoted as literal values.
     * > Omit `$values` and use {@link Choice::when()} if you need expressions for the `WHEN` clause.
     *
     * @param array $values `[when => then]`
     * @return Choice
     */
    public function switch (array $values = []) {
        return $this->db->factory(Choice::class, $this->db, "{$this}", $values);
    }
}