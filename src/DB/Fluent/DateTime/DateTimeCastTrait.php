<?php

namespace Helix\DB\Fluent\DateTime;

use Helix\DB\Fluent\AbstractTrait;
use Helix\DB\Fluent\DateTime;

/**
 * Further manipulate the expression as a date-time.
 */
trait DateTimeCastTrait
{

    use AbstractTrait;

    /**
     * Interpret the expression as a datetime.
     *
     * > Warning: If the expression's value is in the local timezone
     * > you should chain this with {@link DateTime::toUTC()}
     *
     * SQLite:
     * - The expression's value must conform to one of any `time-value` formats.
     * - https://www.sqlite.org/lang_datefunc.html
     *
     * MySQL:
     * - The expression's value must conform to `YYYY-MM-DD` or `YYYY-MM-DD hh:mm:ss`
     *
     * @return DateTime
     */
    public function toDateTime()
    {
        return DateTime::factory($this->db, $this);
    }
}
