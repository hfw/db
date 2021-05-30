<?php

namespace Helix\DB\SQL;

use Helix\DB;

/**
 * Produces numeric expressions for the instance.
 */
trait NumericTrait {

    abstract public function __toString ();

    /**
     * @var DB
     */
    protected $db;

    /**
     * `ABS($this)`
     *
     * @return Numeric
     */
    public function abs () {
        return Numeric::factory($this->db, "ABS({$this})");
    }

    /**
     * `($this + $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Numeric
     */
    public function add ($arg) {
        return Numeric::factory($this->db, "({$this} + {$arg})");
    }

    /**
     * `CEIL($this)`
     *
     * @return Numeric
     */
    public function ceil () {
        return Numeric::factory($this->db, "CEIL({$this})");
    }

    /**
     * `($this / $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Numeric
     */
    public function divide ($arg) {
        return Numeric::factory($this->db, "({$this} / {$arg})");
    }

    /**
     * `FLOOR($this)`
     *
     * @return Numeric
     */
    public function floor () {
        return Numeric::factory($this->db, "FLOOR({$this})");
    }

    /**
     * `($this % 2) = 0`
     *
     * @return Predicate
     */
    public function isEven () {
        return Predicate::factory($this->db, "({$this} % 2) = 0");
    }

    /**
     * `$this < 0`
     *
     * @return Predicate
     */
    public function isNegative () {
        return Predicate::factory($this->db, "{$this} < 0");
    }

    /**
     * `($this % 2) <> 0`
     *
     * @return Predicate
     */
    public function isOdd () {
        return Predicate::factory($this->db, "({$this} % 2) <> 0");
    }

    /**
     * `$this > 0`
     *
     * @return Predicate
     */
    public function isPositive () {
        return Predicate::factory($this->db, "{$this} > 0");
    }

    /**
     * `($this % $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Numeric
     */
    public function modulo ($arg) {
        return Numeric::factory($this->db, "({$this} % {$arg})");
    }

    /**
     * `($this * $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Numeric
     */
    public function multiply ($arg) {
        return Numeric::factory($this->db, "({$this} * {$arg})");
    }

    /**
     * `POW($this,$exponent)`
     *
     * @param number|ValueInterface $exponent
     * @return Numeric
     */
    public function pow ($exponent) {
        return Numeric::factory($this->db, "POW({$this},{$exponent})");
    }

    /**
     * `ROUND($this,$decimals)`
     *
     * @param int $decimals
     * @return Numeric
     */
    public function round (int $decimals = 0) {
        return Numeric::factory($this->db, "ROUND({$this},{$decimals})");
    }

    /**
     * `($this - $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Numeric
     */
    public function subtract (ValueInterface $arg) {
        return Numeric::factory($this->db, "({$this} - {$arg})");
    }
}