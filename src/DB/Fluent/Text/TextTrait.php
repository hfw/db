<?php

namespace Helix\DB\Fluent\Text;

use Helix\DB\Fluent\Num;
use Helix\DB\Fluent\Num\BaseConversionTrait;
use Helix\DB\Fluent\Text;
use Helix\DB\Fluent\Value\ValueTrait;

/**
 * Produces text related expressions for the instance.
 */
trait TextTrait {

    use ValueTrait;
    use BaseConversionTrait;

    /**
     * Hex representation.
     *
     * `HEX($this)`
     *
     * @return Text
     */
    public function hex () {
        return Text::factory($this->db, "HEX({$this})");
    }

    /**
     * Number of characters (not necessarily bytes).
     *
     * @see TextTrait::size()
     *
     * @return Num
     */
    public function length () {
        if ($this->db->isSQLite()) {
            return Num::factory($this->db, "LENGTH(CAST({$this} AS TEXT))");
        }
        return Num::factory($this->db, "CHAR_LENGTH({$this})");
    }

    /**
     * Lowercase.
     *
     * `LOWER($this)`
     *
     * @return Text
     */
    public function lower () {
        return Text::factory($this->db, "LOWER({$this})");
    }

    /**
     * Substring's position (1-based).
     *
     * The position is `0` if the substring isn't found.
     *
     * @param string $substring
     * @return Num
     */
    public function position (string $substring) {
        $substring = $this->db->quote($substring);
        if ($this->db->isSQLite()) {
            return Num::factory($this->db, "INSTR({$this},{$substring})");
        }
        return Num::factory($this->db, "LOCATE({$substring},{$this})");
    }

    /**
     * String replacement.
     *
     * `REPLACE($this,$search,$replace)`
     *
     * @param string $search
     * @param string $replace
     * @return Text
     */
    public function replace (string $search, string $replace) {
        $search = $this->db->quote($search);
        $replace = $this->db->quote($replace);
        return Text::factory($this->db, "REPLACE({$this},{$search},{$replace})");
    }

    /**
     * Size in bytes.
     *
     * @return Num
     */
    public function size () {
        if ($this->db->isSQLite()) {
            return Num::factory($this->db, "LENGTH(CAST({$this} AS BLOB))");
        }
        return Num::factory($this->db, "LENGTH({$this})");
    }

    /**
     * Substring.
     *
     * `SUBSTR($this,$start)` or `SUBSTR($this,$start,$length)`
     *
     * @param int $start 1-based, can be negative to start from the right.
     * @param null|int $length
     * @return Text
     */
    public function substr (int $start, int $length = null) {
        if (isset($length)) {
            return Text::factory($this->db, "SUBSTR({$this},{$start},{$length})");
        }
        return Text::factory($this->db, "SUBSTR({$this},{$start})");
    }

    /**
     * Convert from an arbitrary base to base 10.
     *
     * `CONV($this,$from,10)`
     *
     * @param int $from
     * @return Num
     */
    public function toBase10 (int $from) {
        return Num::factory($this->db, "CONV({$this},{$from},10)");
    }

    /**
     * Uppercase.
     *
     * `UPPER($this)`
     *
     * @return Text
     */
    public function upper () {
        return Text::factory($this->db, "UPPER({$this})");
    }
}
