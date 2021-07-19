<?php

namespace Helix\DB\Fluent;

use Helix\DB;
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

    /**
     * An expression for the current date and time.
     *
     * @param DB $db
     * @return static
     */
    public static function now(DB $db)
    {
        return static::fromFormat($db, [
            'mysql' => "NOW()",
            'sqlite' => "DATETIME('now')"
        ]);
    }
}
