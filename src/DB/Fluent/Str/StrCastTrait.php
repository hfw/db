<?php

namespace Helix\DB\Fluent\Str;

use Helix\DB\Fluent\AbstractTrait;
use Helix\DB\Fluent\Str;

/**
 * Further manipulate the expression as a character string.
 */
trait StrCastTrait
{

    use AbstractTrait;

    /**
     * Casts the expression as a character string.
     *
     * @return Str
     */
    public function toStr()
    {
        return Str::fromFormat($this->db, [
            'mysql' => "CAST(%s AS CHAR)",
            'sqlite' => "CAST(%s AS TEXT)"
        ], $this);
    }
}
