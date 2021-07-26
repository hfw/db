<?php

namespace Helix\DB\Fluent\DateTime;

use Helix\DB\Fluent\DateTime;

/**
 * Date-time subtraction helpers.
 */
trait DateTimeSubTrait
{

    use DateTimeModifyTrait;

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
}
