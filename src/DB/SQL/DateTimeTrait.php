<?php

namespace Helix\DB\SQL;

use Helix\DB;

/**
 * Produces datetime related expressions for the instance.
 *
 * @see https://sqlite.org/lang_datefunc.html
 * @see https://dev.mysql.com/doc/refman/8.0/en/date-and-time-functions.html#function_date-format
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
        return Text::factory($this->db, $this->getDateTimeFormat('%Y-%m-%d'));
    }

    /**
     * `YYYY-MM-DD hh:mm:ss`
     *
     * @return Text
     */
    public function getDateTime () {
        return Text::factory($this->db, $this->getDateTimeFormat([
            'mysql' => '%Y-%m-%d %H:%i:%S',
            'sqlite' => '%Y-%m-%d %H:%M:%S'
        ]));
    }

    /**
     * Returns a text expression using a driver-appropriate format function.
     *
     * @param string|string[] $format Format, or formats keyed by driver name.
     * @return Text
     */
    public function getDateTimeFormat ($format) {
        if (is_array($format)) {
            $format = $format[$this->db->getDriver()];
        }
        $format = $this->db->quote($format);
        if ($this->db->isSQLite()) {
            return Text::factory($this->db, "STRFTIME({$format},{$this})");
        }
        return Text::factory($this->db, "DATE_FORMAT({$this},{$format})");
    }

    /**
     * `01` to `31`
     *
     * @return Num
     */
    public function getDay () {
        return Num::factory($this->db, $this->getDateTimeFormat('%d'));
    }

    /**
     * `0` to `6` (Sunday is `0`)
     *
     * @return Num
     */
    public function getDayOfWeek () {
        return Num::factory($this->db, $this->getDateTimeFormat('%w'));
    }

    /**
     * `001` to `366` (365 + 1 during leap year)
     *
     * @return Num
     */
    public function getDayOfYear () {
        return Num::factory($this->db, $this->getDateTimeFormat('%j'));
    }

    /**
     * `00` to `23`
     *
     * @return Num
     */
    public function getHours () {
        return Num::factory($this->db, $this->getDateTimeFormat('%H'));
    }

    /**
     * `00` to `59`
     *
     * @return Num
     */
    public function getMinutes () {
        return Num::factory($this->db, $this->getDateTimeFormat([
            'mysql' => '%i',
            'sqlite' => '%M'
        ]));
    }

    /**
     * `01` to `12`
     *
     * @return Num
     */
    public function getMonth () {
        return Num::factory($this->db, $this->getDateTimeFormat('%m'));
    }

    /**
     * `00` to `59`
     *
     * @return Num
     */
    public function getSeconds () {
        return Num::factory($this->db, $this->getDateTimeFormat('%S'));
    }

    /**
     * `00:00:00` to `23:59:59`
     *
     * @return Text
     */
    public function getTime () {
        return Text::factory($this->db, $this->getDateTimeFormat([
            'mysql' => '%H:%i:%S',
            'sqlite' => '%H:%M:%S'
        ]));
    }

    /**
     * Unix timestamp.
     *
     * @return Num
     */
    public function getTimestamp () {
        if ($this->db->isSQLite()) {
            return Num::factory($this->db, "STRFTIME('%s',{$this})");
        }
        return Num::factory($this->db, "UNIX_TIMESTAMP({$this})");
    }

    /**
     * `00` to `53`
     *
     * @return Num
     */
    public function getWeekOfYear () {
        return Num::factory($this->db, $this->getDateTimeFormat([
            'mysql' => '%U',
            'sqlite' => '%W'
        ]));
    }

    /**
     * `YYYY`
     *
     * @return Num
     */
    public function getYear () {
        return Num::factory($this->db, $this->getDateTimeFormat('%Y'));
    }
}