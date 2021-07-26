<?php

namespace Helix\DB\Fluent\DateTime;

use Helix\DB\Fluent\DateTime;
use Helix\DB\Fluent\Num;

/**
 * Date-time diffing.
 */
trait DateTimeDiffTrait
{

    use DateTimeFormatTrait;

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
}
