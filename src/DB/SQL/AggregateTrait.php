<?php

namespace Helix\DB\SQL;

/**
 * Produces aggregate expressions for the instance.
 */
trait AggregateTrait {

    use AbstractTrait;

    /**
     * `AVG($this)`
     *
     * @return Num
     */
    public function avg () {
        return Num::factory($this->db, "AVG({$this})");
    }

    /**
     * `AVG(DISTINCT $this)`
     *
     * @return Num
     */
    public function avgDistinct () {
        return Num::factory($this->db, "AVG(DISTINCT {$this})");
    }

    /**
     * `COUNT($this)`
     *
     * @return Num
     */
    public function count () {
        return Num::factory($this->db, "COUNT({$this})");
    }

    /**
     * `COUNT(DISTINCT $this)`
     *
     * @return Num
     */
    public function countDistinct () {
        return Num::factory($this->db, "COUNT(DISTINCT {$this})");
    }

    /**
     * `GROUP_CONCAT($this)` using a delimiter.
     *
     * @param string $delimiter
     * @return Text
     */
    public function groupConcat (string $delimiter = ',') {
        $delimiter = $this->db->quote($delimiter);
        if ($this->db->isSQLite()) {
            return Text::factory($this->db, "GROUP_CONCAT({$this},{$delimiter})");
        }
        return Text::factory($this->db, "GROUP_CONCAT({$this} SEPARATOR {$delimiter})");
    }

    /**
     * `MAX($this)`
     *
     * @return Num
     */
    public function max () {
        return Num::factory($this->db, "MAX({$this})");
    }

    /**
     * `MIN($this)`
     *
     * @return Num
     */
    public function min () {
        return Num::factory($this->db, "MIN({$this})");
    }

    /**
     * `SUM($this)`
     *
     * @return Num
     */
    public function sum () {
        return Num::factory($this->db, "SUM({$this})");
    }

    /**
     * `SUM(DISTINCT $this)`
     *
     * @return Num
     */
    public function sumDistinct () {
        return Num::factory($this->db, "SUM(DISTINCT {$this})");
    }
}