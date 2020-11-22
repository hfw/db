<?php

namespace Helix\DB\SQL;

use Helix\DB;

/**
 * Produces text related expressions for the instance.
 */
trait TextTrait {

    /**
     * @var DB
     */
    protected $db;

    /**
     * @return Text
     */
    public function getHex () {
        return $this->db->factory(Text::class, $this->db, "HEX({$this})");
    }

    /**
     * Returns the number of characters (not bytes) in the string.
     *
     * @see getSize()
     *
     * @return Numeric
     */
    public function getLength () {
        switch ($this->db) {
            case 'sqlite':
                return $this->db->factory(Numeric::class, $this->db, "LENGTH(CAST({$this} AS TEXT))");
            default:
                return $this->db->factory(Numeric::class, $this->db, "CHAR_LENGTH({$this})");
        }
    }

    /**
     * `LOWER($this)`
     *
     * @return Text
     */
    public function getLower () {
        return $this->db->factory(Text::class, $this->db, "LOWER({$this})");
    }

    /**
     * A substring's position (1-based).
     *
     * The position is `0` if the substring isn't found.
     *
     * @param string $substring
     * @return Numeric
     */
    public function getPosition (string $substring) {
        $substring = $this->db->quote($substring);
        switch ($this->db) {
            case 'sqlite':
                return $this->db->factory(Numeric::class, $this->db, "INSTR({$this},{$substring})");
            default:
                return $this->db->factory(Numeric::class, $this->db, "LOCATE({$substring},{$this})");
        }
    }

    /**
     * `REPLACE($this,$search,$replace)`
     *
     * @param string $search
     * @param string $replace
     * @return Text
     */
    public function getReplacement (string $search, string $replace) {
        $search = $this->db->quote($search);
        $replace = $this->db->quote($replace);
        return $this->db->factory(Text::class, $this->db, "REPLACE({$this},{$search},{$replace})");
    }

    /**
     * The number of bytes in the string.
     *
     * @return Numeric
     */
    public function getSize () {
        switch ($this->db) {
            case 'sqlite':
                return $this->db->factory(Numeric::class, $this->db, "LENGTH(CAST({$this} AS BLOB))");
            default:
                return $this->db->factory(Numeric::class, $this->db, "LENGTH({$this})");
        }
    }

    /**
     * `SUBSTR($this,$start)` or `SUBSTR($this,$start,$length)`
     *
     * @param int $start 1-based, can be negative to start from the right.
     * @param int $length
     * @return Text
     */
    public function getSubstring (int $start, int $length = null) {
        if (isset($length)) {
            return $this->db->factory(Text::class, $this->db, "SUBSTR({$this},{$start},{$length})");
        }
        return $this->db->factory(Text::class, $this->db, "SUBSTR({$this},{$start})");
    }

    /**
     * `UPPER($this)`
     *
     * @return Text
     */
    public function getUpper () {
        return $this->db->factory(Text::class, $this->db, "UPPER({$this})");
    }
}