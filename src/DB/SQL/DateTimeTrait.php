<?php

namespace Helix\DB\SQL;

/**
 * Produces datetime related expressions for the instance.
 *
 * Each DBMS has its own quirks with dates, which is beyond the scope of this library.
 *
 * @see https://sqlite.org/lang_datefunc.html
 * @see https://dev.mysql.com/doc/refman/8.0/en/date-and-time-functions.html#function_date-format
 */
trait DateTimeTrait {

    use AbstractTrait;

    /**
     * `YYYY-MM-DD`
     *
     * @return Text
     */
    public function date () {
        return Text::factory($this->db, $this->dateFormat('%Y-%m-%d'));
    }

    /**
     * Date formatting expression using a driver-appropriate function.
     *
     * @param string|string[] $format Format, or formats keyed by driver name.
     * @return Text
     */
    public function dateFormat ($format) {
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
     * `YYYY-MM-DD hh:mm:ss`
     *
     * @return Text
     */
    public function datetime () {
        return Text::factory($this->db, $this->dateFormat([
            'mysql' => '%Y-%m-%d %H:%i:%S',
            'sqlite' => '%Y-%m-%d %H:%M:%S'
        ]));
    }

    /**
     * `01` to `31`
     *
     * @return Num
     */
    public function day () {
        return Num::factory($this->db, $this->dateFormat('%d'));
    }

    /**
     * `0` to `6` (Sunday is `0`)
     *
     * @return Num
     */
    public function dayOfWeek () {
        return Num::factory($this->db, $this->dateFormat('%w'));
    }

    /**
     * `001` to `366` (365 + 1 during leap year)
     *
     * @return Num
     */
    public function dayOfYear () {
        return Num::factory($this->db, $this->dateFormat('%j'));
    }

    /**
     * `00` to `23`
     *
     * @return Num
     */
    public function hours () {
        return Num::factory($this->db, $this->dateFormat('%H'));
    }

    /**
     * `00` to `59`
     *
     * @return Num
     */
    public function minutes () {
        return Num::factory($this->db, $this->dateFormat([
            'mysql' => '%i',
            'sqlite' => '%M'
        ]));
    }

    /**
     * `01` to `12`
     *
     * @return Num
     */
    public function month () {
        return Num::factory($this->db, $this->dateFormat('%m'));
    }

    /**
     * `00` to `59`
     *
     * @return Num
     */
    public function seconds () {
        return Num::factory($this->db, $this->dateFormat('%S'));
    }

    /**
     * `00:00:00` to `23:59:59`
     *
     * @return Text
     */
    public function time () {
        return Text::factory($this->db, $this->dateFormat([
            'mysql' => '%H:%i:%S',
            'sqlite' => '%H:%M:%S'
        ]));
    }

    /**
     * Unix timestamp.
     *
     * @return Num
     */
    public function timestamp () {
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
    public function weekOfYear () {
        return Num::factory($this->db, $this->dateFormat([
            'mysql' => '%U',
            'sqlite' => '%W'
        ]));
    }

    /**
     * `YYYY`
     *
     * @return Num
     */
    public function year () {
        return Num::factory($this->db, $this->dateFormat('%Y'));
    }
}