<?php

namespace Helix\DB\Fluent\Value;

use Closure;
use Helix\DB\EntityInterface;
use Helix\DB\Fluent\AbstractTrait;
use Helix\DB\Fluent\Choice;
use Helix\DB\Fluent\Predicate;
use Helix\DB\Fluent\ValueInterface;
use Helix\DB\Select;

/**
 * Comparative functions.
 *
 * Because SQLite doesn't have the `ANY`/`ALL` comparison operators,
 * subqueries are instead nested and correlated using `EXISTS` or `NOT EXISTS`,
 * which requires the first column of the subquery to have a name or alias so it's referable.
 */
trait ComparisonTrait
{

    use AbstractTrait;

    /**
     * Relational comparison helper.
     *
     * @param number|string|Select $arg
     * @param string $oper Relational operator
     * @param string $multi `ALL|ANY`
     * @return Predicate
     * @internal
     */
    protected function _isRelational($arg, string $oper, string $multi)
    {
        static $inverse = [
            '<' => '>=',
            '<=' => '>',
            '>=' => '<',
            '>' => '<='
        ];
        if ($arg instanceof Select) {
            if ($this->db->isSQLite()) {
                $sub = Select::factory($this->db, $arg, [$arg[0]]);
                if ($multi === 'ANY') {
                    return $sub->where("{$this} {$oper} {$arg[0]}")->isNotEmpty();
                }
                return $sub->where("{$this} {$inverse[$oper]} {$arg[0]}")->isEmpty();
            }
            return Predicate::factory($this->db, "{$this} {$oper} {$multi} ({$arg->toSql()})");
        }
        return Predicate::factory($this->db, "{$this} {$oper} {$this->db->quote($arg)}");
    }

    /**
     * Null-safe type-strict equality.
     *
     * - Mysql: `$this <=> $arg`, or `$this <=> ANY ($arg)`
     * - SQLite: `$this IS $arg`, or `EXISTS (... WHERE $this IS $arg[0])`
     *
     * @param null|scalar|EntityInterface|Select|ValueInterface $arg
     * @return Predicate
     */
    public function is($arg): Predicate
    {
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
        } else {
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
    public function isBetween($min, $max)
    {
        $min = $this->db->quote($min);
        $max = $this->db->quote($max);
        return Predicate::factory($this->db, "{$this} BETWEEN {$min} AND {$max}");
    }

    /**
     * See {@link Predicate::match()}
     *
     * @param null|scalar|array|Closure|EntityInterface|Select|ValueInterface $arg
     * @return Predicate
     */
    public function isEqual($arg)
    {
        return Predicate::match($this->db, $this, $arg);
    }

    /**
     * `$this IS FALSE`
     *
     * @return Predicate
     */
    public function isFalse()
    {
        return Predicate::factory($this->db, "{$this} IS FALSE");
    }

    /**
     * `$this > $arg`, or driver-appropriate `$this > ALL (SELECT ...)`
     *
     * @param number|string|Select $arg
     * @return Predicate
     */
    public function isGt($arg)
    {
        return $this->_isRelational($arg, '>', 'ALL');
    }

    /**
     * Driver-appropriate `$this > ANY (SELECT ...)`
     *
     * @param Select $select
     * @return Predicate
     */
    public function isGtAny(Select $select)
    {
        return $this->_isRelational($select, '>', 'ANY');
    }

    /**
     * `$this >= $arg`, or driver-appropriate `$this >= ALL (SELECT ...)`
     *
     * @param number|string|Select $arg
     * @return Predicate
     */
    public function isGte($arg)
    {
        return $this->_isRelational($arg, '>=', 'ALL');
    }

    /**
     * Driver-appropriate `$this >= ANY (SELECT ...)`
     *
     * @param Select $select
     * @return Predicate
     */
    public function isGteAny(Select $select)
    {
        return $this->_isRelational($select, '>=', 'ANY');
    }

    /**
     * `$this LIKE $pattern`
     *
     * @param string $pattern
     * @return Predicate
     */
    public function isLike(string $pattern)
    {
        $pattern = $this->db->quote($pattern);
        return Predicate::factory($this->db, "{$this} LIKE {$pattern}");
    }

    /**
     * `$this < $arg`, or driver-appropriate `$this < ALL (SELECT ...)`
     *
     * @param number|string|Select $arg
     * @return Predicate
     */
    public function isLt($arg)
    {
        return $this->_isRelational($arg, '<', 'ALL');
    }

    /**
     * Driver-appropriate `$this < ANY (SELECT ...)`
     *
     * @param Select $select
     * @return Predicate
     */
    public function isLtAny(Select $select)
    {
        return $this->_isRelational($select, '<', 'ANY');
    }

    /**
     * `$this <= $arg`, or driver-appropriate `$this <= ALL (SELECT ...)`
     *
     * @param number|string|Select $arg
     * @return Predicate
     */
    public function isLte($arg)
    {
        return $this->_isRelational($arg, '<=', 'ALL');
    }

    /**
     * Driver-appropriate `$this <= ANY (SELECT ...)`
     *
     * @param Select $select
     * @return Predicate
     */
    public function isLteAny(Select $select)
    {
        return $this->_isRelational($select, '<=', 'ANY');
    }

    /**
     * Null-safe type-strict inequality.
     *
     * @param null|scalar|EntityInterface|Select|ValueInterface $arg
     * @return Predicate
     */
    public function isNot($arg)
    {
        return $this->is($arg)->lNot();
    }

    /**
     * `$this NOT BETWEEN $min AND $max` (inclusive)
     *
     * @param number $min
     * @param number $max
     * @return Predicate
     */
    public function isNotBetween($min, $max)
    {
        $min = $this->db->quote($min);
        $max = $this->db->quote($max);
        return Predicate::factory($this->db, "{$this} NOT BETWEEN {$min} AND {$max}");
    }

    /**
     * `$this <> $arg` or `$this NOT IN ($arg)`
     *
     * @param null|scalar|array|EntityInterface|Select|ValueInterface $arg
     * @return Predicate
     */
    public function isNotEqual($arg)
    {
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
    public function isNotLike(string $pattern)
    {
        $pattern = $this->db->quote($pattern);
        return Predicate::factory($this->db, "{$this} NOT LIKE {$pattern}");
    }

    /**
     * `$this IS NOT NULL`
     *
     * @return Predicate
     */
    public function isNotNull()
    {
        return Predicate::factory($this->db, "{$this} IS NOT NULL");
    }

    /**
     * `$this NOT REGEXP $pattern`
     *
     * @param string $pattern
     * @return Predicate
     */
    public function isNotRegExp(string $pattern)
    {
        $pattern = $this->db->quote($pattern);
        return Predicate::factory($this->db, "{$this} NOT REGEXP {$pattern}");
    }

    /**
     * `$this IS NULL`
     *
     * @return Predicate
     */
    public function isNull()
    {
        return Predicate::factory($this->db, "{$this} IS NULL");
    }

    /**
     * `$this REGEXP $pattern`
     *
     * @param string $pattern
     * @return Predicate
     */
    public function isRegExp(string $pattern)
    {
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
    public function switch(array $values = [])
    {
        return Choice::factory($this->db, "{$this}", $values);
    }
}
