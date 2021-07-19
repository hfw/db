<?php

namespace Helix\DB\Fluent\Num;

use Helix\DB\Fluent\AbstractTrait;
use Helix\DB\Fluent\Str;

/**
 * Further manipulate the expression in a different numeric base.
 *
 * Since converting to base 10 only applies to character string expressions,
 * its method can be found at {@link \Helix\DB\Fluent\Str\StrTrait::toBase10()}
 */
trait BaseConversionTrait
{

    use AbstractTrait;

    /**
     * Convert between arbitrary bases.
     *
     * `CONV($this,$from,$to)`
     *
     * @param int $from
     * @param int $to
     * @return Str
     */
    public function toBase(int $from, int $to)
    {
        return Str::factory($this->db, "CONV({$this},{$from},{$to})");
    }

    /**
     * Convert from an arbitrary base to base 16.
     *
     * `CONV($this,$from,16)`
     *
     * @param int $from
     * @return Str
     */
    public function toBase16(int $from = 10)
    {
        return $this->toBase($from, 16);
    }

    /**
     * Convert from an arbitrary base to base 2.
     *
     * `CONV($this,$from,2)`
     *
     * @param int $from
     * @return Str
     */
    public function toBase2(int $from = 10)
    {
        return $this->toBase($from, 2);
    }

    /**
     * Convert from an arbitrary base to base 8.
     *
     * `CONV($this,$from,8)`
     *
     * @param int $from
     * @return Str
     */
    public function toBase8(int $from = 10)
    {
        return $this->toBase($from, 8);
    }
}
