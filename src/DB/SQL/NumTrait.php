<?php

namespace Helix\DB\SQL;

/**
 * Produces numeric expressions for the instance.
 */
trait NumTrait {

    use AbstractTrait;

    /**
     * `ABS($this)`
     *
     * @return Num
     */
    public function abs () {
        return Num::factory($this->db, "ABS({$this})");
    }

    /**
     * `($this + $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Num
     */
    public function add ($arg) {
        return Num::factory($this->db, "({$this} + {$arg})");
    }

    /**
     * `CEIL($this)`
     *
     * @return Num
     */
    public function ceil () {
        return Num::factory($this->db, "CEIL({$this})");
    }

    /**
     * `($this / $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Num
     */
    public function divide ($arg) {
        return Num::factory($this->db, "({$this} / {$arg})");
    }

    /**
     * `FLOOR($this)`
     *
     * @return Num
     */
    public function floor () {
        return Num::factory($this->db, "FLOOR({$this})");
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
     * @return Num
     */
    public function mod ($arg) {
        return Num::factory($this->db, "({$this} % {$arg})");
    }

    /**
     * `($this * $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Num
     */
    public function multiply ($arg) {
        return Num::factory($this->db, "({$this} * {$arg})");
    }

    /**
     * `POW($this,$exponent)`
     *
     * @param number|ValueInterface $exponent
     * @return Num
     */
    public function pow ($exponent) {
        return Num::factory($this->db, "POW({$this},{$exponent})");
    }

    /**
     * `ROUND($this,$decimals)`
     *
     * @param int $decimals
     * @return Num
     */
    public function round (int $decimals = 0) {
        return Num::factory($this->db, "ROUND({$this},{$decimals})");
    }

    /**
     * `($this - $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Num
     */
    public function subtract (ValueInterface $arg) {
        return Num::factory($this->db, "({$this} - {$arg})");
    }
}