<?php

namespace Helix\DB\Fluent\DateTime;

use DateInterval;
use Helix\DB\Fluent\DateTime;
use Helix\DB\Fluent\Num;
use Helix\DB\Fluent\Text;
use Helix\DB\Fluent\Value\ValueTrait;

/**
 * Date-time expression manipulation.
 *
 * Each DBMS has its own quirks with dates, which is beyond the scope of this library.
 *
 * @see https://sqlite.org/lang_datefunc.html
 * @see https://dev.mysql.com/doc/refman/8.0/en/date-and-time-functions.html#function_date-format
 */
trait DateTimeTrait
{

    use ValueTrait;

    /**
     * @return DateTime
     */
    public function addDay()
    {
        return $this->addDays(1);
    }

    /**
     * @param int $days
     * @return DateTime
     */
    public function addDays(int $days)
    {
        return $this->modify(0, 0, 0, $days);
    }

    /**
     * @return DateTime
     */
    public function addHour()
    {
        return $this->addHours(1);
    }

    /**
     * @param int $hours
     * @return DateTime
     */
    public function addHours(int $hours)
    {
        return $this->modify(0, 0, $hours);
    }

    /**
     * @param int $minutes
     * @return DateTime
     */
    public function addMinutes(int $minutes)
    {
        return $this->modify(0, $minutes);
    }

    /**
     * @return DateTime
     */
    public function addMonth()
    {
        return $this->addMonths(1);
    }

    /**
     * @param int $months
     * @return DateTime
     */
    public function addMonths(int $months)
    {
        return $this->modify(0, 0, 0, 0, $months);
    }

    /**
     * @param int $seconds
     * @return DateTime
     */
    public function addSeconds(int $seconds)
    {
        return $this->modify($seconds);
    }

    /**
     * @return DateTime
     */
    public function addYear()
    {
        return $this->addYears(1);
    }

    /**
     * @param int $years
     * @return DateTime
     */
    public function addYears(int $years)
    {
        return $this->modify(0, 0, 0, 0, 0, $years);
    }

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
     * @return Text
     */
    public function dateFormat($format)
    {
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
     * Because this format is reentrant, a {@link DateTime} is returned.
     *
     * @return DateTime
     */
    public function datetime()
    {
        if ($this->db->isSQLite()) {
            return DateTime::factory($this->db, "DATETIME({$this})");
        }
        return DateTime::factory($this->db, "DATE_FORMAT({$this},'%Y-%m-%d %H:%i:%S')");
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
        $x ??= $this->db->now();
        return $x->julian()->sub($this->julian());
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
     * Date-time difference (`$x - $this`) in whole seconds elapsed (rounded).
     *
     * @param null|DateTime $x Defaults to the current time.
     * @return Num
     */
    public function diffSeconds(DateTime $x = null)
    {
        return $this->diffDays($x)->mul(24 * 60 * 60)->round();
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
     * @return Text
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
        if ($this->db->isSQLite()) {
            return Num::factory($this->db, "JULIANDAY({$this})");
        }
        // julian "year zero" offset, plus number of fractional days since "year zero".
        return Num::factory($this->db, "(1721059.5 + (TO_SECONDS({$this}) / 86400))");
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
     * Applies date-time modifiers.
     *
     * Each argument can be zero, positive, or negative.
     *
     * `$s` can also be a `DateInterval` or `DateInterval` description (e.g. `"+1 day"`).
     * If so, the rest of the arguments are ignored.
     *
     * @param int|string|DateInterval $s Seconds, or `DateInterval` related
     * @param int $m Minutes
     * @param int $h Hours
     * @param int $D Days
     * @param int $M Months
     * @param int $Y Years
     * @return DateTime
     */
    public function modify($s, int $m = 0, int $h = 0, int $D = 0, int $M = 0, int $Y = 0)
    {
        static $units = ['SECOND', 'MINUTE', 'HOUR', 'DAY', 'MONTH', 'YEAR'];
        if (is_string($s)) {
            $s = DateInterval::createFromDateString($s);
            assert($s instanceof DateInterval);
        }
        if ($s instanceof DateInterval) {
            $ints = [$s->s, $s->i, $s->h, $s->d, $s->m, $s->y];
        } else {
            $ints = func_get_args();
        }

        // remove zeroes and reverse so larger intervals happen first
        $ints = array_reverse(array_filter($ints), true);

        // sqlite allows us to do it all in one go
        if ($this->db->isSQLite()) {
            $spec = [$this];
            foreach ($ints as $i => $int) {
                $spec[] = sprintf("'%s %s'", $int > 0 ? "+{$int}" : $int, $units[$i]);
            }
            return DateTime::factory($this->db, sprintf('DATETIME(%s)', implode(',', $spec)));
        }

        // mysql requires wrapping
        $spec = "CAST({$this} AS DATETIME)";
        foreach ($ints as $i => $int) {
            $spec = sprintf('DATE_%s(%s, INTERVAL %s %s)',
                $spec,
                $int > 0 ? 'ADD' : 'SUB',
                abs($int),
                $units[$i]
            );
        }
        return DateTime::factory($this->db, $spec);
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
     * @return DateTime
     */
    public function subDay()
    {
        return $this->subDays(1);
    }

    /**
     * @param int $days
     * @return DateTime
     */
    public function subDays(int $days)
    {
        return $this->modify(0, 0, 0, $days * -1);
    }

    /**
     * @return DateTime
     */
    public function subHour()
    {
        return $this->subHours(1);
    }

    /**
     * @param int $hours
     * @return DateTime
     */
    public function subHours(int $hours)
    {
        return $this->modify(0, 0, $hours * -1);
    }

    /**
     * @param int $minutes
     * @return DateTime
     */
    public function subMinutes(int $minutes)
    {
        return $this->modify(0, $minutes * -1);
    }

    /**
     * @return DateTime
     */
    public function subMonth()
    {
        return $this->subMonths(1);
    }

    /**
     * @param int $months
     * @return DateTime
     */
    public function subMonths(int $months)
    {
        return $this->modify(0, 0, 0, 0, $months * -1);
    }

    /**
     * @param int $seconds
     * @return DateTime
     */
    public function subSeconds(int $seconds)
    {
        return $this->modify($seconds * -1);
    }

    /**
     * @return DateTime
     */
    public function subYear()
    {
        return $this->subYears(1);
    }

    /**
     * @param int $years
     * @return DateTime
     */
    public function subYears(int $years)
    {
        return $this->modify(0, 0, 0, 0, 0, $years * -1);
    }

    /**
     * `00:00:00` to `23:59:59`
     *
     * @return Text
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
        if ($this->db->isSQLite()) {
            return Num::factory($this->db, "STRFTIME('%s',{$this})");
        }
        return Num::factory($this->db, "UNIX_TIMESTAMP({$this})");
    }

    /**
     * Changes the timezone from UTC to the local timezone.
     *
     * - SQLite: Uses the operating system's timezone.
     * - MySQL: Uses PHP's timezone.
     *
     * > Warning: Chaining this multiple times will further change the timezone offset.
     *
     * @return DateTime
     */
    public function toLocalTz()
    {
        if ($this->db->isSQLite()) {
            // docs:
            // > The "localtime" modifier (12) assumes the time value to its left is in
            // > Universal Coordinated Time (UTC) and adjusts that time value so that it is in localtime.
            // > If "localtime" follows a time that is not UTC, then the behavior is undefined.
            return DateTime::factory($this->db, "DATETIME({$this},'localtime')");
        }
        $local = date_default_timezone_get();
        return DateTime::factory($this->db, "CONVERT_TZ({$this},'UTC','{$local}')");
    }

    /**
     * Changes the timezone from the local timezone to UTC.
     *
     * > Warning: Datetimes are already stored and retrieved as UTC.
     * > Only use this if you know the expression is in the local timezone.
     *
     * > Warning: Chaining this multiple times will further change the timezone offset.
     *
     * @return DateTime
     */
    public function toUtc()
    {
        if ($this->db->isSQLite()) {
            // docs:
            // > "utc" assumes that the time value to its left is in the local timezone
            // > and adjusts that time value to be in UTC. If the time to the left is not in localtime,
            // > then the result of "utc" is undefined.
            return DateTime::factory($this->db, "DATETIME({$this},'utc')");
        }
        $local = date_default_timezone_get();
        return DateTime::factory($this->db, "CONVERT_TZ({$this},'{$local}','UTC')");
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
