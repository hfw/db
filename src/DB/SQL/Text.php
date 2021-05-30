<?php

namespace Helix\DB\SQL;

/**
 * Represents a text expression. Produces various transformations.
 */
class Text extends Value {

    use TextTrait;

    /**
     * Casts the expression to a floating point number.
     *
     * @return Num
     */
    public function toFloat () {
        if ($this->db->isSQLite()) {
            return Num::factory($this->db, "CAST({$this} AS REAL)");
        }
        return Num::factory($this->db, "({$this} + 0)");
    }

    /**
     * Casts the expression to a signed integer.
     *
     * @return Num
     */
    public function toInt () {
        if ($this->db->isSQLite()) {
            return Num::factory($this->db, "CAST({$this} AS INTEGER)");
        }
        return Num::factory($this->db, "CAST({$this} AS SIGNED)");
    }
}