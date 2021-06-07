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
     * `CONV($this,$from,$to)`
     *
     * @param int $from
     * @param int $to
     * @return Text
     */
    public function toBase (int $from, int $to) {
        return Text::factory($this->db, "CONV({$this},{$from},{$to})");
    }

    /**
     * `CONV($this,$from,10)`
     *
     * @param int $from
     * @return Num
     */
    public function toBase10 (int $from) {
        return Num::factory($this->db, "CONV({$this},{$from},10)");
    }

    /**
     * `CONV($this,$from,16)`
     *
     * This is similar to {@link TextTrait::hex()} except you can specify the starting base.
     *
     * @param int $from
     * @return Text
     */
    public function toBase16 (int $from) {
        return $this->toBase($from, 16);
    }

    /**
     * `CONV($this,$from,2)`
     *
     * @param int $from
     * @return Text
     */
    public function toBase2 (int $from) {
        return $this->toBase($from, 2);
    }

    /**
     * `CONV($this,$from,8)`
     *
     * @param int $from
     * @return Text
     */
    public function toBase8 (int $from) {
        return $this->toBase($from, 8);
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