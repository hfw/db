<?php

namespace Helix\DB\Fluent;

use Closure;
use Helix\DB;
use Helix\DB\EntityInterface;
use Helix\DB\Select;

/**
 * A logical expression that evaluates to a boolean.
 */
class Predicate extends Expression implements ValueInterface
{

    /**
     * Null-safe equality {@link Predicate} from mixed arguments.
     *
     * If `$a` is an integer (enumerated item), returns `$b` as a {@link Predicate}
     *
     * If `$b` is a closure, returns from `$b($a, DB $this)`
     *
     * If `$b` is an {@link EntityInterface}, the ID is used.
     *
     * If `$b` is an array, returns `$a IN (...quoted $b)`
     *
     * If `$b` is a {@link Select}, returns `$a IN ($b->toSql())`
     *
     * Otherwise predicates upon `$a = quoted $b`
     *
     * @param scalar $a
     * @param null|scalar|array|Closure|EntityInterface|Select|ValueInterface $b
     * @return static
     */
    public static function match(DB $db, $a, $b): Predicate
    {
        if ($b instanceof Closure) {
            return $b->__invoke($a, $db);
        }
        if (is_int($a)) {
            return static::factory($db, $b);
        }
        if (is_array($b)) {
            return static::factory($db, "{$a} IN ({$db->quoteList($b)})");
        }
        if ($b instanceof Select) {
            return static::factory($db, "{$a} IN ({$b->toSql()})");
        }
        if ($b === null) {
            return static::factory($db, "{$a} IS NULL");
        }
        return static::factory($db, "{$a} = {$db->quote($b)}");
    }

    /**
     * Logical `AND`
     *
     * `($this AND ...)`
     *
     * @param string ...$predicates
     * @return Predicate
     */
    public function lAnd(string ...$predicates)
    {
        array_unshift($predicates, $this);
        return static::factory($this->db, sprintf('(%s)', implode(' AND ', $predicates)));
    }

    /**
     * Logical `NOT`
     *
     * `NOT($this)`
     *
     * @return static
     */
    public function lNot()
    {
        return static::factory($this->db, "NOT({$this})");
    }

    /**
     * Logical `OR`
     *
     * `($this OR ...)`
     *
     * @param string ...$predicates
     * @return Predicate
     */
    public function lOr(string ...$predicates)
    {
        array_unshift($predicates, $this);
        return static::factory($this->db, sprintf('(%s)', implode(' OR ', $predicates)));
    }

}
