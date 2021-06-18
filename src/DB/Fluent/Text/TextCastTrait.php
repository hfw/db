<?php

namespace Helix\DB\Fluent\Text;

use Helix\DB\Fluent\AbstractTrait;
use Helix\DB\Fluent\Text;

/**
 * Further manipulate the expression as a character string.
 */
trait TextCastTrait
{

    use AbstractTrait;

    /**
     * Casts the expression as a character string.
     *
     * @return Text
     */
    public function toText()
    {
        if ($this->db->isSQLite()) {
            return Text::factory($this->db, "CAST({$this} AS TEXT)");
        }
        return Text::factory($this->db, "CAST({$this} AS CHAR)");
    }
}
