<?php

namespace Helix\DB\Fluent\Num;

use Helix\DB\Fluent\AbstractTrait;
use Helix\DB\Fluent\Num;

/**
 * Further manipulate the expression as an integer.
 */
trait NumCastIntTrait
{

    use AbstractTrait;

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
