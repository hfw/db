<?php

namespace Helix\DB\Fluent\Num;

use Helix\DB\Fluent\AbstractTrait;
use Helix\DB\Fluent\Num;

trait NumCastTrait
{

    use AbstractTrait;

    /**
     * Casts the expression as a floating point number.
     *
     * @return Num
     */
    public function toFloat()
    {
        if ($this->db->isSQLite()) {
            return Num::factory($this->db, "CAST({$this} AS REAL)");
        }
        return Num::factory($this->db, "({$this} + 0)");
    }

    /**
     * Casts the expression as a signed integer.
     *
     * @return Num
     */
    public function toInt()
    {
        if ($this->db->isSQLite()) {
            return Num::factory($this->db, "CAST({$this} AS INTEGER)");
        }
        return Num::factory($this->db, "CAST({$this} AS SIGNED)");
    }
}
