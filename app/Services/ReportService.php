<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Support\DateHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ReportService
{
    /**
     * Get daily report summary for a role and date, including previous-month same-day,
     * growth and achievement against monthly target.
     *
     * Returns array with keys: current_amount, prev_amount, growth, growth_pct, achievement_pct
     */
    public static function getDailyComparisons(int $roleId, string $date): array
    {
        $current = DailyReport::where('role_id', $roleId)
            ->where('date', $date)
            ->first();

        $currentAmount = $current?->amount ?? 0;

        $prevDate = DateHelper::previousMonthSameDay($date);
        $prev = DailyReport::where('role_id', $roleId)
            ->where('date', $prevDate)
            ->first();

        $prevAmount = $prev?->amount ?? 0;

        $growth = $currentAmount - $prevAmount;
        $growthPct = $prevAmount == 0 ? null : ($growth / $prevAmount) * 100;

        // load monthly target for the month of $date by summing per-user targets for the role
        $dt = Carbon::parse($date);
        $year = $dt->year;
        $month = $dt->month;

        $carTarget = DB::table('monthly_car_targets')
            ->where('monthly_car_targets.role_id', $roleId)
            ->where('monthly_car_targets.year', $year)
            ->where('monthly_car_targets.month', $month)
            ->selectRaw('COALESCE(SUM(monthly_car_targets.target_amount),0) as amount')
            ->value('amount');

        $motorTarget = DB::table('monthly_motor_targets')
            ->where('monthly_motor_targets.role_id', $roleId)
            ->where('monthly_motor_targets.year', $year)
            ->where('monthly_motor_targets.month', $month)
            ->selectRaw('COALESCE(SUM(monthly_motor_targets.target_amount),0) as amount')
            ->value('amount');

        $monthlyTargetAmount = floatval(($carTarget ?? 0) + ($motorTarget ?? 0));
        $achievementPct = $monthlyTargetAmount == 0 ? null : ($currentAmount / $monthlyTargetAmount) * 100;

        return [
            'current_amount' => (float) $currentAmount,
            'prev_amount' => (float) $prevAmount,
            'growth' => (float) $growth,
            'growth_pct' => $growthPct === null ? null : (float) $growthPct,
            'achievement_pct' => $achievementPct === null ? null : (float) $achievementPct,
        ];
    }
}
