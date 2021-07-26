<?php

namespace Helix\DB\Fluent\DateTime;

use DateInterval;
use Helix\DB\Fluent\AbstractTrait;
use Helix\DB\Fluent\DateTime;
use Helix\DB\Fluent\Str;

/**
 * Date-time modifiers.
 */
trait DateTimeModifyTrait
{

    use AbstractTrait;

    /**
     * @param string|string[] $format
     * @return Str
     */
    abstract public function dateFormat($format);

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
     * @return DateTime
     */
    public function addMinute()
    {
        return $this->addMinutes(1);
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
     * @return DateTime
     */
    public function addSecond()
    {
        return $this->addSeconds(1);
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
     * `YYYY-MM-01`
     *
     * @return DateTime
     */
    public function firstDayOfMonth()
    {
        return DateTime::factory($this->db, $this->dateFormat('%Y-%m-01'));
    }

    /**
     * `YYYY-01-01`
     *
     * @return DateTime
     */
    public function firstDayOfYear()
    {
        return DateTime::factory($this->db, $this->dateFormat('%Y-01-01'));
    }

    /**
     * `YYYY-MM-DD`
     *
     * @return DateTime
     */
    public function lastDayOfMonth()
    {
        return $this->firstDayOfMonth()->addMonth()->subDay();
    }

    /**
     * `YYYY-12-31`
     *
     * @return DateTime
     */
    public function lastDayOfYear()
    {
        return DateTime::factory($this->db, $this->dateFormat('%Y-12-31'));
    }

    /**
     * Applies date-time modifiers.
     *
     * `$s` can be a `DateInterval` or `DateInterval` description (e.g. `"+1 day"`).
     * If so, the rest of the arguments are ignored.
     *
     * > Note: Modifiers are processed from greatest-to-least interval scope,
     * > meaning years are applied first and seconds are applied last.
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
     * Manually set the date components, preserving the time.
     *
     * `NULL` can be given to preserve a component's value.
     *
     * @param null|int $day
     * @param null|int $month
     * @param null|int $year
     * @return DateTime
     */
    public function setDate(int $day = null, int $month = null, int $year = null)
    {
        $day ??= '%D';
        $month ??= '%m';
        $year ??= '%Y';
        if (is_int($day)) {
            assert($day >= 1 and $day <= 31);
            $day = sprintf('%02d', $day);
        }
        if (is_int($month)) {
            assert($month >= 1 and $month <= 12);
            $month = sprintf('%02d', $month);
        }
        if (is_int($year)) {
            assert($year >= 0 and $year <= 9999);
            $year = sprintf('%04d', $year);
        }
        return DateTime::factory($this->db, $this->dateFormat([
            'mysql' => "{$year}-{$month}-{$day} %H:%i:%S",
            'sqlite' => "{$year}-{$month}-{$day} %H:%M:%S",
        ]));
    }

    /**
     * @param int $day
     * @return DateTime
     */
    public function setDay(int $day)
    {
        assert($day >= 1 and $day <= 31);
        $day = sprintf('%02d', $day);
        return DateTime::factory($this->db, $this->dateFormat([
            'mysql' => "%Y-%m-{$day} %H:%i:%S",
            'sqlite' => "%Y-%m-{$day} %H:%M:%S",
        ]));
    }

    /**
     * @param int $hours
     * @return DateTime
     */
    public function setHours(int $hours)
    {
        assert($hours >= 0 and $hours <= 23);
        $hours = sprintf('%02d', $hours);
        return DateTime::factory($this->db, $this->dateFormat([
            'mysql' => "%Y-%m-%d {$hours}:%i:%S",
            'sqlite' => "%Y-%m-%d {$hours}:%M:%S"
        ]));
    }

    /**
     * @param int $minutes
     * @return DateTime
     */
    public function setMinutes(int $minutes)
    {
        assert($minutes >= 0 and $minutes <= 59);
        $minutes = sprintf('%02d', $minutes);
        return DateTime::factory($this->db, $this->dateFormat("%Y-%m-%d %H:{$minutes}:%S"));
    }

    /**
     * @param int $month
     * @return DateTime
     */
    public function setMonth(int $month)
    {
        assert($month >= 1 and $month <= 12);
        $month = sprintf('%02d', $month);
        return DateTime::factory($this->db, $this->dateFormat([
            'mysql' => "%Y-{$month}-%d %H:%i:%S",
            'sqlite' => "%Y-{$month}-%d %H:%M:%S",
        ]));
    }

    /**
     * @param int $seconds
     * @return DateTime
     */
    public function setSeconds(int $seconds)
    {
        assert($seconds >= 0 and $seconds <= 59);
        $seconds = sprintf('%02d', $seconds);
        return DateTime::factory($this->db, $this->dateFormat([
            'mysql' => "%Y-%m-%d %H:%i:{$seconds}",
            'sqlite' => "%Y-%m-%d %H:%M:{$seconds}"
        ]));
    }

    /**
     * Manually set the time components, preserving the date.
     *
     * `NULL` can be given to preserve a component's value.
     *
     * @param null|int $seconds
     * @param null|int $minutes
     * @param null|int $hours
     * @return DateTime
     */
    public function setTime(int $seconds = null, int $minutes = null, int $hours = null)
    {
        $seconds ??= '%S';
        $minutes ??= [
            'mysql' => '%i',
            'sqlite' => '%M',
        ][$this->db->getDriver()];
        $hours ??= '%H';

        if (is_int($seconds)) {
            assert($seconds >= 0 and $seconds <= 59);
            $seconds = sprintf('%02d', $seconds);
        }
        if (is_int($minutes)) {
            assert($minutes >= 0 and $minutes <= 59);
            $minutes = sprintf('%02d', $minutes);
        }
        if (is_int($hours)) {
            assert($hours >= 0 and $hours <= 23);
            $hours = sprintf('%02d', $hours);
        }

        return DateTime::factory($this->db, $this->dateFormat("%Y-%m-%d {$hours}:{$minutes}:{$seconds}"));
    }

    /**
     * @param int $year
     * @return DateTime
     */
    public function setYear(int $year)
    {
        assert($year >= 0 and $year <= 9999);
        $year = sprintf('%04d', $year);
        return DateTime::factory($this->db, $this->dateFormat([
            'mysql' => "{$year}-%m-%d %H:%i:%S",
            'sqlite' => "{$year}-%m-%d %H:%M:%S",
        ]));
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
     * @return DateTime
     */
    public function subMinute()
    {
        return $this->subMinutes(1);
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
     * @return DateTime
     */
    public function subSecond()
    {
        return $this->subSeconds(1);
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
     * Changes the timezone from local to UTC.
     *
     * SQLite uses the system's timezone as the "local" timezone,
     * whereas MySQL allows you to specify it.
     *
     * > Warning: Datetimes are already stored and retrieved as UTC.
     * > Only use this if you know the expression is in the local timezone.
     *
     * > Warning: Chaining this multiple times will further change the timezone offset.
     *
     * @param null|string $mysqlLocalTz The "local" timezone name or offset given to MySQL. Defaults to PHP's current timezone.
     * @return DateTime
     */
    public function toUTC(string $mysqlLocalTz = null)
    {
        return DateTime::fromFormat($this->db, [
            'mysql' => "CONVERT_TZ(%s,'%s','UTC')",
            'sqlite' => "DATETIME(%s,'utc')"
        ], $this, $mysqlLocalTz ?? date_default_timezone_get());
    }
}
