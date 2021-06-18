<?php

namespace Helix\DB\Fluent;

use Helix\DB\Fluent\DateTime\DateTimeCastTrait;
use Helix\DB\Fluent\Num\NumCastFloatTrait;
use Helix\DB\Fluent\Num\NumCastIntTrait;
use Helix\DB\Fluent\Text\TextTrait;

/**
 * A character string expression.
 */
class Text extends Expression implements ValueInterface
{

    use TextTrait;
    use DateTimeCastTrait;
    use NumCastFloatTrait;
    use NumCastIntTrait;
}
