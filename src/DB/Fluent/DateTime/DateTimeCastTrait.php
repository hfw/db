<?php

namespace Helix\DB\Fluent\DateTime;

use Helix\DB\Fluent\AbstractTrait;
use Helix\DB\Fluent\DateTime;

trait DateTimeCastTrait
{

    use AbstractTrait;

    /**
     * Interpret the expression as a datetime.
     *
     * > Warning: If the expression is in the local timezone
     * > you should chain this with {@link DateTime::toUtc()}
     *
     * SQLite:
     * - The expression's value must conform to one of any `time-value` formats.
     * - https://www.sqlite.org/lang_datefunc.html
     *
     * MySQL:
     * - The expression's value must conform to `YYYY-MM-DD hh:mm:ss`
     *
     * @return DateTime
     */
    public function toDateTime()
    {
        return DateTime::factory($this->db, $this);
    }
}