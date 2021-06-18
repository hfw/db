<?php

namespace Helix\DB\Fluent;

use Helix\DB\Fluent\Num\NumTrait;
use Helix\DB\Fluent\Text\TextCastTrait;

/**
 * Represents a numeric expression. Produces various transformations.
 */
class Num extends Expression implements ValueInterface
{

    use NumTrait;
    use TextCastTrait;
}
