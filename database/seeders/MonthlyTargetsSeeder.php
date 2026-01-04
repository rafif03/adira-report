<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MonthlyTargetsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // New behavior:
        // - Date range: 15 Nov 2025 .. 15 Mar 2026
        // - Each user provides at most one car and one motor report per day
        // - Monthly targets are approximately 30x the average daily realization for that month

        $startDate = Carbon::create(2025, 11, 15);
        $endDate = Carbon::create(2026, 3, 15);

        // roles/users (exclude admin & guest)
        $roleIds = DB::table('roles')->whereNotIn('slug', ['admin','guest'])->pluck('id')->toArray();
        $users = DB::table('users')->whereIn('role_id', $roleIds)->get();

        // accumulators for monthly sums and day counts per role-month
        $monthlyCarSums = []; // [roleId]["YYYY-MM"] = ['units'=>int,'amount'=>int]
        $monthlyMotorSums = [];
        $monthlyDayCounts = []; // [roleId]["YYYY-MM"] = int

        // iterate dates and create one car + one motor report per user per day.
        // monthly targets are computed from the aggregated role totals.
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $ds = $date->toDateString();

            $roleDayCar = [];
            $roleDayMotor = [];

            foreach ($users as $user) {
                // Each user inputs one car and one motor report per day
                $carUnits = rand(0, 10);
                $carAmount = $carUnits * rand(1000000, 5000000);

                $motorUnits = rand(0, 20);
                $motorAmount = $motorUnits * rand(200000, 800000);

                // insert/update one row per user/day
                DB::table('car_reports')->updateOrInsert(
                    ['role_id' => $user->role_id, 'date' => $ds, 'submitted_by' => $user->id],
                    [
                        'units' => $carUnits,
                        'amount' => $carAmount,
                    ]
                );

                DB::table('motor_reports')->updateOrInsert(
                    ['role_id' => $user->role_id, 'date' => $ds, 'submitted_by' => $user->id],
                    [
                        'units' => $motorUnits,
                        'amount' => $motorAmount,
                    ]
                );

                // accumulate per role/day totals
                $r = $user->role_id;
                $roleDayCar[$r]['units'] = ($roleDayCar[$r]['units'] ?? 0) + $carUnits;
                $roleDayCar[$r]['amount'] = ($roleDayCar[$r]['amount'] ?? 0) + $carAmount;

                $roleDayMotor[$r]['units'] = ($roleDayMotor[$r]['units'] ?? 0) + $motorUnits;
                $roleDayMotor[$r]['amount'] = ($roleDayMotor[$r]['amount'] ?? 0) + $motorAmount;
            }

            // accumulate monthly sums (based on role-level totals)
            foreach ($roleIds as $roleId) {
                $carUnits = $roleDayCar[$roleId]['units'] ?? 0;
                $carAmount = $roleDayCar[$roleId]['amount'] ?? 0;
                $motorUnits = $roleDayMotor[$roleId]['units'] ?? 0;
                $motorAmount = $roleDayMotor[$roleId]['amount'] ?? 0;

                $ym = $date->format('Y-m');
                if (!isset($monthlyCarSums[$roleId][$ym])) {
                    $monthlyCarSums[$roleId][$ym] = ['units' => 0, 'amount' => 0];
                }
                if (!isset($monthlyMotorSums[$roleId][$ym])) {
                    $monthlyMotorSums[$roleId][$ym] = ['units' => 0, 'amount' => 0];
                }
                if (!isset($monthlyDayCounts[$roleId][$ym])) {
                    $monthlyDayCounts[$roleId][$ym] = 0;
                }

                $monthlyCarSums[$roleId][$ym]['units'] += $carUnits;
                $monthlyCarSums[$roleId][$ym]['amount'] += $carAmount;

                $monthlyMotorSums[$roleId][$ym]['units'] += $motorUnits;
                $monthlyMotorSums[$roleId][$ym]['amount'] += $motorAmount;

                $monthlyDayCounts[$roleId][$ym]++;
            }
        }

        // compute monthly targets ≈ 30x average daily realization for that month
        foreach ($roleIds as $roleId) {
            $months = array_keys($monthlyDayCounts[$roleId] ?? []);
            foreach ($months as $ym) {
                $parts = explode('-', $ym);
                $year = intval($parts[0]);
                $month = intval($parts[1]);

                $days = $monthlyDayCounts[$roleId][$ym] ?: 1;

                $carUnitsSum = $monthlyCarSums[$roleId][$ym]['units'] ?? 0;
                $carAmountSum = $monthlyCarSums[$roleId][$ym]['amount'] ?? 0;

                $motorUnitsSum = $monthlyMotorSums[$roleId][$ym]['units'] ?? 0;
                $motorAmountSum = $monthlyMotorSums[$roleId][$ym]['amount'] ?? 0;

                // average daily for that month (based on generated days)
                $avgCarUnits = $carUnitsSum / $days;
                $avgCarAmount = $carAmountSum / $days;

                $avgMotorUnits = $motorUnitsSum / $days;
                $avgMotorAmount = $motorAmountSum / $days;

                // set monthly targets roughly 30x the daily average
                $targetCarUnits = (int) round($avgCarUnits * 30);
                $targetCarAmount = (int) round($avgCarAmount * 30);

                $targetMotorUnits = (int) round($avgMotorUnits * 30);
                $targetMotorAmount = (int) round($avgMotorAmount * 30);

                // distribute role-level targets to users of the role evenly
                $roleUsers = DB::table('users')->where('role_id', $roleId)->get();
                if ($roleUsers->isEmpty()) {
                    continue;
                }
                $countUsers = $roleUsers->count();

                // distribute units (integers)
                $baseCarUnits = intdiv($targetCarUnits, $countUsers);
                $remCarUnits = $targetCarUnits - ($baseCarUnits * $countUsers);

                $baseMotorUnits = intdiv($targetMotorUnits, $countUsers);
                $remMotorUnits = $targetMotorUnits - ($baseMotorUnits * $countUsers);

                // distribute amounts (as integers to avoid float rounding)
                $baseCarAmount = intdiv($targetCarAmount, $countUsers);
                $remCarAmount = $targetCarAmount - ($baseCarAmount * $countUsers);

                $baseMotorAmount = intdiv($targetMotorAmount, $countUsers);
                $remMotorAmount = $targetMotorAmount - ($baseMotorAmount * $countUsers);

                $i = 0;
                foreach ($roleUsers as $u) {
                    $uCarUnits = $baseCarUnits + ($i < $remCarUnits ? 1 : 0);
                    $uMotorUnits = $baseMotorUnits + ($i < $remMotorUnits ? 1 : 0);

                    $uCarAmount = $baseCarAmount + ($i < $remCarAmount ? 1 : 0);
                    $uMotorAmount = $baseMotorAmount + ($i < $remMotorAmount ? 1 : 0);

                    DB::table('monthly_car_targets')->updateOrInsert(
                        ['user_id' => $u->id, 'year' => $year, 'month' => $month],
                        ['role_id' => $roleId, 'target_units' => $uCarUnits, 'target_amount' => $uCarAmount]
                    );

                    DB::table('monthly_motor_targets')->updateOrInsert(
                        ['user_id' => $u->id, 'year' => $year, 'month' => $month],
                        ['role_id' => $roleId, 'target_units' => $uMotorUnits, 'target_amount' => $uMotorAmount]
                    );

                    $i++;
                }
            }
        }
    }
}
