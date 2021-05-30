<?php

namespace Helix\DB\SQL;

/**
 * Represents a logical expression that will evaluate as boolean.
 */
class Predicate extends Expression implements ValueInterface {

    /**
     * `NOT($this)`
     *
     * @return static
     */
    public function not () {
        return static::factory($this->db, "NOT({$this})");
    }

}