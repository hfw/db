<?php

namespace Helix\DB\Fluent;

use Helix\DB\Fluent\Num\NumTrait;
use Helix\DB\Fluent\Str\StrCastTrait;

/**
 * A numeric expression.
 */
class Num extends Expression implements ValueInterface
{

    use NumTrait;
    use StrCastTrait;
}
