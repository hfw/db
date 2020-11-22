<?php

namespace Helix\DB;

use Countable;
use Helix\DB\SQL\Expression;
use Helix\DB\SQL\ExpressionInterface;

/**
 * Static helper for building SQL.
 *
 * The methods here are driver agnostic, and do not quote values.
 */
class SQL {

    /**
     * Returns an array of `?` placeholders.
     *
     * @param int|array|Countable $count
     * @return ExpressionInterface[]
     */
    public static function marks ($count): array {
        if (is_array($count) or $count instanceof Countable) {
            $count = count($count);
        }
        return array_fill(0, $count, new Expression('?'));
    }

    /**
     * Converts an array of columns to `:named` placeholders for prepared queries.
     *
     * Qualified columns are slotted as `qualifier__column` (two underscores).
     *
     * @param string[] $columns
     * @return string[] `["column" => ":column"]`
     */
    public static function slots (array $columns): array {
        $slots = [];
        foreach ($columns as $column) {
            $slots[(string)$column] = ':' . str_replace('.', '__', $column);
        }
        return $slots;
    }

    /**
     * @param string[] $columns
     * @return string[] `["column" => "column=:column"]`
     */
    public static function slotsEqual (array $columns): array {
        $slots = static::slots($columns);
        foreach ($slots as $column => $slot) {
            $slots[$column] = "{$column} = {$slot}";
        }
        return $slots;
    }

    final private function __construct () { }
}