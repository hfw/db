<?php

namespace Helix\DB\Fluent\Num;

use Helix\DB\Fluent\Num;
use Helix\DB\Fluent\Predicate;
use Helix\DB\Fluent\Value\ValueTrait;
use Helix\DB\Fluent\ValueInterface;

/**
 * Numeric expression manipulation.
 *
 * This trait does not include {@link NumCastFloatTrait},
 * because the expression is either already a float or an integer.
 */
trait NumTrait
{

    use ValueTrait;
    use BaseConversionTrait;
    use NumCastIntTrait;

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
     * `($this + $arg + ... )`
     *
     * @param number|ValueInterface $arg
     * @param number|ValueInterface ...$args
     * @return Num
     */
    public function add($arg, ...$args)
    {
        array_unshift($args, $this, $arg);
        return Num::factory($this->db, sprintf('(%s)', implode(' + ', $this->db->quoteArray($args))));
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
     * Bitwise `AND`
     *
     * `($this & $value)`
     *
     * @param int|ValueInterface $value
     * @return Num
     */
    public function bAnd($value)
    {
        return Num::factory($this->db, "({$this} & {$value})");
    }

    /**
     * Bitwise `NOT`
     *
     * `($this ~ $value)`
     *
     * @param int|ValueInterface $value
     * @return Num
     */
    public function bNot($value)
    {
        return Num::factory($this->db, "({$this} ~ {$value})");
    }

    /**
     * Bitwise `OR`
     *
     * `($this | $value)`
     *
     * @param int|ValueInterface $value
     * @return Num
     */
    public function bOr($value)
    {
        return Num::factory($this->db, "({$this} | {$value})");
    }

    /**
     * Bitwise shift left.
     *
     * `($this << $bits)`
     *
     * @param int $bits
     * @return Num
     */
    public function bSL(int $bits = 1)
    {
        return Num::factory($this->db, "({$this} << {$bits})");
    }

    /**
     * Bitwise shift right.
     *
     * `($this >> $bits)`
     *
     * @param int $bits
     * @return Num
     */
    public function bSR(int $bits = 1)
    {
        return Num::factory($this->db, "({$this} >> {$bits})");
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
     * `($this / $arg / ...)`
     *
     * @param number|ValueInterface $arg
     * @param number|ValueInterface ...$args
     * @return Num
     */
    public function div($arg, ...$args)
    {
        array_unshift($args, $this, $arg);
        return Num::factory($this->db, sprintf('(%s)', implode(' / ', $this->db->quoteArray($args))));
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
     * `$this = 0`
     *
     * @return Predicate
     */
    public function isZero()
    {
        return Predicate::factory($this->db, "{$this} = 0");
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
     * `($this * $arg * ...)`
     *
     * @param number|ValueInterface $arg
     * @param number|ValueInterface ...$args
     * @return Num
     */
    public function mul($arg, ...$args)
    {
        array_unshift($args, $this, $arg);
        return Num::factory($this->db, sprintf('(%s)', implode(' * ', $this->db->quoteArray($args))));
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
     * `POW($this,1/$radix)`
     *
     * @param int $radix Non-zero.
     * @return Num
     */
    public function root(int $radix)
    {
        assert($radix !== 0);
        return Num::factory($this->db, "POW({$this},1/{$radix})");
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
     * `($this - $arg - ...)`
     *
     * @param number|ValueInterface $arg
     * @param number|ValueInterface ...$args
     * @return Num
     */
    public function sub($arg, ...$args)
    {
        array_unshift($args, $this, $arg);
        return Num::factory($this->db, sprintf('(%s)', implode(' - ', $this->db->quoteArray($args))));
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
