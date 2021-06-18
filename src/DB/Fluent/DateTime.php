<?php

namespace Helix\DB\Fluent;

use Helix\DB\Fluent\DateTime\DateTimeTrait;
use Helix\DB\Fluent\Text\TextCastTrait;

/**
 * Represents a date-time expression.
 */
class DateTime extends Expression implements ValueInterface {

    use DateTimeTrait;
    use TextCastTrait;
}
