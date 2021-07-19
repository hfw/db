<?php

namespace Helix\DB\Fluent;

use Helix\DB;
use Helix\DB\Fluent\Num\NumTrait;
use Helix\DB\Fluent\Str\StrCastTrait;

/**
 * A numeric expression.
 */
class Num extends Expression implements ValueInterface
{

    use NumTrait;
    use FactoryFormatTrait;
    use StrCastTrait;

    /**
     * `PI()`
     *
     * @param DB $db
     * @return static
     */
    public static function pi(DB $db)
    {
        return static::factory($db, "PI()");
    }
}
