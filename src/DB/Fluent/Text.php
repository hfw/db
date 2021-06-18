<?php

namespace Helix\DB\Fluent;

use Helix\DB\Fluent\DateTime\DateTimeCastTrait;
use Helix\DB\Fluent\Num\NumCastTrait;
use Helix\DB\Fluent\Text\TextTrait;

/**
 * Represents a text expression. Produces various transformations.
 */
class Text extends Expression implements ValueInterface
{

    use TextTrait;
    use DateTimeCastTrait;
    use NumCastTrait;
}
