<?php

namespace Helix\DB\Fluent\Str;

use Helix\DB\Fluent\Num;
use Helix\DB\Fluent\Num\BaseConversionTrait;
use Helix\DB\Fluent\Predicate;
use Helix\DB\Fluent\Str;
use Helix\DB\Fluent\Value\ValueTrait;
use Helix\DB\Fluent\ValueInterface;

/**
 * Character string expression manipulation.
 */
trait StrTrait
{

    use ValueTrait;
    use BaseConversionTrait;

    /**
     * @param int $direction
     * @param null|string $chars
     * @return Str
     * @internal
     */
    protected function _trim(int $direction, string $chars = null)
    {
        $function = [-1 => 'LTRIM', 0 => 'TRIM', 1 => 'RTRIM'][$direction];
        if (isset($chars)) {
            $chars = $this->db->quote($chars);
            if ($this->db->isSQLite()) {
                return Str::factory($this->db, "{$function}({$this},{$chars})");
            }
            $direction = [-1 => 'LEADING', 0 => 'BOTH', 1 => 'TRAILING'][$direction];
            return Str::factory($this->db, "TRIM({$direction} {$chars} FROM {$this})");
        }
        return Str::factory($this->db, "{$function}({$this})");
    }

    /**
     * Concatenate other strings.
     *
     * @param string|ValueInterface ...$strings
     * @return Str
     */
    public function concat(...$strings)
    {
        array_unshift($strings, $this);
        $strings = $this->db->quoteArray($strings);
        if ($this->db->isSQLite()) {
            return Str::factory($this->db, sprintf('(%s)', implode(' || ', $strings)));
        }
        return Str::factory($this->db, sprintf('CONCAT(%s)', implode(',', $strings)));
    }

    /**
     * Hex representation.
     *
     * @return Str
     */
    public function hex()
    {
        return Str::factory($this->db, "HEX({$this})");
    }

    /**
     * Zero-length.
     *
     * @return Predicate
     */
    public function isEmpty()
    {
        return $this->length()->isZero();
    }

    /**
     * Number of characters (not necessarily bytes).
     *
     * @see StrTrait::size()
     *
     * @return Num
     */
    public function length()
    {
        return Num::fromFormat($this->db, [
            'mysql' => "CHAR_LENGTH(%s)",
            'sqlite' => "LENGTH(CAST(%s AS TEXT))"
        ], $this);
    }

    /**
     * Lowercase.
     *
     * @return Str
     */
    public function lower()
    {
        return Str::factory($this->db, "LOWER({$this})");
    }

    /**
     * See {@link StrTrait::trim()}
     *
     * @param null|string $chars
     * @return Str
     */
    public function ltrim(string $chars = null)
    {
        return $this->_trim(-1, $chars);
    }

    /**
     * Substring's position (1-based).
     *
     * The position is `0` if the substring isn't found.
     *
     * @param string $substring
     * @return Num
     */
    public function position(string $substring)
    {
        $substring = $this->db->quote($substring);
        if ($this->db->isSQLite()) {
            return Num::factory($this->db, "INSTR({$this},{$substring})");
        }
        return Num::factory($this->db, "LOCATE({$substring},{$this})");
    }

    /**
     * String replacement.
     *
     * @param string $search
     * @param string $replace
     * @return Str
     */
    public function replace(string $search, string $replace)
    {
        $search = $this->db->quote($search);
        $replace = $this->db->quote($replace);
        return Str::factory($this->db, "REPLACE({$this},{$search},{$replace})");
    }

    /**
     * See {@link StrTrait::trim()}
     *
     * @param null|string $chars
     * @return Str
     */
    public function rtrim(string $chars = null)
    {
        return $this->_trim(1, $chars);
    }

    /**
     * Number of bytes (not necessarily characters).
     *
     * @return Num
     */
    public function size()
    {
        return Num::fromFormat($this->db, [
            'mysql' => "LENGTH(%s)",
            'sqlite' => "LENGTH(CAST(%s AS BLOB))"
        ], $this);
    }

    /**
     * Substring.
     *
     * @param int $start 1-based, can be negative to start from the right.
     * @param null|int $length
     * @return Str
     */
    public function substr(int $start, int $length = null)
    {
        assert($start !== 0);
        if (isset($length)) {
            return Str::factory($this->db, "SUBSTR({$this},{$start},{$length})");
        }
        return Str::factory($this->db, "SUBSTR({$this},{$start})");
    }

    /**
     * Convert from an arbitrary base to base 10.
     *
     * @param int $from
     * @return Num
     */
    public function toBase10(int $from)
    {
        return Num::factory($this->db, "CONV({$this},{$from},10)");
    }

    /**
     * Trims whitespace (or other things) from both ends of the string.
     *
     * If `$chars` is given:
     * - MySQL treats it as a leading/trailing string
     * - SQLite treats it as individual characters (same as PHP)
     *
     * @see StrTrait::ltrim()
     * @see StrTrait::rtrim()
     *
     * @param null|string $chars
     * @return Str
     */
    public function trim(string $chars = null)
    {
        return $this->_trim(0, $chars);
    }

    /**
     * Uppercase.
     *
     * @return Str
     */
    public function upper()
    {
        return Str::factory($this->db, "UPPER({$this})");
    }
}
