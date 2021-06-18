<?php

namespace Helix\DB\Fluent;

use Helix\DB\Fluent\DateTime\DateTimeCastTrait;
use Helix\DB\Fluent\Num\NumCastTrait;
use Helix\DB\Fluent\Text\TextCastTrait;
use Helix\DB\Fluent\Value\ValueTrait;

/**
 * Represents a typeless value expression.
 */
class Value extends Expression implements ValueInterface {

    use ValueTrait;
    use DateTimeCastTrait;
    use NumCastTrait;
    use TextCastTrait;
}
