<?php

namespace Helix\DB;

use Helix\DB;

/**
 * Installs a magic static factory, where the signature can be changed to match the constructor,
 * since it's just an annotation.
 *
 * @method static static factory(DB $db, ...$args)
 * @internal
 */
trait FactoryTrait {

    /**
     * @param string $ignored
     * @param array $args The first argument must be a {@link DB} instance.
     * @return static
     */
    public static function __callStatic (string $ignored, array $args) {
        /** @var DB $db */
        $db = $args[0];
        unset($args[0]);
        return $db->factory(static::class, ...$args);
    }
}