<?php

namespace Helix\DB\Fluent;

use Helix\DB\Fluent\DateTime\DateTimeCastTrait;
use Helix\DB\Fluent\Num\NumCastFloatTrait;
use Helix\DB\Fluent\Num\NumCastIntTrait;
use Helix\DB\Fluent\Str\StrTrait;

/**
 * A character string expression.
 */
class Str extends Expression implements ValueInterface
{

    use StrTrait;
    use DateTimeCastTrait;
    use NumCastFloatTrait;
    use NumCastIntTrait;
}
