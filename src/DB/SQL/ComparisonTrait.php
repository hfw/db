<?php

namespace Helix\DB\SQL;

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

    use AbstractTrait;

    /**
     * Null-safe equality.
     *
     * - Mysql: `$this <=> $arg`, or `$this <=> ANY ($arg)`
     * - SQLite: `$this IS $arg`, or `EXISTS (... WHERE $this IS $arg[0])`
     *
     * @param null|scalar|Select $arg
     * @return Predicate
     */
    public function is ($arg): Predicate {
        if ($arg instanceof Select) {
            if ($this->db->isSQLite()) {
                return Select::factory($this->db, $arg, [$arg[0]])
                    ->where("{$this} IS {$arg[0]}")
                    ->isNotEmpty();
            }
            return Predicate::factory($this->db, "{$this} <=> ANY ({$arg->toSql()})");
        }
        if ($arg === null or is_bool($arg)) {
            $arg = ['' => 'NULL', 1 => 'TRUE', 0 => 'FALSE'][$arg];
        }
        else {
            $arg = $this->db->quote($arg);
        }
        if ($this->db->isMySQL()) {
            return Predicate::factory($this->db, "{$this} <=> {$arg}");
        }
        return Predicate::factory($this->db, "{$this} IS {$arg}");
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
        return Predicate::factory($this->db, "{$this} BETWEEN {$min} AND {$max}");
    }

    /**
     * `$this = $arg` or `$this IN ($arg)`
     *
     * @param scalar|array|Select $arg
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
        return Predicate::factory($this->db, "{$this} IS FALSE");
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
            if ($this->db->isSQLite()) {
                $sub = Select::factory($this->db, $arg, [$arg[0]]);
                if ($multi === 'ANY') {
                    return $sub->where("{$this} > {$arg[0]}")->isNotEmpty();
                }
                return $sub->where("{$this} <= {$arg[0]}")->isEmpty();
            }
            return Predicate::factory($this->db, "{$this} > {$multi} ({$arg->toSql()})");
        }
        return Predicate::factory($this->db, "{$this} > {$this->db->quote($arg)}");
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
            if ($this->db->isSQLite()) {
                $sub = Select::factory($this->db, $arg, [$arg[0]]);
                if ($multi === 'ANY') {
                    return $sub->where("{$this} >= {$arg[0]}")->isNotEmpty();
                }
                return $sub->where("{$this} < {$arg[0]}")->isEmpty();
            }
            return Predicate::factory($this->db, "{$this} >= {$multi} ({$arg->toSql()})");
        }
        return Predicate::factory($this->db, "{$this} >= {$this->db->quote($arg)}");
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
            if ($this->db->isSQLite()) {
                $sub = Select::factory($this->db, $arg, [$arg[0]]);
                if ($multi === 'ANY') {
                    return $sub->where("{$this} < {$arg[0]}")->isNotEmpty();
                }
                return $sub->where("{$this} >= {$arg[0]}")->isEmpty();
            }
            return Predicate::factory($this->db, "{$this} < {$multi} ({$arg->toSql()})");
        }
        return Predicate::factory($this->db, "{$this} < {$this->db->quote($arg)}");
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
            if ($this->db->isSQLite()) {
                $sub = Select::factory($this->db, $arg, [$arg[0]]);
                if ($multi === 'ANY') {
                    return $sub->where("{$this} <= {$arg[0]}")->isNotEmpty();
                }
                return $sub->where("{$this} > {$arg[0]}")->isEmpty();
            }
            return Predicate::factory($this->db, "{$this} <= {$multi} ({$arg->toSql()})");
        }
        return Predicate::factory($this->db, "{$this} <= {$this->db->quote($arg)}");
    }

    /**
     * `$this LIKE $pattern`
     *
     * @param string $pattern
     * @return Predicate
     */
    public function isLike (string $pattern) {
        $pattern = $this->db->quote($pattern);
        return Predicate::factory($this->db, "{$this} LIKE {$pattern}");
    }

    /**
     * Null-safe inequality.
     *
     * @param null|scalar|Select $arg
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
        return Predicate::factory($this->db, "{$this} NOT BETWEEN {$min} AND {$max}");
    }

    /**
     * `$this <> $arg` or `$this NOT IN ($arg)`
     *
     * @param scalar|array|Select $arg
     * @return Predicate
     */
    public function isNotEqual ($arg) {
        if ($arg instanceof Select) {
            return Predicate::factory($this->db, "{$this} NOT IN ({$arg->toSql()})");
        }
        if (is_array($arg)) {
            return Predicate::factory($this->db, "{$this} NOT IN ({$this->db->quoteList($arg)})");
        }
        return Predicate::factory($this->db, "{$this} <> {$this->db->quote($arg)}");
    }

    /**
     * `$this NOT LIKE $pattern`
     *
     * @param string $pattern
     * @return Predicate
     */
    public function isNotLike (string $pattern) {
        $pattern = $this->db->quote($pattern);
        return Predicate::factory($this->db, "{$this} NOT LIKE {$pattern}");
    }

    /**
     * `$this IS NOT NULL`
     *
     * @return Predicate
     */
    public function isNotNull () {
        return Predicate::factory($this->db, "{$this} IS NOT NULL");
    }

    /**
     * `$this NOT REGEXP $pattern`
     *
     * @param string $pattern
     * @return Predicate
     */
    public function isNotRegExp (string $pattern) {
        $pattern = $this->db->quote($pattern);
        return Predicate::factory($this->db, "{$this} NOT REGEXP {$pattern}");
    }

    /**
     * `$this IS NULL`
     *
     * @return Predicate
     */
    public function isNull () {
        return Predicate::factory($this->db, "{$this} IS NULL");
    }

    /**
     * `$this REGEXP $pattern`
     *
     * @param string $pattern
     * @return Predicate
     */
    public function isRegExp (string $pattern) {
        $pattern = $this->db->quote($pattern);
        return Predicate::factory($this->db, "{$this} REGEXP {$pattern}");
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
        return Choice::factory($this->db, "{$this}", $values);
    }
}