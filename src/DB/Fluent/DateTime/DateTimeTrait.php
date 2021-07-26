<?php

namespace Helix\DB\Fluent\DateTime;

use Helix\DB\Fluent\Value\ValueTrait;

/**
 * Date-time expression manipulation.
 *
 * @see https://sqlite.org/lang_datefunc.html
 * @see https://dev.mysql.com/doc/refman/8.0/en/date-and-time-functions.html#function_date-format
 */
trait DateTimeTrait
{

    use DateTimeAddTrait;
    use DateTimeDiffTrait;
    use DateTimeSubTrait;
    use ValueTrait;
}
