<?php

namespace Helix\DB\SQL;

use Helix\DB;

/**
 * Produces aggregate expressions for the instance.
 */
trait AggregateTrait {

    abstract public function __toString ();

    /**
     * @var DB
     */
    protected $db;

    /**
     * `AVG(ALL|DISTINCT $this)`
     *
     * @param string $aggregate `ALL|DISTINCT`
     * @return Num
     */
    public function getAvg (string $aggregate = 'ALL') {
        return Num::factory($this->db, "AVG({$aggregate} {$this})");
    }

    /**
     * `GROUP_CONCAT($this)` using a delimiter.
     *
     * @param string $delimiter
     * @return Text
     */
    public function getConcat (string $delimiter = ',') {
        $delimiter = $this->db->quote($delimiter);
        if ($this->db->isSQLite()) {
            return Text::factory($this->db, "GROUP_CONCAT({$this},{$delimiter})");
        }
        return Text::factory($this->db, "GROUP_CONCAT({$this} SEPARATOR {$delimiter})");
    }

    /**
     * `COUNT(ALL|DISTINCT $this)`
     *
     * @param string $aggregate `ALL|DISTINCT`
     * @return Num
     */
    public function getCount (string $aggregate = 'ALL') {
        return Num::factory($this->db, "COUNT({$aggregate} {$this})");
    }

    /**
     * `MAX($this)`
     *
     * @return Num
     */
    public function getMax () {
        return Num::factory($this->db, "MAX({$this})");
    }

    /**
     * `MIN($this)`
     *
     * @return Num
     */
    public function getMin () {
        return Num::factory($this->db, "MIN({$this})");
    }

    /**
     * `SUM(ALL|DISTINCT $this)`
     *
     * @param string $aggregate `ALL|DISTINCT`
     * @return Num
     */
    public function getSum (string $aggregate = 'ALL') {
        return Num::factory($this->db, "SUM({$aggregate} {$this})");
    }
}