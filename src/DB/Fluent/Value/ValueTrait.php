<?php

namespace Helix\DB\Fluent\Value;

use Helix\DB\Fluent\AbstractTrait;
use Helix\DB\Fluent\Value;

/**
 * Type-agnostic functions.
 */
trait ValueTrait
{

    use AggregateTrait;
    use ComparisonTrait;

    /**
     * `COALESCE($this, ...$values)`
     *
     * @param scalar[] $values
     * @return Value
     */
    public function coalesce(array $values)
    {
        array_unshift($values, $this);
        $values = $this->db->quoteList($values);
        return Value::factory($this->db, "COALESCE({$values})");
    }
}
