<?php

namespace Helix\DB\Fluent\DateTime;

use DateInterval;
use Helix\DB\Fluent\AbstractTrait;
use Helix\DB\Fluent\DateTime;

/**
 * Date-time modifiers.
 */
trait DateTimeModifyTrait
{

    use AbstractTrait;

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
