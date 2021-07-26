<?php

namespace Helix\DB\Fluent\DateTime;

use Helix\DB\Fluent\DateTime;

/**
 * Date-time addition helpers.
 */
trait DateTimeAddTrait
{

    use DateTimeModifyTrait;

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
}
