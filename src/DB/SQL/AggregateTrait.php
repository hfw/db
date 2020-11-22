<?php

namespace Helix\DB\SQL;

use Helix\DB;

/**
 * Produces aggregate expressions for the instance.
 */
trait AggregateTrait {

    /**
     * @var DB
     */
    protected $db;

    /**
     * `AVG(ALL|DISTINCT $this)`
     *
     * @param string $aggregate `ALL|DISTINCT`
     * @return Numeric
     */
    public function getAvg ($aggregate = 'ALL') {
        return $this->db->factory(Numeric::class, $this->db, "AVG({$aggregate} {$this})");
    }

    /**
     * `GROUP_CONCAT($this)` using a delimiter.
     *
     * @param string $delimiter
     * @return Text
     */
    public function getConcat (string $delimiter = ',') {
        $delimiter = $this->db->quote($delimiter);
        switch ($this->db) {
            case 'sqlite':
                return $this->db->factory(Text::class, $this->db, "GROUP_CONCAT({$this},{$delimiter})");
            default:
                return $this->db->factory(Text::class, $this->db, "GROUP_CONCAT({$this} SEPARATOR {$delimiter})");
        }
    }

    /**
     * `COUNT(ALL|DISTINCT $this)`
     *
     * @param string $aggregate `ALL|DISTINCT`
     * @return Numeric
     */
    public function getCount (string $aggregate = 'ALL') {
        return $this->db->factory(Numeric::class, $this->db, "COUNT({$aggregate} {$this})");
    }

    /**
     * `MAX($this)`
     *
     * @return Numeric
     */
    public function getMax () {
        return $this->db->factory(Numeric::class, $this->db, "MAX({$this})");
    }

    /**
     * `MIN($this)`
     *
     * @return Numeric
     */
    public function getMin () {
        return $this->db->factory(Numeric::class, $this->db, "MIN({$this})");
    }

    /**
     * `SUM(ALL|DISTINCT $this)`
     *
     * @param string $aggregate `ALL|DISTINCT`
     * @return Numeric
     */
    public function getSum (string $aggregate = 'ALL') {
        return $this->db->factory(Numeric::class, $this->db, "SUM({$aggregate} {$this})");
    }
}