<?php

namespace Helix\DB\Fluent;

/**
 * A logical expression that evaluates to a boolean.
 */
class Predicate extends Expression implements ValueInterface
{

    /**
     * `NOT($this)`
     *
     * @return static
     */
    public function not()
    {
        return static::factory($this->db, "NOT({$this})");
    }

}
