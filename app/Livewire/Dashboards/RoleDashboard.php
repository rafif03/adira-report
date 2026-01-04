<?php

namespace App\Livewire\Dashboards;

use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Role;
use App\Models\User;

class RoleDashboard extends Component
{
    public $role;
    public $roleName;
    public $date;
    public $vehicle_type = 'both';
    public $mode = 'day';
    public $metric = 'amount';
    public $tableView = 'area';

    public function mount($role = null)
    {
        if ($role) {
            $this->role = Str::lower($role);
            $this->roleName = Role::where('slug', $this->role)->value('name') ?? ucfirst($this->role);
            // default date and vehicle type
            $this->date = now()->toDateString();
            $this->vehicle_type = 'both';
            $this->mode = 'day';
            $this->metric = 'amount';
            // non-admin dashboards should show per-user details by default
            if ($this->role !== 'admin') {
                $this->tableView = 'user';
            }
            return;
        }

        // derive role from the dynamic route parameter {role}
        $this->role = Str::lower((string) (request()->route('role') ?? 'dashboard'));
        $this->roleName = Role::where('slug', $this->role)->value('name') ?? ucfirst($this->role);
        // non-admin dashboards should show per-user details by default
        if ($this->role !== 'admin') {
            $this->tableView = 'user';
        }
    }

    public function render()
    {
        if (Str::lower($this->role ?? '') === 'guest') {
            return view('livewire.dashboards.role-dashboard', [
                'role' => 'guest',
                'isGuest' => true,
            ]);
        }

        $data = ['role' => $this->role, 'date' => $this->date, 'vehicle_type' => $this->vehicle_type, 'metric' => $this->metric];

        // compute metrics per area (role) and per user for selected date/month or single day
        $now = Carbon::parse($this->date ?: now()->toDateString());
            $year = $now->year;
            $month = $now->month;

            $prev = $now->copy()->subMonth();
            $pYear = $prev->year;
            $pMonth = $prev->month;

            $dayMode = ($this->mode === 'day');
            $selectedDate = $now->toDateString();
            // compute previous-date for day-mode but avoid fallback when previous month doesn't have the same day
            $prevCandidate = $now->copy()->subMonth();
            $prevHasSameDay = ($prevCandidate->day === $now->day);
            $prevDate = $prevHasSameDay ? $prevCandidate->toDateString() : null;

            // determine roles to include: full list for admin, or only the current role for per-area dashboards
            $excluded = ['admin', 'guest'];
            if ($this->role === 'admin') {
                // Keep existing behavior for active roles, but include soft-deleted roles only if they have reports.
                $rolesActive = Role::query()->whereNotIn('slug', $excluded)->orderBy('name')->get();
            } else {
                $rolesActive = Role::query()->where('slug', $this->role)->orderBy('name')->get();
            }

            $roles = $rolesActive;

            $areas = [];
            $users = [];
            $usersCar = [];
            $usersMotor = [];

            $grand = [
                'target' => 0,
                'realization' => 0,
                'realization_prev' => 0,
                'realization_today' => 0,
            ];

            // determine period ranges
            if ($dayMode) {
                $startDate = $selectedDate;
                $endDate = $selectedDate;
                // previous day candidate (only if same day exists in prev month)
                if ($prevDate) {
                    $prevStart = $prevDate;
                    $prevEnd = $prevDate;
                } else {
                    $prevStart = null;
                    $prevEnd = null;
                }
            } else {
                $startDate = $now->copy()->startOfMonth()->toDateString();
                $endDate = $now->copy()->endOfMonth()->toDateString();
                $prevStart = $prev->copy()->startOfMonth()->toDateString();
                $prevEnd = $prev->copy()->endOfMonth()->toDateString();
            }

            // aggregated totals per role for selected period
            $carByRole = DB::table('car_reports')
                ->select('role_id', DB::raw('SUM(amount) as total'), DB::raw('SUM(units) as units_total'))
                ->whereBetween('date', [$startDate, $endDate])
                ->groupBy('role_id')
                ->get()
                ->keyBy('role_id');

            $motorByRole = DB::table('motor_reports')
                ->select('role_id', DB::raw('SUM(amount) as total'), DB::raw('SUM(units) as units_total'))
                ->whereBetween('date', [$startDate, $endDate])
                ->groupBy('role_id')
                ->get()
                ->keyBy('role_id');

            // If admin: include soft-deleted roles only when they have at least one report in the selected period.
            if ($this->role === 'admin') {
                $reportedRoleIds = collect($carByRole->keys())
                    ->merge($motorByRole->keys())
                    ->filter(fn ($id) => ! is_null($id))
                    ->unique()
                    ->values();

                if ($reportedRoleIds->isNotEmpty()) {
                    $deletedRolesWithReports = Role::onlyTrashed()
                        ->whereIn('id', $reportedRoleIds)
                        ->orderBy('name')
                        ->get();

                    if ($deletedRolesWithReports->isNotEmpty()) {
                        $roles = $rolesActive
                            ->concat($deletedRolesWithReports)
                            ->sortBy('name')
                            ->values();
                    }
                }
            }

            // aggregated totals per role for previous period (if available)
            if ($prevStart && $prevEnd) {
                $carByRolePrev = DB::table('car_reports')
                    ->select('role_id', DB::raw('SUM(amount) as total'), DB::raw('SUM(units) as units_total'))
                    ->whereBetween('date', [$prevStart, $prevEnd])
                    ->groupBy('role_id')
                    ->get()
                    ->keyBy('role_id');

                $motorByRolePrev = DB::table('motor_reports')
                    ->select('role_id', DB::raw('SUM(amount) as total'), DB::raw('SUM(units) as units_total'))
                    ->whereBetween('date', [$prevStart, $prevEnd])
                    ->groupBy('role_id')
                    ->get()
                    ->keyBy('role_id');
            } else {
                $carByRolePrev = collect();
                $motorByRolePrev = collect();
            }

            // aggregated per-user totals for selected period
            $carByUserRows = DB::table('car_reports')
                ->select('role_id','submitted_by', DB::raw('SUM(amount) as total'), DB::raw('SUM(units) as units_total'))
                ->whereBetween('date', [$startDate, $endDate])
                ->groupBy('role_id','submitted_by')
                ->get();

            $motorByUserRows = DB::table('motor_reports')
                ->select('role_id','submitted_by', DB::raw('SUM(amount) as total'), DB::raw('SUM(units) as units_total'))
                ->whereBetween('date', [$startDate, $endDate])
                ->groupBy('role_id','submitted_by')
                ->get();

            $carByUser = [];
            foreach ($carByUserRows as $rRow) {
                $carByUser[$rRow->role_id][$rRow->submitted_by] = [
                    'amount' => $rRow->total,
                    'units' => $rRow->units_total,
                ];
            }
            $motorByUser = [];
            foreach ($motorByUserRows as $rRow) {
                $motorByUser[$rRow->role_id][$rRow->submitted_by] = [
                    'amount' => $rRow->total,
                    'units' => $rRow->units_total,
                ];
            }

            // aggregated per-user totals for previous period
            if ($prevStart && $prevEnd) {
                $carByUserPrevRows = DB::table('car_reports')
                    ->select('role_id','submitted_by', DB::raw('SUM(amount) as total'), DB::raw('SUM(units) as units_total'))
                    ->whereBetween('date', [$prevStart, $prevEnd])
                    ->groupBy('role_id','submitted_by')
                    ->get();

                $motorByUserPrevRows = DB::table('motor_reports')
                    ->select('role_id','submitted_by', DB::raw('SUM(amount) as total'), DB::raw('SUM(units) as units_total'))
                    ->whereBetween('date', [$prevStart, $prevEnd])
                    ->groupBy('role_id','submitted_by')
                    ->get();

                $carByUserPrev = [];
                foreach ($carByUserPrevRows as $rRow) {
                    $carByUserPrev[$rRow->role_id][$rRow->submitted_by] = [
                        'amount' => $rRow->total,
                        'units' => $rRow->units_total,
                    ];
                }
                $motorByUserPrev = [];
                foreach ($motorByUserPrevRows as $rRow) {
                    $motorByUserPrev[$rRow->role_id][$rRow->submitted_by] = [
                        'amount' => $rRow->total,
                        'units' => $rRow->units_total,
                    ];
                }
            } else {
                $carByUserPrev = [];
                $motorByUserPrev = [];
            }

            // aggregated per-user MONTHLY targets for the selected year/month
            $carTargetRows = DB::table('monthly_car_targets')
                ->select('user_id', DB::raw('SUM(target_units) as target_units'), DB::raw('SUM(target_amount) as target_amount'))
                ->where('year', $year)
                ->where('month', $month)
                ->groupBy('user_id')
                ->get();

            $motorTargetRows = DB::table('monthly_motor_targets')
                ->select('user_id', DB::raw('SUM(target_units) as target_units'), DB::raw('SUM(target_amount) as target_amount'))
                ->where('year', $year)
                ->where('month', $month)
                ->groupBy('user_id')
                ->get();

            $carTargetsByUser = [];
            foreach ($carTargetRows as $tr) {
                $carTargetsByUser[$tr->user_id] = [
                    'units' => $tr->target_units ?? 0,
                    'amount' => $tr->target_amount ?? 0,
                ];
            }
            $motorTargetsByUser = [];
            foreach ($motorTargetRows as $tr) {
                $motorTargetsByUser[$tr->user_id] = [
                    'units' => $tr->target_units ?? 0,
                    'amount' => $tr->target_amount ?? 0,
                ];
            }

            // build areas and users using the aggregated maps
            // Keep existing behavior for active users, but include soft-deleted users only if they have reports.
            $roleIdsForUsers = $roles->pluck('id')->filter()->values();

            // IMPORTANT (admin dashboard): show users under the area/role they actually reported with
            // for the selected date/month, not their current role.
            if ($this->role === 'admin') {
                $userIdsByRole = [];

                foreach ($carByUserRows as $row) {
                    if (! $row->role_id || ! $row->submitted_by) {
                        continue;
                    }
                    $userIdsByRole[$row->role_id][] = $row->submitted_by;
                }
                foreach ($motorByUserRows as $row) {
                    if (! $row->role_id || ! $row->submitted_by) {
                        continue;
                    }
                    $userIdsByRole[$row->role_id][] = $row->submitted_by;
                }

                foreach ($userIdsByRole as $rId => $ids) {
                    $userIdsByRole[$rId] = collect($ids)->unique()->values()->all();
                }

                $allReportedUserIds = collect($userIdsByRole)
                    ->flatten()
                    ->filter()
                    ->unique()
                    ->values();

                $usersById = $allReportedUserIds->isNotEmpty()
                    ? User::withTrashed()->whereIn('id', $allReportedUserIds)->get()->keyBy('id')
                    : collect();

                $currentRoleIds = $usersById->pluck('role_id')->filter()->unique()->values();
                $currentRoleNames = $currentRoleIds->isNotEmpty()
                    ? Role::withTrashed()->whereIn('id', $currentRoleIds)->pluck('name', 'id')
                    : collect();

                $roleUsersMap = [];
                foreach ($userIdsByRole as $rId => $ids) {
                    $models = collect($ids)
                        ->map(fn ($id) => $usersById[$id] ?? null)
                        ->filter()
                        ->sortBy(fn ($u) => $u->name)
                        ->values();

                    $roleUsersMap[$rId] = $models;
                }

                // Include active users even if they have no reports (so per-user table shows all).
                // Do not duplicate users that already appear due to report snapshots.
                $activeUsersByRole = User::query()
                    ->whereIn('role_id', $roleIdsForUsers)
                    ->get()
                    ->groupBy('role_id');

                foreach ($roles as $r) {
                    $rId = $r->id;
                    $existingIds = collect($roleUsersMap[$rId] ?? collect())->pluck('id')->all();
                    $extras = collect($activeUsersByRole[$rId] ?? collect())
                        ->filter(fn ($u) => ! in_array($u->id, $existingIds))
                        ->sortBy(fn ($u) => $u->name)
                        ->values();

                    if ($extras->isNotEmpty()) {
                        $merged = collect($roleUsersMap[$rId] ?? collect())
                            ->concat($extras)
                            ->sortBy(fn ($u) => $u->name)
                            ->values();
                        $roleUsersMap[$rId] = $merged;
                    }
                }
            } else {
                $activeUsersByRole = User::query()
                    ->whereIn('role_id', $roleIdsForUsers)
                    ->get()
                    ->groupBy('role_id');

                $roleUsersMap = $activeUsersByRole;
                $currentRoleNames = collect();
            }

            foreach ($roles as $r) {
                $roleId = $r->id;
                $roleIsDeleted = isset($r->deleted_at) && $r->deleted_at !== null;
                $roleHasReports = $carByRole->has($roleId) || $motorByRole->has($roleId);

                $useUnits = ($this->metric === 'units');

                // targets (car + motor) for selected metric
                $tCarRow = DB::table('monthly_car_targets')
                    ->where('monthly_car_targets.role_id', $roleId)
                    ->where('monthly_car_targets.year', $year)
                    ->where('monthly_car_targets.month', $month)
                    ->selectRaw('COALESCE(SUM(monthly_car_targets.target_units),0) as target_units, COALESCE(SUM(monthly_car_targets.target_amount),0) as target_amount')
                    ->first();

                $tMotorRow = DB::table('monthly_motor_targets')
                    ->where('monthly_motor_targets.role_id', $roleId)
                    ->where('monthly_motor_targets.year', $year)
                    ->where('monthly_motor_targets.month', $month)
                    ->selectRaw('COALESCE(SUM(monthly_motor_targets.target_units),0) as target_units, COALESCE(SUM(monthly_motor_targets.target_amount),0) as target_amount')
                    ->first();

                $tCarMetric = $useUnits ? intval($tCarRow->target_units ?? 0) : floatval($tCarRow->target_amount ?? 0);
                $tMotorMetric = $useUnits ? intval($tMotorRow->target_units ?? 0) : floatval($tMotorRow->target_amount ?? 0);

                if ($this->vehicle_type === 'car') {
                    $target = $tCarMetric;
                } elseif ($this->vehicle_type === 'motor') {
                    $target = $tMotorMetric;
                } else {
                    $target = $tCarMetric + $tMotorMetric;
                }

                $rCarAmount = $carByRole[$roleId]->total ?? 0;
                $rMotorAmount = $motorByRole[$roleId]->total ?? 0;
                $rCarUnits = $carByRole[$roleId]->units_total ?? 0;
                $rMotorUnits = $motorByRole[$roleId]->units_total ?? 0;

                $rCarMetric = $useUnits ? intval($rCarUnits) : floatval($rCarAmount);
                $rMotorMetric = $useUnits ? intval($rMotorUnits) : floatval($rMotorAmount);

                if ($this->vehicle_type === 'car') {
                    $realization = $rCarMetric;
                } elseif ($this->vehicle_type === 'motor') {
                    $realization = $rMotorMetric;
                } else {
                    $realization = $rCarMetric + $rMotorMetric;
                }

                $realizationToday = $realization;

                $rCarPrevAmount = $carByRolePrev[$roleId]->total ?? 0;
                $rMotorPrevAmount = $motorByRolePrev[$roleId]->total ?? 0;
                $rCarPrevUnits = $carByRolePrev[$roleId]->units_total ?? 0;
                $rMotorPrevUnits = $motorByRolePrev[$roleId]->units_total ?? 0;

                $rCarPrevMetric = $useUnits ? intval($rCarPrevUnits) : floatval($rCarPrevAmount);
                $rMotorPrevMetric = $useUnits ? intval($rMotorPrevUnits) : floatval($rMotorPrevAmount);

                if ($this->vehicle_type === 'car') {
                    $realizationPrev = $rCarPrevMetric;
                } elseif ($this->vehicle_type === 'motor') {
                    $realizationPrev = $rMotorPrevMetric;
                } else {
                    $realizationPrev = $rCarPrevMetric + $rMotorPrevMetric;
                }

                $growth = $realization - $realizationPrev;
                $growthPct = $realizationPrev > 0 ? ($growth / $realizationPrev) * 100 : null;
                $achievementPct = $target > 0 ? ($realization / $target) * 100 : null;

                $areas[] = [
                    'role_id' => $roleId,
                    'role_name' => $r->name,
                    'role_deleted' => $roleIsDeleted,
                    'has_reports' => $roleHasReports,
                    'target' => $target,
                    'realization' => $realization,
                    'realization_prev' => $realizationPrev,
                    'realization_today' => $realizationToday,
                    'growth' => $growth,
                    'growth_pct' => $growthPct,
                    'achievement_pct' => $achievementPct,
                ];

                // accumulate grand totals
                $grand['target'] += $target;
                $grand['realization'] += $realization;
                $grand['realization_prev'] += $realizationPrev;
                $grand['realization_today'] += $realizationToday;

                // per-user inside role
                $roleUsers = $roleUsersMap[$roleId] ?? collect();
                foreach ($roleUsers as $u) {
                    $uId = $u->id;
                    $userIsDeleted = isset($u->deleted_at) && $u->deleted_at !== null;
                    $userHasReports = isset($carByUser[$roleId][$uId]) || isset($motorByUser[$roleId][$uId]);

                    $userMoved = false;
                    $userCurrentRoleName = null;
                    if (isset($u->role_id) && $u->role_id) {
                        $userMoved = (intval($u->role_id) !== intval($roleId));
                        $userCurrentRoleName = $currentRoleNames[$u->role_id] ?? null;
                    }

                    $uCarAmount = $carByUser[$roleId][$uId]['amount'] ?? 0;
                    $uMotorAmount = $motorByUser[$roleId][$uId]['amount'] ?? 0;
                    $uCarUnits = $carByUser[$roleId][$uId]['units'] ?? 0;
                    $uMotorUnits = $motorByUser[$roleId][$uId]['units'] ?? 0;

                    $uCarMetric = $useUnits ? intval($uCarUnits) : floatval($uCarAmount);
                    $uMotorMetric = $useUnits ? intval($uMotorUnits) : floatval($uMotorAmount);

                    if ($this->vehicle_type === 'car') {
                        $uReal = $uCarMetric;
                    } elseif ($this->vehicle_type === 'motor') {
                        $uReal = $uMotorMetric;
                    } else {
                        $uReal = $uCarMetric + $uMotorMetric;
                    }

                    $uRealToday = $uReal;

                    $uCarPrevAmount = $carByUserPrev[$roleId][$uId]['amount'] ?? 0;
                    $uMotorPrevAmount = $motorByUserPrev[$roleId][$uId]['amount'] ?? 0;
                    $uCarPrevUnits = $carByUserPrev[$roleId][$uId]['units'] ?? 0;
                    $uMotorPrevUnits = $motorByUserPrev[$roleId][$uId]['units'] ?? 0;

                    $uCarPrevMetric = $useUnits ? intval($uCarPrevUnits) : floatval($uCarPrevAmount);
                    $uMotorPrevMetric = $useUnits ? intval($uMotorPrevUnits) : floatval($uMotorPrevAmount);

                    if ($this->vehicle_type === 'car') {
                        $uRealPrev = $uCarPrevMetric;
                    } elseif ($this->vehicle_type === 'motor') {
                        $uRealPrev = $uMotorPrevMetric;
                    } else {
                        $uRealPrev = $uCarPrevMetric + $uMotorPrevMetric;
                    }

                    $uGrowth = $uReal - $uRealPrev;
                    $uGrowthPct = $uRealPrev > 0 ? ($uGrowth / $uRealPrev) * 100 : null;

                    // compute per-user monthly target (car + motor) for the selected year/month
                    $uCarTarget = ($carTargetsByUser[$uId]['amount'] ?? 0);
                    $uMotorTarget = ($motorTargetsByUser[$uId]['amount'] ?? 0);
                    $uTargetAmount = ($this->vehicle_type === 'car') ? $uCarTarget : (($this->vehicle_type === 'motor') ? $uMotorTarget : ($uCarTarget + $uMotorTarget));
                    $uTargetUnits = ($carTargetsByUser[$uId]['units'] ?? 0) + ($motorTargetsByUser[$uId]['units'] ?? 0);

                    $uTargetMetric = $useUnits ? intval($uTargetUnits) : floatval($uTargetAmount);

                    $uAchievement = $uTargetMetric > 0 ? ($uRealToday / $uTargetMetric) * 100 : null;

                    $users[] = [
                        'role_id' => $roleId,
                        'role_name' => $r->name,
                        'role_deleted' => $roleIsDeleted,
                        'has_reports' => $userHasReports,
                        'user_id' => $uId,
                        'user_name' => $u->name,
                        'user_deleted' => $userIsDeleted,
                        'user_moved' => $userMoved,
                        'user_current_role_name' => $userCurrentRoleName,
                        'realization' => $uReal,
                        'realization_prev' => $uRealPrev,
                        'realization_today' => $uRealToday,
                        'growth' => $uGrowth,
                        'growth_pct' => $uGrowthPct,
                        'target' => $uTargetMetric,
                        'achievement_pct' => $uAchievement,
                    ];

                    // per-vehicle breakdowns for non-admin dashboards
                    $usersCar[] = [
                        'role_id' => $roleId,
                        'role_name' => $r->name,
                        'role_deleted' => $roleIsDeleted,
                        'has_reports' => $userHasReports,
                        'user_id' => $uId,
                        'user_name' => $u->name,
                        'user_deleted' => $userIsDeleted,
                        'user_moved' => $userMoved,
                        'user_current_role_name' => $userCurrentRoleName,
                        'realization' => $uCarMetric,
                        'realization_prev' => $uCarPrevMetric,
                        'realization_today' => $uRealToday,
                        'growth' => ($uCarMetric - $uCarPrevMetric),
                        'growth_pct' => $uCarPrevMetric > 0 ? (($uCarMetric - $uCarPrevMetric) / $uCarPrevMetric) * 100 : null,
                        'target' => ($carTargetsByUser[$uId]['amount'] ?? 0),
                        'achievement_pct' => ($carTargetsByUser[$uId]['amount'] ?? 0) > 0 ? ($uCarMetric / ($carTargetsByUser[$uId]['amount'] ?? 1)) * 100 : null,
                    ];

                    $usersMotor[] = [
                        'role_id' => $roleId,
                        'role_name' => $r->name,
                        'role_deleted' => $roleIsDeleted,
                        'has_reports' => $userHasReports,
                        'user_id' => $uId,
                        'user_name' => $u->name,
                        'user_deleted' => $userIsDeleted,
                        'user_moved' => $userMoved,
                        'user_current_role_name' => $userCurrentRoleName,
                        'realization' => $uMotorMetric,
                        'realization_prev' => $uMotorPrevMetric,
                        'realization_today' => $uRealToday,
                        'growth' => ($uMotorMetric - $uMotorPrevMetric),
                        'growth_pct' => $uMotorPrevMetric > 0 ? (($uMotorMetric - $uMotorPrevMetric) / $uMotorPrevMetric) * 100 : null,
                        'target' => ($motorTargetsByUser[$uId]['amount'] ?? 0),
                        'achievement_pct' => ($motorTargetsByUser[$uId]['amount'] ?? 0) > 0 ? ($uMotorMetric / ($motorTargetsByUser[$uId]['amount'] ?? 1)) * 100 : null,
                    ];
                }
            }

            // compute grand totals and derived percentages
            $grandTotals = [
                'target' => $grand['target'],
                'realization' => $grand['realization'],
                'realization_prev' => $grand['realization_prev'],
                'realization_today' => $grand['realization_today'],
            ];

            $grandGrowth = $grandTotals['realization'] - $grandTotals['realization_prev'];
            $grandGrowthPct = $grandTotals['realization_prev'] > 0 ? ($grandGrowth / $grandTotals['realization_prev']) * 100 : null;
            $grandAchievementPct = $grandTotals['target'] > 0 ? ($grandTotals['realization_today'] / $grandTotals['target']) * 100 : null;

            // prepare a display label for header based on mode
            if ($this->mode === 'month') {
                $displayLabel = $now->format('Y-m');
            } else {
                $displayLabel = $now->toDateString();
            }

            $data['adminMetrics'] = [
                'areas' => $areas,
                'users' => $users,
                'users_car' => $usersCar,
                'users_motor' => $usersMotor,
                'year' => $year,
                'month' => $month,
                'prev_year' => $pYear,
                'prev_month' => $pMonth,
                'display_label' => $displayLabel,
                'totals' => array_merge($grandTotals, [
                    'growth' => $grandGrowth,
                    'growth_pct' => $grandGrowthPct,
                    'achievement_pct' => $grandAchievementPct,
                ]),
            ];

        // end metric computation

        return view('livewire.dashboards.role-dashboard', $data);
    }
}
