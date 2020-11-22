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
     * @return Numeric
     */
    public function toFloat () {
        switch ($this->db) {
            case 'sqlite':
                return $this->db->factory(Numeric::class, $this->db, "CAST({$this} AS REAL)");
            default:
                return $this->db->factory(Numeric::class, $this->db, "({$this} + 0)");
        }
    }

    /**
     * Casts the expression to a signed integer.
     *
     * @return Numeric
     */
    public function toInt () {
        switch ($this->db) {
            case 'sqlite':
                return $this->db->factory(Numeric::class, $this->db, "CAST({$this} AS INTEGER)");
            default:
                return $this->db->factory(Numeric::class, $this->db, "CAST({$this} AS SIGNED)");
        }
    }
}