<?php

namespace Helix\DB\Fluent;

use Helix\DB;
use Helix\DB\FactoryTrait;

/**
 * Create an expression using driver-appropriate SQL.
 *
 * @internal
 */
trait FactoryFormatTrait
{

    use FactoryTrait;

    /**
     * Creates an expression using driver-appropriate SQL.
     *
     * @param DB $db
     * @param string[] $formats Formats for `sprintf()`, keyed by driver.
     * @param ...$args
     * @return static
     */
    public static function fromFormat(DB $db, array $formats, ...$args)
    {
        return static::factory($db, sprintf($formats[$db->getDriver()], ...$args));
    }

}
