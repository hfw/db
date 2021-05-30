<?php

namespace Helix\DB\SQL;

use Helix\DB;

/**
 * Produces datetime related expressions for the instance.
 */
trait DateTimeTrait {

    abstract public function __toString ();

    /**
     * @var DB
     */
    protected $db;

    /**
     * `YYYY-MM-DD`
     *
     * @return Text
     */
    public function getDate () {
        return $this->db->factory(Text::class, $this->db, $this->getDateTimeFormat('%Y-%m-%d'));
    }

    /**
     * Returns a text expression using a driver-appropriate format function.
     *
     * @param string|array $format Format, or formats keyed by driver name.
     * @return Text
     */
    public function getDateTimeFormat ($format) {
        if (is_array($format)) {
            $format = $format[$this->db->getDriver()];
        }
        $format = $this->db->quote($format);
        if ($this->db->isSQLite()) {
            return $this->db->factory(Text::class, $this->db, "STRFTIME({$format},{$this})");
        }
        return $this->db->factory(Text::class, $this->db, "DATE_FORMAT({$this},{$format})");
    }

    /**
     * `01` to `31`
     *
     * @return Numeric
     */
    public function getDay () {
        return $this->db->factory(Numeric::class, $this->db, $this->getDateTimeFormat('%d'));
    }

    /**
     * `0` to `6` (Sunday is `0`)
     *
     * @return Numeric
     */
    public function getDayOfWeek () {
        return $this->db->factory(Numeric::class, $this->db, $this->getDateTimeFormat('%w'));
    }

    /**
     * `001` to `366` (365 + 1 during leap year)
     *
     * @return Numeric
     */
    public function getDayOfYear () {
        return $this->db->factory(Numeric::class, $this->db, $this->getDateTimeFormat('%j'));
    }

    /**
     * `00` to `23`
     *
     * @return Numeric
     */
    public function getHours () {
        return $this->db->factory(Numeric::class, $this->db, $this->getDateTimeFormat('%H'));
    }

    /**
     * `00` to `59`
     *
     * @return Numeric
     */
    public function getMinutes () {
        return $this->db->factory(Numeric::class, $this->db, $this->getDateTimeFormat([
            'mysql' => '%i',
            'sqlite' => '%M'
        ]));
    }

    /**
     * `01` to `12`
     *
     * @return Numeric
     */
    public function getMonth () {
        return $this->db->factory(Numeric::class, $this->db, $this->getDateTimeFormat('%m'));
    }

    /**
     * `00` to `59`
     *
     * @return Numeric
     */
    public function getSeconds () {
        return $this->db->factory(Numeric::class, $this->db, $this->getDateTimeFormat('%S'));
    }

    /**
     * `00:00:00` to `23:59:59`
     *
     * @return Text
     */
    public function getTime () {
        return $this->db->factory(Text::class, $this->db, $this->getDateTimeFormat([
            'mysql' => '%H:%i:%S',
            'sqlite' => '%H:%M:%S'
        ]));
    }

    /**
     * Unix timestamp.
     *
     * @return Numeric
     */
    public function getTimestamp () {
        if ($this->db->isSQLite()) {
            return $this->db->factory(Numeric::class, $this->db, "STRFTIME('%s',{$this})");
        }
        return $this->db->factory(Numeric::class, $this->db, "UNIX_TIMESTAMP({$this})");
    }

    /**
     * `00` to `53`
     *
     * @return Numeric
     */
    public function getWeekOfYear () {
        return $this->db->factory(Numeric::class, $this->db, $this->getDateTimeFormat([
            'mysql' => '%U',
            'sqlite' => '%W'
        ]));
    }

    /**
     * `YYYY`
     *
     * @return Numeric
     */
    public function getYear () {
        return $this->db->factory(Numeric::class, $this->db, $this->getDateTimeFormat('%Y'));
    }
}