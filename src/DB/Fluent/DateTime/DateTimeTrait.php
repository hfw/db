<?php

namespace Helix\DB\Fluent\DateTime;

use DateInterval;
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
            'mysql' => "DATE_FORMAT(%s,'%Y-%m-%d %H:%i:%S')",
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
        return ($x ?? $this->db->now())->julian()->sub($this->julian());
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
     * Applies date-time modifiers.
     *
     * `$s` can be a `DateInterval` or `DateInterval` description (e.g. `"+1 day"`).
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
        // interval units. process larger intervals first.
        static $units = ['YEAR', 'MONTH', 'DAY', 'HOUR', 'MINUTE', 'SECOND'];
        if (is_string($s)) {
            $s = DateInterval::createFromDateString($s);
            assert($s instanceof DateInterval);
        }
        if ($s instanceof DateInterval) {
            $ints = [$s->y, $s->m, $s->d, $s->h, $s->i, $s->s];
        } else {
            $ints = [$Y, $M, $D, $h, $m, $s];
        }

        // key by units and remove zeroes
        $ints = array_filter(array_combine($units, $ints));

        if ($this->db->isSQLite()) {
            return $this->modify_sqlite($ints);
        }
        return $this->modify_mysql($ints);
    }

    /**
     * MySQL requires nesting.
     *
     * @param int[] $ints
     * @return DateTime
     * @internal
     */
    protected function modify_mysql(array $ints)
    {
        $spec = $this;
        foreach ($ints as $unit => $int) {
            $spec = sprintf('DATE_%s(%s, INTERVAL %s %s)', $int > 0 ? 'ADD' : 'SUB', $spec, abs($int), $unit);
        }
        return DateTime::factory($this->db, $spec);
    }

    /**
     * SQLite allows variadic modifiers.
     *
     * @param int[] $ints
     * @return DateTime
     * @internal
     */
    protected function modify_sqlite(array $ints)
    {
        $spec = [$this];
        foreach ($ints as $unit => $int) {
            $spec[] = sprintf("'%s %s'", $int > 0 ? "+{$int}" : $int, $unit);
        }
        return DateTime::factory($this->db, sprintf('DATETIME(%s)', implode(',', $spec)));
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
     * Changes the timezone from the local timezone to UTC.
     *
     * > Warning: Datetimes are already stored and retrieved as UTC.
     * > Only use this if you know the expression is in the local timezone.
     *
     * > Warning: Chaining this multiple times will further change the timezone offset.
     *
     * @return DateTime
     */
    public function toUTC()
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
