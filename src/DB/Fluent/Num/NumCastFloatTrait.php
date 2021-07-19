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
        return Num::fromFormat($this->db, [
            'mysql' => "(%s + 0)",
            'sqlite' => "CAST(%s AS REAL)"
        ], $this);
    }
}
