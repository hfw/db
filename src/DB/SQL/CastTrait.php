<?php

namespace Helix\DB\SQL;

trait CastTrait {

    use AbstractTrait;

    /**
     * `COALESCE($this, ...$values)`
     *
     * @param scalar[] $values
     * @return Value
     */
    public function coalesce (array $values) {
        array_unshift($values, $this);
        $values = $this->db->quoteList($values);
        return Value::factory($this->db, "COALESCE({$values})");
    }

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

    /**
     * Casts the expression to a character string.
     *
     * @return Text
     */
    public function toText () {
        if ($this->db->isSQLite()) {
            return Text::factory($this->db, "CAST({$this} AS TEXT)");
        }
        return Text::factory($this->db, "CAST({$this} AS CHAR)");
    }
}