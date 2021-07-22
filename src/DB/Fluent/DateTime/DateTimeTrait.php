<?php

namespace Helix\DB\Fluent\DateTime;

use Helix\DB\Fluent\DateTime;
use Helix\DB\Fluent\Num;
use Helix\DB\Fluent\Str;
use Helix\DB\Fluent\Value\ValueTrait;

/**
 * Date-time expression manipulation.
 *
 * @see https://sqlite.org/lang_datefunc.html
 * @see https://dev.mysql.com/doc/refman/8.0/en/date-and-time-functions.html#function_date-format
 */
trait DateTimeTrait
{

    use DateTimeModifyTrait;
    use ValueTrait;

    /**
     * `YYYY-MM-DD`
     *
     * Because this format is reentrant, a {@link DateTime} is returned.
     *
     * @return DateTime
     */
    public function date()
    {
        return DateTime::factory($this->db, "DATE({$this})");
    }

    /**
     * Date formatting expression using a driver-appropriate function.
     *
     * @param string|string[] $format Format, or formats keyed by driver name.
     * @return Str
     */
    public function dateFormat($format)
    {
        if (is_array($format)) {
            $format = $format[$this->db->getDriver()];
        }
        $format = $this->db->quote($format);
        if ($this->db->isSQLite()) {
            return Str::factory($this->db, "STRFTIME({$format},{$this})");
        }
        return Str::factory($this->db, "DATE_FORMAT({$this},{$format})");
    }

    /**
     * `YYYY-MM-DD hh:mm:ss`
     *
     * Because this format is reentrant, a {@link DateTime} is returned.
     *
     * @return DateTime
     */
    public function datetime()
    {
        return DateTime::fromFormat($this->db, [
            'mysql' => "DATE_FORMAT(%s,'%%Y-%%m-%%d %%H:%%i:%%S')",
            'sqlite' => "DATETIME(%s)"
        ], $this);
    }

    /**
     * `01` to `31`
     *
     * @return Num
     */
    public function day()
    {
        return Num::factory($this->db, $this->dateFormat('%d'));
    }

    /**
     * `0` to `6` (Sunday is `0`)
     *
     * @return Num
     */
    public function dayOfWeek()
    {
        return Num::factory($this->db, $this->dateFormat('%w'));
    }

    /**
     * `001` to `366` (365 + 1 during leap year)
     *
     * @return Num
     */
    public function dayOfYear()
    {
        return Num::factory($this->db, $this->dateFormat('%j'));
    }

    /**
     * Date-time difference (`$x - $this`) in fractional days elapsed.
     *
     * @param null|DateTime $x Defaults to the current time.
     * @return Num
     */
    public function diffDays(DateTime $x = null)
    {
        return ($x ?? DateTime::now($this->db))->julian()->sub($this->julian());
    }

    /**
     * Date-time difference (`$x - $this`) in fractional hours elapsed.
     *
     * @param null|DateTime $x Defaults to the current time.
     * @return Num
     */
    public function diffHours(DateTime $x = null)
    {
        return $this->diffDays($x)->mul(24);
    }

    /**
     * Date-time difference (`$x - $this`) in fractional minutes elapsed.
     *
     * @param null|DateTime $x Defaults to the current time.
     * @return Num
     */
    public function diffMinutes(DateTime $x = null)
    {
        return $this->diffDays($x)->mul(24 * 60);
    }

    /**
     * Date-time difference (`$x - $this`) in fractional months elapsed.
     *
     * @param null|DateTime $x Defaults to the current time.
     * @return Num
     */
    public function diffMonths(DateTime $x = null)
    {
        return $this->diffDays($x)->div(365.2425 / 12);
    }

    /**
     * Date-time difference (`$x - $this`) in fractional seconds elapsed.
     *
     * @param null|DateTime $x Defaults to the current time.
     * @return Num
     */
    public function diffSeconds(DateTime $x = null)
    {
        return $this->diffDays($x)->mul(24 * 60 * 60);
    }

    /**
     * Date-time difference (`$x - $this`) in fractional years elapsed.
     *
     * @param null|DateTime $x Defaults to the current time.
     * @return Num
     */
    public function diffYears(DateTime $x = null)
    {
        return $this->diffDays($x)->div(365.2425);
    }

    /**
     * `00` to `23`
     *
     * @return Num
     */
    public function hours()
    {
        return Num::factory($this->db, $this->dateFormat('%H'));
    }

    /**
     * ISO-8601 compatible datetime string, offset `Z` (UTC/Zulu)
     *
     * https://en.wikipedia.org/wiki/ISO_8601
     *
     * @return Str
     */
    public function iso8601()
    {
        return $this->dateFormat([
            'mysql' => '%Y-%m-%dT%H:%i:%SZ',
            'sqlite' => '%Y-%m-%dT%H:%M:%SZ',
        ]);
    }

    /**
     * Julian day number (fractional).
     *
     * @return Num
     */
    public function julian()
    {
        return Num::fromFormat($this->db, [
            // mysql: julian "year zero" offset, plus number of fractional days since "year zero".
            'mysql' => "(1721059.5 + (TO_SECONDS(%s) / 86400))",
            'sqlite' => "JULIANDAY(%s)"
        ], $this);
    }

    /**
     * `00` to `59`
     *
     * @return Num
     */
    public function minutes()
    {
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
    public function month()
    {
        return Num::factory($this->db, $this->dateFormat('%m'));
    }

    /**
     * `00` to `59`
     *
     * @return Num
     */
    public function seconds()
    {
        return Num::factory($this->db, $this->dateFormat('%S'));
    }

    /**
     * `00:00:00` to `23:59:59`
     *
     * @return Str
     */
    public function time()
    {
        return $this->dateFormat([
            'mysql' => '%H:%i:%S',
            'sqlite' => '%H:%M:%S'
        ]);
    }

    /**
     * Unix timestamp.
     *
     * @return Num
     */
    public function timestamp()
    {
        return Num::fromFormat($this->db, [
            'mysql' => "UNIX_TIMESTAMP(%s)",
            'sqlite' => "STRFTIME('%%s',%s)",
        ], $this);
    }

    /**
     * `00` to `53`
     *
     * @return Num
     */
    public function weekOfYear()
    {
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
    public function year()
    {
        return Num::factory($this->db, $this->dateFormat('%Y'));
    }
}
