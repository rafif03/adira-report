<?php

namespace App\Support;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Return previous-month same day, falling back to last day of previous month
     * (uses Carbon::subMonthNoOverflow)
     */
    public static function previousMonthSameDay(string $date): string
    {
        $c = Carbon::parse($date);
        return $c->copy()->subMonthNoOverflow()->toDateString();
    }
}
