<?php

namespace Helix\DB\Fluent;

use Helix\DB\Fluent\DateTime\DateTimeTrait;
use Helix\DB\Fluent\Str\StrCastTrait;

/**
 * A date-time expression.
 */
class DateTime extends Expression implements ValueInterface
{

    use DateTimeTrait;
    use FactoryFormatTrait;
    use StrCastTrait;
}
