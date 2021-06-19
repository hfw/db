<?php

namespace Helix\DB\Fluent;

/**
 * A logical expression that evaluates to a boolean.
 */
class Predicate extends Expression implements ValueInterface
{

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
