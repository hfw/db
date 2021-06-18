<?php

namespace Helix\DB\Fluent;

use Helix\DB\Fluent\DateTime\DateTimeCastTrait;
use Helix\DB\Fluent\Num\NumCastFloatTrait;
use Helix\DB\Fluent\Num\NumCastIntTrait;
use Helix\DB\Fluent\Text\TextCastTrait;
use Helix\DB\Fluent\Value\ValueTrait;

/**
 * A typeless value expression, which can be cast to any type.
 */
class Value extends Expression implements ValueInterface
{

    use ValueTrait;
    use DateTimeCastTrait;
    use NumCastFloatTrait;
    use NumCastIntTrait;
    use TextCastTrait;
}
