<?php

namespace Helix\DB\Fluent\Num;

use Helix\DB\Fluent\Num;
use Helix\DB\Fluent\Predicate;
use Helix\DB\Fluent\Value\ValueTrait;
use Helix\DB\Fluent\ValueInterface;

/**
 * Produces numeric expressions for the instance.
 */
trait NumTrait
{

    use ValueTrait;
    use BaseConversionTrait;
    use NumCastTrait;

    /**
     * `ABS($this)`
     *
     * @return Num
     */
    public function abs()
    {
        return Num::factory($this->db, "ABS({$this})");
    }

    /**
     * `ACOS($this)`
     *
     * @return Num
     */
    public function acos()
    {
        return Num::factory($this->db, "ACOS({$this})");
    }

    /**
     * `($this + $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Num
     */
    public function add($arg)
    {
        $arg = $this->db->quote($arg);
        return Num::factory($this->db, "({$this} + {$arg})");
    }

    /**
     * `ASIN($this)`
     *
     * @return Num
     */
    public function asin()
    {
        return Num::factory($this->db, "ASIN({$this})");
    }

    /**
     * `ATAN($this)`
     *
     * @return Num
     */
    public function atan()
    {
        return Num::factory($this->db, "ATAN({$this})");
    }

    /**
     * `CEIL($this)`
     *
     * @return Num
     */
    public function ceil()
    {
        return Num::factory($this->db, "CEIL({$this})");
    }

    /**
     * `COS($this)`
     *
     * @return Num
     */
    public function cos()
    {
        return Num::factory($this->db, "COS({$this})");
    }

    /**
     * Radians to degrees.
     *
     * `DEGREES($this)`
     *
     * @return Num
     */
    public function degrees()
    {
        return Num::factory($this->db, "DEGREES({$this})");
    }

    /**
     * `($this / $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Num
     */
    public function div($arg)
    {
        $arg = $this->db->quote($arg);
        return Num::factory($this->db, "({$this} / {$arg})");
    }

    /**
     * Euler's constant raised to the power of the expression.
     *
     * `EXP($this)`
     *
     * @return Num
     */
    public function exp()
    {
        return Num::factory($this->db, "EXP({$this})");
    }

    /**
     * `FLOOR($this)`
     *
     * @return Num
     */
    public function floor()
    {
        return Num::factory($this->db, "FLOOR({$this})");
    }

    /**
     * `($this % 2) = 0`
     *
     * @return Predicate
     */
    public function isEven()
    {
        return Predicate::factory($this->db, "({$this} % 2) = 0");
    }

    /**
     * `$this < 0`
     *
     * @return Predicate
     */
    public function isNegative()
    {
        return Predicate::factory($this->db, "{$this} < 0");
    }

    /**
     * `($this % 2) <> 0`
     *
     * @return Predicate
     */
    public function isOdd()
    {
        return Predicate::factory($this->db, "({$this} % 2) <> 0");
    }

    /**
     * `$this > 0`
     *
     * @return Predicate
     */
    public function isPositive()
    {
        return Predicate::factory($this->db, "{$this} > 0");
    }

    /**
     * `LN($this)`
     *
     * @return Num
     */
    public function ln()
    {
        return Num::factory($this->db, "LN({$this})");
    }

    /**
     * `LOG($base,$this)`
     *
     * > Note: This is the cross-DBMS signature. PHP's built-in function has the reverse.
     *
     * @param float $base
     * @return Num
     */
    public function log(float $base)
    {
        return Num::factory($this->db, "LOG({$base},{$this})");
    }

    /**
     * `LOG10($this)`
     *
     * @return Num
     */
    public function log10()
    {
        return Num::factory($this->db, "LOG10({$this})");
    }

    /**
     * `LOG2($this)`
     *
     * @return Num
     */
    public function log2()
    {
        return Num::factory($this->db, "LOG2({$this})");
    }

    /**
     * `($this % $divisor)`
     *
     * @param float $divisor
     * @return Num
     */
    public function mod(float $divisor)
    {
        return Num::factory($this->db, "({$this} % {$divisor})");
    }

    /**
     * `($this * $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Num
     */
    public function mul($arg)
    {
        $arg = $this->db->quote($arg);
        return Num::factory($this->db, "({$this} * {$arg})");
    }

    /**
     * `POW($this,$exponent)`
     *
     * @param float $exponent
     * @return Num
     */
    public function pow(float $exponent)
    {
        return Num::factory($this->db, "POW({$this},{$exponent})");
    }

    /**
     * Degrees to radians.
     *
     * `RADIANS($this)`
     *
     * @return Num
     */
    public function radians()
    {
        return Num::factory($this->db, "RADIANS({$this})");
    }

    /**
     * `ROUND($this,$decimals)`
     *
     * @param int $decimals
     * @return Num
     */
    public function round(int $decimals = 0)
    {
        return Num::factory($this->db, "ROUND({$this},{$decimals})");
    }

    /**
     * `SIGN($this)`
     *
     * @return Num `-1`, `0`, `1`
     */
    public function sign()
    {
        return Num::factory($this->db, "SIGN({$this})");
    }

    /**
     * `SIN($this)`
     *
     * @return Num
     */
    public function sin()
    {
        return Num::factory($this->db, "SIN({$this})");
    }

    /**
     * `SQRT($this)`
     *
     * @return Num
     */
    public function sqrt()
    {
        return Num::factory($this->db, "SQRT({$this})");
    }

    /**
     * `($this - $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Num
     */
    public function sub($arg)
    {
        $arg = $this->db->quote($arg);
        return Num::factory($this->db, "({$this} - {$arg})");
    }

    /**
     * `TAN($this)`
     *
     * @return Num
     */
    public function tan()
    {
        return Num::factory($this->db, "TAN({$this})");
    }
}
