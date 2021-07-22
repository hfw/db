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

    /**
     * `RAND()` float between `0` and `1`
     *
     * @param DB $db
     * @return static
     */
    public static function rand(DB $db)
    {
        return static::factory($db, "RAND()");
    }

    /**
     * {@link DateTime} from a Unix timestamp.
     *
     * The expression must be a non-negative integer.
     *
     * @return DateTime
     */
    public function toDateTime()
    {
        return DateTime::fromFormat($this->db, [
            'mysql' => "CAST(%s AT TIME ZONE 'UTC' AS DATETIME)",
            'sqlite' => "DATETIME(%s,'unixepoch')",
        ], $this);
    }
}
