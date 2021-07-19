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
        return Num::fromFormat($this->db, [
            'mysql' => "CAST(%s AS SIGNED)",
            'sqlite' => "CAST(%s AS INTEGER)"
        ], $this);
    }
}
