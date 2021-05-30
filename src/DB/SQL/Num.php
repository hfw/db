<?php

namespace Helix\DB\SQL;

/**
 * Represents a numeric expression. Produces various transformations.
 */
class Num extends Value {

    use NumTrait;

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