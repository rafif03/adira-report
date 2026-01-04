<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed daily reports from 2025-12-01 to 2026-01-31
        $start = Carbon::create(2025,12,1);
        $end = Carbon::create(2026,1,31);

        $roles = DB::table('roles')->select('id', 'slug')->get();

        foreach ($roles as $role) {
            if (in_array($role->slug, ['admin', 'guest'])) continue;
            $id = $role->id;

            // get users for this role
            $userIds = DB::table('users')->where('role_id', $id)->pluck('id')->toArray();

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                // create 1-2 car reports per role per day
                $carSubmissions = count($userIds) ? rand(1, min(2, count($userIds))) : 1;
                $carSubmitters = $userIds;
                shuffle($carSubmitters);
                $carSubmitters = array_slice($carSubmitters, 0, $carSubmissions);
                foreach ($carSubmitters as $submittedBy) {
                    DB::table('car_reports')->insert([
                        'role_id' => $id,
                        'date' => $date->toDateString(),
                        'units' => rand(5,80),
                        'amount' => rand(1000000,50000000),
                        'submitted_by' => $submittedBy,
                    ]);
                }

                // create 1-2 motor reports per role per day
                $motorSubmissions = count($userIds) ? rand(1, min(2, count($userIds))) : 1;
                $motorSubmitters = $userIds;
                shuffle($motorSubmitters);
                $motorSubmitters = array_slice($motorSubmitters, 0, $motorSubmissions);
                foreach ($motorSubmitters as $submittedBy) {
                    DB::table('motor_reports')->insert([
                        'role_id' => $id,
                        'date' => $date->toDateString(),
                        'units' => rand(20,300),
                        'amount' => rand(500000,15000000),
                        'submitted_by' => $submittedBy,
                    ]);
                }
            }
        }
    }
}
