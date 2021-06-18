<?php

namespace Helix\DB\Fluent\Num;

use Helix\DB\Fluent\AbstractTrait;
use Helix\DB\Fluent\Num;

/**
 * Further manipulate the expression as a float.
 */
trait NumCastFloatTrait
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
}
