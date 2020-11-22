<?php

namespace Helix\DB\SQL;

/**
 * Represents a logical expression that will evaluate as boolean.
 */
class Predicate extends Expression implements ValueInterface {

    /**
     * `NOT($this)`
     *
     * @return Predicate
     */
    public function not () {
        return new static("NOT({$this})");
    }

}