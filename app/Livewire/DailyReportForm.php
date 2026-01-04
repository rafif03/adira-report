<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\Role;
use App\Models\CarReport;
use App\Models\MotorReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DailyReportForm extends Component
{
    public $date;
    public $role_id;
    public $units = 0;
    public $amount = 0.00;
    public $vehicle_type = 'car';
    public $reportsAgg;

    public $isAdmin = false;
    public $reports;
    public $carReports = [];
    public $motorReports = [];
    public $editingRoleId = null;
    public $editingReportId = null;
    public $editingUserId = null;
    public $showSavedConfirmation = false;
    public $showDeletedConfirmation = false;
    public $confirmingDeleteId = null;
    public $confirmingDeleteVehicleType = null;
    public $expandedRoleIds = [];
    public $usersByRole = [];
    public $showAddModal = false;
    // modal for conflict when attempting to input report in a different area
    public $showConflictModal = false;
    public $conflictMessage = '';

    // modal for admin restrictions (cannot input for admin users or self)
    public $showAdminRestrictionModal = false;
    public $adminRestrictionMessage = '';

    public function mount()
    {
        $this->date = now()->toDateString();

        $user = Auth::user();
        $this->isAdmin = $user && strtolower($user->role?->slug ?? $user->role?->name ?? '') === 'admin';

        if ($user && ! $this->isAdmin) {
            $roleName = strtolower($user->role?->slug ?? $user->role?->name ?? 'guest');
            if ($roleName === 'guest') {
                return redirect()->route('dashboard');
            }
        }

        if ($user && ! $this->isAdmin) {
            $this->role_id = $user->role_id;
        }

        $this->loadReports();
    }

    public function updatedDate()
    {
        $this->loadReports();
    }

    public function updatedVehicleType()
    {
        $this->loadReports();
    }

    public function loadReports()
    {
        $date = Carbon::parse($this->date)->toDateString();

        // Admins see all reports for the date; other users see only their own submissions
        if ($this->isAdmin) {
            $this->carReports = \App\Models\CarReport::with('role', 'submittedBy')
                ->whereDate('date', $date)
                ->orderBy('role_id')
                ->get();

            $this->motorReports = \App\Models\MotorReport::with('role', 'submittedBy')
                ->whereDate('date', $date)
                ->orderBy('role_id')
                ->get();

            // keep legacy $reports for admin single-table uses
            $this->reports = $this->vehicle_type === 'motor' ? $this->motorReports : $this->carReports;

            // build aggregated totals per role for admin summary table
            $carAgg = $this->carReports->groupBy('role_id')->map(function($group, $roleId) {
                return (object)[
                    'role_id' => intval($roleId),
                    'units' => intval($group->sum('units')),
                    'amount' => floatval($group->sum('amount')),
                ];
            })->values();

            $motorAgg = $this->motorReports->groupBy('role_id')->map(function($group, $roleId) {
                return (object)[
                    'role_id' => intval($roleId),
                    'units' => intval($group->sum('units')),
                    'amount' => floatval($group->sum('amount')),
                ];
            })->values();

            // merge according to selected vehicle_type or both
            if ($this->vehicle_type === 'car') {
                $this->reportsAgg = $carAgg->keyBy('role_id');
            } elseif ($this->vehicle_type === 'motor') {
                $this->reportsAgg = $motorAgg->keyBy('role_id');
            } else {
                // sum car + motor per role
                $merged = [];
                foreach ($carAgg as $c) {
                    $merged[$c->role_id] = ['units' => $c->units, 'amount' => $c->amount];
                }
                foreach ($motorAgg as $m) {
                    if (! isset($merged[$m->role_id])) {
                        $merged[$m->role_id] = ['units' => $m->units, 'amount' => $m->amount];
                    } else {
                        $merged[$m->role_id]['units'] += $m->units;
                        $merged[$m->role_id]['amount'] += $m->amount;
                    }
                }
                $this->reportsAgg = collect();
                foreach ($merged as $roleId => $vals) {
                    $this->reportsAgg->put($roleId, (object)[
                        'role_id' => intval($roleId),
                        'units' => intval($vals['units']),
                        'amount' => floatval($vals['amount']),
                    ]);
                }
            }

            // ensure reportsAgg is always a collection (not null)
            $this->reportsAgg = $this->reportsAgg ?? collect();

            // Build per-role user lists from report rows so users appear under the area they
            // actually reported for the selected date (using report.role_id), not by their current role.
            $userIdsByRole = [];
            foreach ($this->carReports as $r) {
                if ($r->role_id && $r->submitted_by) {
                    $userIdsByRole[$r->role_id][] = $r->submitted_by;
                }
            }
            foreach ($this->motorReports as $r) {
                if ($r->role_id && $r->submitted_by) {
                    $userIdsByRole[$r->role_id][] = $r->submitted_by;
                }
            }

            foreach ($userIdsByRole as $rid => $ids) {
                $userIdsByRole[$rid] = collect($ids)->filter()->unique()->values()->all();
            }

            $allReportedUserIds = collect($userIdsByRole)->flatten()->filter()->unique()->values();

            $usersById = $allReportedUserIds->isNotEmpty()
                ? User::withTrashed()->whereIn('id', $allReportedUserIds)->get()->keyBy('id')
                : collect();

            // gather current role ids for reported users so we can show "moved to" label
            $currentRoleIds = $usersById->pluck('role_id')->filter()->unique()->values();
            $currentRoleNames = $currentRoleIds->isNotEmpty()
                ? Role::withTrashed()->whereIn('id', $currentRoleIds)->pluck('name', 'id')
                : collect();

            $currentRoleSlugs = $currentRoleIds->isNotEmpty()
                ? Role::withTrashed()->whereIn('id', $currentRoleIds)->pluck('slug', 'id')
                : collect();

            $users = collect();
            $currentAdminId = Auth::id();

            foreach ($userIdsByRole as $rId => $ids) {
                $models = collect($ids)
                    ->map(fn($id) => $usersById[$id] ?? null)
                    ->filter()
                    ->filter(function ($u) use ($currentRoleSlugs, $currentAdminId) {
                        if ($currentAdminId && (int) $u->id === (int) $currentAdminId) return false;
                        $slug = strtolower($currentRoleSlugs[$u->role_id] ?? '');
                        return $slug !== 'admin';
                    })
                    ->sortBy(fn($u) => $u->name)
                    ->map(function ($u) use ($rId, $currentRoleNames) {
                        $moved = false;
                        $currentName = null;
                        if (isset($u->role_id) && $u->role_id) {
                            $moved = (int) $u->role_id !== (int) $rId;
                            $currentName = $currentRoleNames[$u->role_id] ?? null;
                        }

                        return [
                            'id' => $u->id,
                            'name' => $u->name,
                            'deleted' => $u->deleted_at !== null,
                            'moved' => $moved,
                            'current_role_name' => $currentName,
                        ];
                    })->values()->all();

                $users->put($rId, $models);
            }

            // Also include users who belong to this role but haven't reported yet,
            // so admins can submit reports on their behalf. Do not duplicate users
            // who already appear from report rows (they should appear under the
            // role they reported with).
            $allRoles = Role::whereNotIn('slug', ['admin', 'guest'])->with('users')->get();

            foreach ($allRoles as $role) {
                $existing = collect($users->get($role->id) ?? [])->pluck('id')->all();
                foreach ($role->users as $u) {
                    if ($currentAdminId && (int) $u->id === (int) $currentAdminId) continue;
                    if (in_array($u->id, $existing)) continue;
                    // only include non-deleted users
                    if ($u->deleted_at) continue;
                    $arr = [
                        'id' => $u->id,
                        'name' => $u->name,
                        'deleted' => false,
                        'moved' => false,
                        'current_role_name' => null,
                    ];
                    $roleList = $users->get($role->id) ?? [];
                    $roleList[] = $arr;
                    $users->put($role->id, collect($roleList)->sortBy('name')->values()->all());
                }
            }

            $this->usersByRole = $users->toArray();
        } else {
            $user = auth()->user();
            $this->carReports = \App\Models\CarReport::with('role', 'submittedBy')
                ->whereDate('date', $date)
                ->where('submitted_by', $user->id)
                ->where('role_id', $user->role_id)
                ->get();

            $this->motorReports = \App\Models\MotorReport::with('role', 'submittedBy')
                ->whereDate('date', $date)
                ->where('submitted_by', $user->id)
                ->where('role_id', $user->role_id)
                ->get();

            // keep $reports as empty collection for non-admin (not used)
            $this->reports = collect();
            // usersByRole not needed for single-user view, but keep as empty
            $this->usersByRole = [];
        }
    }

    public function toggleExpand($roleId)
    {
        if (in_array($roleId, $this->expandedRoleIds)) {
            $this->expandedRoleIds = array_filter($this->expandedRoleIds, fn($id) => $id !== $roleId);
        } else {
            $this->expandedRoleIds[] = $roleId;
        }
    }

    public function rules()
    {
        return [
            'date' => 'required|date',
            'role_id' => 'required|exists:roles,id',
            'vehicle_type' => 'required|in:car,motor',
            'units' => 'nullable|integer|min:0',
            'amount' => 'nullable|numeric|min:0',
        ];
    }

    public function submit()
    {
        $this->validate();

        $user = Auth::user();

        if (! $this->isAdmin && $user->role_id !== (int) $this->role_id) {
            $this->addError('role_id', 'You are not authorized to submit for this role.');
            return;
        }

        // Persist to the appropriate table based on vehicle type
        $this->saveReport();
    }

    public function editReport($roleId)
    {
        // For admin this edits by role; for non-admin edit the user's own report if exists
        $user = auth()->user();
        if ($this->isAdmin) {
            $model = $this->vehicle_type === 'motor' ? MotorReport::class : CarReport::class;

            $r = $model::where('role_id', $roleId)
                ->whereDate('date', Carbon::parse($this->date)->toDateString())
                ->first();

            if ($r) {
                $this->editingReportId = $r->id;
                $this->role_id = $r->role_id;
                $this->units = $r->units;
                $this->amount = $r->amount;
            } else {
                $this->editingReportId = null;
                $this->role_id = $roleId;
                $this->units = 0;
                $this->amount = 0;
            }

            $this->editingRoleId = $roleId;
        } else {
            // non-admin: set editing to user's own report (roleId ignored)
            $r = \App\Models\CarReport::whereDate('date', Carbon::parse($this->date)->toDateString())
                ->where('submitted_by', $user->id)
                ->first();

            if ($r) {
                $this->editingReportId = $r->id;
                $this->role_id = $r->role_id;
                $this->units = $r->units;
                $this->amount = $r->amount;
                $this->vehicle_type = 'car';
            } else {
                $this->editingReportId = null;
                $this->role_id = $user->role_id;
                $this->units = 0;
                $this->amount = 0;
                $this->vehicle_type = 'car';
            }

            $this->editingRoleId = $user->role_id;
        }
    }

    public function cancelEdit()
    {
        $this->editingRoleId = null;
        $this->editingReportId = null;
        $this->editingUserId = null;
        $this->units = 0;
        $this->amount = 0.00;
        $this->role_id = $this->isAdmin ? null : $this->role_id;
        $this->showAddModal = false;
    }

    public function dismissConflictModal()
    {
        $this->showConflictModal = false;
        $this->conflictMessage = '';
    }

    public function dismissAdminRestrictionModal()
    {
        $this->showAdminRestrictionModal = false;
        $this->adminRestrictionMessage = '';
    }

    protected function isAdminUserId($userId): bool
    {
        $u = User::withTrashed()->with('role')->find($userId);
        if (! $u) return false;

        $slug = strtolower($u->role?->slug ?? $u->role?->name ?? '');
        return $slug === 'admin';
    }

    public function saveReport()
    {
        $user = Auth::user();

        $date = Carbon::parse($this->date)->toDateString();

        // Ensure role_id is set before validation for non-admin add flows
        if (! $this->isAdmin && $user) {
            $this->role_id = $user->role_id;
        }

        $this->validate();

        // Non-admin users can only create/update their own submission for their own role.
        if (! $this->isAdmin) {
            $this->role_id = $user->role_id;
            $model = $this->vehicle_type === 'motor' ? MotorReport::class : CarReport::class;

            // User-side guard: if monthly target for this month is tied to a different area,
            // block submission and instruct the user to contact admin.
            $dt = Carbon::parse($date);
            $tYear = $dt->year;
            $tMonth = $dt->month;

            $tCarRoleId = DB::table('monthly_car_targets')
                ->where('user_id', $user->id)
                ->where('year', $tYear)
                ->where('month', $tMonth)
                ->value('role_id');

            $tMotorRoleId = DB::table('monthly_motor_targets')
                ->where('user_id', $user->id)
                ->where('year', $tYear)
                ->where('month', $tMonth)
                ->value('role_id');

            $targetRoleIds = collect([$tCarRoleId, $tMotorRoleId])
                ->filter(fn ($v) => ! empty($v))
                ->map(fn ($v) => intval($v))
                ->unique()
                ->values()
                ->all();

            foreach ($targetRoleIds as $tRid) {
                if ($tRid !== (int) $this->role_id) {
                    $targetRoleName = Role::withTrashed()->whereKey($tRid)->value('name');
                    $currentRoleName = Role::withTrashed()->whereKey($this->role_id)->value('name');

                    $periodLabel = $dt->format('Y-m');
                    $msg = 'Tidak bisa submit laporan untuk tanggal ' . $date . '.';
                    $msg .= ' Target bulanan periode ' . $periodLabel . ' Anda terikat ke area berbeda.';
                    if ($targetRoleName) {
                        $msg .= ' Area target: ' . $targetRoleName . '.';
                    }
                    if ($currentRoleName) {
                        $msg .= ' Area akun Anda saat ini: ' . $currentRoleName . '.';
                    }
                    $msg .= ' Silakan hubungi Admin untuk perbaikan area/target.';

                    $this->conflictMessage = $msg;
                    $this->showConflictModal = true;
                    return;
                }
            }

            if ($this->editingReportId) {
                $r = $model::whereKey($this->editingReportId)
                    ->where('submitted_by', $user->id)
                    ->first();

                if (! $r) {
                    $this->addError('role_id', 'You are not authorized to edit this report.');
                    return;
                }

                $r->update([
                    'role_id' => $this->role_id,
                    'date' => $date,
                    'units' => $this->units ?? 0,
                    'amount' => $this->amount ?? 0,
                    'submitted_by' => $user->id,
                ]);
            } else {
                // Avoid SQLite date-string mismatches by looking up via whereDate
                $existing = $model::where('role_id', $this->role_id)
                    ->where('submitted_by', $user->id)
                    ->whereDate('date', $date)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'units' => $this->units ?? 0,
                        'amount' => $this->amount ?? 0,
                        'date' => $date,
                    ]);
                } else {
                    $model::create([
                        'role_id' => $this->role_id,
                        'date' => $date,
                        'units' => $this->units ?? 0,
                        'amount' => $this->amount ?? 0,
                        'submitted_by' => $user->id,
                    ]);
                }
            }

            $this->loadReports();
            $this->cancelEdit();
            $this->showAddModal = false;
            $this->showSavedConfirmation = true;

            return;
        }

        if (! $this->isAdmin && $user->role_id !== (int) $this->role_id) {
            $this->addError('role_id', 'You are not authorized to submit for this role.');
            return;
        }
        // Ensure correct model and submitted_by for admin or non-admin
        $model = $this->vehicle_type === 'motor' ? MotorReport::class : CarReport::class;

        // Prevent entering a report for a new area if the user already reported under a different area on the same date.
        if ($this->isAdmin) {
            $targetUserId = $this->editingUserId;
            $excludeId = null;
            $excludeModel = null;

            if ($this->editingReportId) {
                $excludeId = $this->editingReportId;
                $excludeModel = $model;
                $existingEditing = $model::find($this->editingReportId);
                if ($existingEditing && ! $targetUserId) {
                    $targetUserId = $existingEditing->submitted_by;
                }
            }

            if (! $targetUserId) {
                $targetUserId = $user?->id;
            }

            // Admin cannot submit for themselves or any admin account.
            if ($targetUserId && $user && (int) $targetUserId === (int) $user->id) {
                $this->adminRestrictionMessage = 'Admin tidak bisa menginputkan laporan atas nama dirinya sendiri.';
                $this->showAdminRestrictionModal = true;
                return;
            }

            if ($targetUserId && $this->isAdminUserId($targetUserId)) {
                $this->adminRestrictionMessage = 'Admin tidak bisa menginputkan laporan untuk user dengan role Admin.';
                $this->showAdminRestrictionModal = true;
                return;
            }

            if ($targetUserId && $this->role_id) {
                $roleId = (int) $this->role_id;

                // Enforce monthly target <-> daily report tie:
                // if the user already has monthly targets in this month for a different area, block.
                $dt = Carbon::parse($date);
                $tYear = $dt->year;
                $tMonth = $dt->month;

                $tCarRoleId = DB::table('monthly_car_targets')
                    ->where('user_id', $targetUserId)
                    ->where('year', $tYear)
                    ->where('month', $tMonth)
                    ->value('role_id');
                $tMotorRoleId = DB::table('monthly_motor_targets')
                    ->where('user_id', $targetUserId)
                    ->where('year', $tYear)
                    ->where('month', $tMonth)
                    ->value('role_id');

                $targetRoleIds = collect([$tCarRoleId, $tMotorRoleId])
                    ->filter(fn ($v) => ! empty($v))
                    ->map(fn ($v) => intval($v))
                    ->unique()
                    ->values()
                    ->all();

                foreach ($targetRoleIds as $tRid) {
                    if ($tRid !== $roleId) {
                        $tRoleName = Role::withTrashed()->whereKey($tRid)->value('name');
                        $inputRoleName = Role::withTrashed()->whereKey($roleId)->value('name');
                        $msg = 'User ini sudah punya target bulanan untuk area lain di bulan ini.';
                        if ($tRoleName) {
                            $msg .= ' Area target: ' . $tRoleName . '.';
                        }
                        if ($inputRoleName) {
                            $msg .= ' Area laporan yang dipilih: ' . $inputRoleName . '.';
                        }
                        $this->conflictMessage = $msg . ' Target dan laporan harian harus satu area di bulan yang sama.';
                        $this->showConflictModal = true;
                        return;
                    }
                }

                $carConflict = CarReport::query()
                    ->where('submitted_by', $targetUserId)
                    ->whereDate('date', $date)
                    ->where('role_id', '!=', $roleId);

                if ($excludeModel === CarReport::class && $excludeId) {
                    $carConflict->where('id', '!=', $excludeId);
                }

                $motorConflict = MotorReport::query()
                    ->where('submitted_by', $targetUserId)
                    ->whereDate('date', $date)
                    ->where('role_id', '!=', $roleId);

                if ($excludeModel === MotorReport::class && $excludeId) {
                    $motorConflict->where('id', '!=', $excludeId);
                }

                $conflict = $carConflict->first() ?: $motorConflict->first();

                if ($conflict) {
                    $conflictRoleName = Role::withTrashed()->whereKey($conflict->role_id)->value('name');
                    $msg = 'User ini sudah punya laporan pada tanggal ini untuk area lain.';
                    if ($conflictRoleName) {
                        $msg = 'User ini sudah punya laporan pada tanggal ini untuk area: ' . $conflictRoleName . '.';
                    }
                    $this->conflictMessage = $msg . ' Tidak bisa input laporan di area baru untuk tanggal yang sama.';
                    $this->showConflictModal = true;
                    return;
                }
            }
        }

        if ($this->editingReportId) {
            $r = $model::find($this->editingReportId);
            if ($r) {
                $r->update([
                    'role_id' => $this->role_id,
                    'date' => $date,
                    'units' => $this->units ?? 0,
                    'amount' => $this->amount ?? 0,
                    'submitted_by' => $r->submitted_by ?? $user?->id,
                ]);
            }
        } else {
            // For non-admin, always set submitted_by to current user
            if ($this->editingUserId) {
                $model::updateOrCreate([
                    'role_id' => $this->role_id,
                    'date' => $date,
                    'submitted_by' => $this->editingUserId,
                ], [
                    'units' => $this->units ?? 0,
                    'amount' => $this->amount ?? 0,
                    'submitted_by' => $this->editingUserId,
                ]);
            } else {
                $model::updateOrCreate([
                    'role_id' => $this->role_id,
                    'date' => $date,
                    'submitted_by' => $user?->id,
                ], [
                    'units' => $this->units ?? 0,
                    'amount' => $this->amount ?? 0,
                    'submitted_by' => $user?->id,
                ]);
            }
        }
        $this->loadReports();
        $this->cancelEdit();
        // close add modal and show saved confirmation
        $this->showAddModal = false;
        $this->showSavedConfirmation = true;
        if (method_exists($this, 'emit')) {
            $this->emit('reportSaved');
        }
    }

    public function prepareAdd($vehicleType = 'car')
    {
        $user = auth()->user();
        $this->vehicle_type = $vehicleType;
        $this->editingReportId = null;
        $this->editingUserId = null;
        $this->role_id = $user->role_id;
        $this->units = 0;
        $this->amount = 0.00;
        $this->showAddModal = true;
    }

    public function deleteReport($id)
    {
        // backwards-compatible: delete using current vehicle_type if provided as null
        $vehicleType = $this->vehicle_type;
        $model = $vehicleType === 'motor' ? MotorReport::class : CarReport::class;
        $r = $model::find($id);
        if ($r) {
            $r->delete();
            $this->loadReports();
            $this->showDeletedConfirmation = true;
        }
    }

    public function confirmDelete($id)
    {
        $this->confirmingDeleteId = $id;
        $this->confirmingDeleteVehicleType = null;
    }

    public function confirmDeleteSpecific($id, $vehicleType)
    {
        $this->confirmingDeleteId = $id;
        $this->confirmingDeleteVehicleType = $vehicleType;
    }

    public function performDelete()
    {
        if (! $this->confirmingDeleteId) return;
        // delete using explicit vehicle type if present
        if ($this->confirmingDeleteVehicleType) {
            $model = $this->confirmingDeleteVehicleType === 'motor' ? MotorReport::class : CarReport::class;
            $r = $model::find($this->confirmingDeleteId);
            if ($r) {
                $r->delete();
            }
            $this->confirmingDeleteVehicleType = null;

            $this->loadReports();
            $this->showDeletedConfirmation = true;
        } else {
            $this->deleteReport($this->confirmingDeleteId);
        }

        $this->confirmingDeleteId = null;
    }

    public function cancelDelete()
    {
        $this->confirmingDeleteId = null;
        $this->confirmingDeleteVehicleType = null;
    }

    public function editUserReport($roleId, $userId)
    {
        if ($this->isAdmin) {
            $currentId = Auth::id();
            if ($currentId && (int) $userId === (int) $currentId) {
                $this->adminRestrictionMessage = 'Admin tidak bisa menginputkan laporan atas nama dirinya sendiri.';
                $this->showAdminRestrictionModal = true;
                return;
            }

            if ($this->isAdminUserId($userId)) {
                $this->adminRestrictionMessage = 'Admin tidak bisa menginputkan laporan untuk user dengan role Admin.';
                $this->showAdminRestrictionModal = true;
                return;
            }
        }

        $model = $this->vehicle_type === 'motor' ? MotorReport::class : CarReport::class;

        $r = $model::where('role_id', $roleId)
            ->whereDate('date', Carbon::parse($this->date)->toDateString())
            ->where('submitted_by', $userId)
            ->first();

        if ($r) {
            $this->editingReportId = $r->id;
            $this->role_id = $r->role_id;
            $this->units = $r->units;
            $this->amount = $r->amount;
        } else {
            $this->editingReportId = null;
            $this->role_id = $roleId;
            $this->units = 0;
            $this->amount = 0;
        }

        $this->editingRoleId = $roleId;
        $this->editingUserId = $userId;
    }

    public function editSpecific($reportId, $vehicleType)
    {
        $model = $vehicleType === 'motor' ? MotorReport::class : CarReport::class;
        $r = $model::find($reportId);
        if ($r) {
            $this->editingReportId = $r->id;
            $this->role_id = $r->role_id;
            $this->units = $r->units;
            $this->amount = $r->amount;
            $this->vehicle_type = $vehicleType;
            $this->editingRoleId = $r->role_id;
        }
    }

    public function dismissSavedConfirmation()
    {
        $this->showSavedConfirmation = false;
    }

    public function dismissDeletedConfirmation()
    {
        $this->showDeletedConfirmation = false;
    }

    public function render()
    {
        // Admins can choose roles except admin/guest; other users only see their own role
        if ($this->isAdmin) {
            $activeRoles = Role::whereNotIn('slug', ['admin', 'guest'])->orderBy('name')->get();

            // Include deleted roles only if they have reports for the selected date
            $date = Carbon::parse($this->date ?: now()->toDateString())->toDateString();

            $reportedRoleIds = collect($this->carReports)->pluck('role_id')
                ->merge(collect($this->motorReports)->pluck('role_id'))
                ->filter()
                ->unique()
                ->values();

            $deletedRolesWithReports = $reportedRoleIds->isNotEmpty()
                ? Role::onlyTrashed()->whereIn('id', $reportedRoleIds)->orderBy('name')->get()
                : collect();

            $roles = $activeRoles
                ->concat($deletedRolesWithReports)
                ->sortBy('name')
                ->values();
        } else {
            $roles = Role::where('id', $this->role_id)->get();
        }
        return view('livewire.daily-report-form', compact('roles'));
    }
}
