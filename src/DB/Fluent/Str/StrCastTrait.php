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
        if ($this->db->isSQLite()) {
            return Str::factory($this->db, "CAST({$this} AS TEXT)");
        }
        return Str::factory($this->db, "CAST({$this} AS CHAR)");
    }
}
