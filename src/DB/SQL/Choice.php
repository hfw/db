<?php

namespace Helix\DB\SQL;

use Helix\DB;

/**
 * Represents a `CASE` expression, with or without a *subject* expression.
 *
 * If a subject is used, `WHEN` relies on equating the subject's evaluation to a literal value.
 * This is also known as a "simple case", and is not null-safe.
 * If you are choosing between known states, this may be the use-case for you.
 *
 * Otherwise `WHEN` relies on arbitrary predication.
 * This is also known as a "searched case", and may be constructed in a null-safe manner.
 * If you are choosing amongst complex states, this may be the use-case for you.
 *
 * With a subject (simple case):
 *
 * ```
 * CASE subject
 *  WHEN value THEN value
 *  ...
 * END
 * ```
 *
 * Without a subject (searched case):
 *
 * ```
 * CASE
 *  WHEN predicate THEN value
 *  ...
 * END
 * ```
 *
 * @method static static factory(DB $db, string $subject = null, array $values = [])
 */
class Choice extends Value {

    /**
     * @var string
     */
    protected $else;

    /**
     * @var string
     */
    protected $subject;

    /**
     * [when => then]
     *
     * @var string[]
     */
    protected $values = [];

    /**
     * @param DB $db
     * @param null|string $subject
     * @param array $values `[when => then]` for the subject.
     */
    public function __construct (DB $db, string $subject = null, array $values = []) {
        parent::__construct($db, '');
        $this->subject = $subject;
        $this->whenValues($values);
    }

    /**
     * @return string
     */
    public function __toString () {
        $sql = 'CASE';
        if (isset($this->subject)) {
            $sql .= " {$this->subject}";
        }
        foreach ($this->values as $when => $then) {
            $sql .= " WHEN {$when} THEN {$then}";
        }
        if (isset($this->else)) {
            $sql .= " ELSE {$this->else}";
        }
        $sql .= ' END';
        return $sql;
    }

    /**
     * @param string|ValueInterface $else
     * @return $this
     */
    public function else ($else) {
        $this->else = isset($else) ? $this->db->quote($else) : null;
        return $this;
    }

    /**
     * Quotes and sets a conditional value.
     *
     * @param string|ValueInterface $expression
     * @param string|ValueInterface $then
     * @return $this
     */
    public function when ($expression, $then) {
        $this->values[$this->db->quote($expression)] = $this->db->quote($then);
        return $this;
    }

    /**
     * Quotes and sets multiple conditional values for the subject.
     *
     * > :warning: Keys are quoted as literal values.
     * > Use {@link when()} if you need an expression for the `WHEN` clause.
     *
     * @param array $values `[when => then]`
     * @return $this
     */
    public function whenValues (array $values) {
        foreach ($values as $when => $then) {
            $this->when($when, $then);
        }
        return $this;
    }
}