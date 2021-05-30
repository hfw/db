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
        return $this->db->factory(Numeric::class, $this->db, "ABS({$this})");
    }

    /**
     * `($this + $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Numeric
     */
    public function add ($arg) {
        return $this->db->factory(Numeric::class, $this->db, "({$this} + {$arg})");
    }

    /**
     * `CEIL($this)`
     *
     * @return Numeric
     */
    public function ceil () {
        return $this->db->factory(Numeric::class, $this->db, "CEIL({$this})");
    }

    /**
     * `($this / $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Numeric
     */
    public function divide ($arg) {
        return $this->db->factory(Numeric::class, $this->db, "({$this} / {$arg})");
    }

    /**
     * `FLOOR($this)`
     *
     * @return Numeric
     */
    public function floor () {
        return $this->db->factory(Numeric::class, $this->db, "FLOOR({$this})");
    }

    /**
     * `($this % 2) = 0`
     *
     * @return Predicate
     */
    public function isEven () {
        return $this->db->factory(Predicate::class, "({$this} % 2) = 0");
    }

    /**
     * `$this < 0`
     *
     * @return Predicate
     */
    public function isNegative () {
        return $this->db->factory(Predicate::class, "{$this} < 0");
    }

    /**
     * `($this % 2) <> 0`
     *
     * @return Predicate
     */
    public function isOdd () {
        return $this->db->factory(Predicate::class, "({$this} % 2) <> 0");
    }

    /**
     * `$this > 0`
     *
     * @return Predicate
     */
    public function isPositive () {
        return $this->db->factory(Predicate::class, "{$this} > 0");
    }

    /**
     * `($this % $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Numeric
     */
    public function modulo ($arg) {
        return $this->db->factory(Numeric::class, $this->db, "({$this} % {$arg})");
    }

    /**
     * `($this * $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Numeric
     */
    public function multiply ($arg) {
        return $this->db->factory(Numeric::class, $this->db, "({$this} * {$arg})");
    }

    /**
     * `POW($this,$exponent)`
     *
     * @param number|ValueInterface $exponent
     * @return Numeric
     */
    public function pow ($exponent) {
        return $this->db->factory(Numeric::class, $this->db, "POW({$this},{$exponent})");
    }

    /**
     * `ROUND($this,$decimals)`
     *
     * @param int $decimals
     * @return Numeric
     */
    public function round (int $decimals = 0) {
        return $this->db->factory(Numeric::class, $this->db, "ROUND({$this},{$decimals})");
    }

    /**
     * `($this - $arg)`
     *
     * @param number|ValueInterface $arg
     * @return Numeric
     */
    public function subtract ($arg) {
        return $this->db->factory(Numeric::class, $this->db, "({$this} - {$arg})");
    }
}