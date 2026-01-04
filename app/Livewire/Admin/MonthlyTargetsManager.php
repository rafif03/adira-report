<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\MonthlyCarTarget;
use App\Models\MonthlyMotorTarget;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MonthlyTargetsManager extends Component
{
    public $year;
    public $month;
    public $user_id;
    public $target_units = 0;
    public $target_amount = 0.00;
    public $vehicle_type = 'car';

    public $targets;
    public $editingUserId = null;
    public $editingTargetId = null;
    public $editingRoleId = null;
    public $expandedRoles = [];
    public $showSavedConfirmation = false;
    public $showDeletedConfirmation = false;

    public $usersByRole = [];
    public $showConflictModal = false;
    public $conflictMessage = null;

    public $showAdminRestrictionModal = false;
    public $adminRestrictionMessage = null;

    protected function isAdminUserId($userId): bool
    {
        $u = User::with('role')->find($userId);
        if (! $u) return false;
        $slug = strtolower($u->role?->slug ?? $u->role?->name ?? '');
        return $slug === 'admin';
    }

    public function mount()
    {
        $now = now();
        $this->year = $now->year;
        $this->month = $now->month;
        $this->loadTargets();
    }

    public function updatedYear()
    {
        $this->loadTargets();
    }

    public function updatedMonth()
    {
        $this->loadTargets();
    }

    public function updatedVehicleType()
    {
        $this->loadTargets();
    }

    protected function rules()
    {
        return [
            'year' => 'required|integer|min:2000',
            'month' => 'required|integer|min:1|max:12',
            'user_id' => 'required|exists:users,id',
            'vehicle_type' => 'required|in:car,motor',
            'target_units' => 'nullable|integer|min:0',
            'target_amount' => 'nullable|numeric|min:0',
        ];
    }

    public function loadTargets()
    {
        // exclude administrative roles from targets
        $excludedRoleIds = Role::whereIn('slug', ['admin', 'guest'])->pluck('id')->toArray();
        $excludedUserIds = User::whereIn('role_id', $excludedRoleIds)->pluck('id')->toArray();

        $model = $this->vehicle_type === 'motor' ? MonthlyMotorTarget::class : MonthlyCarTarget::class;

        $query = $model::with('user', 'role')
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->orderBy('user_id');

        if (! empty($excludedUserIds)) {
            $query->whereNotIn('user_id', $excludedUserIds);
        }

        $this->targets = $query->get();
    }

    protected function computeUsersByRole($roles)
    {
        $rolesById = $roles->keyBy('id');

        $excludedRoleIds = Role::whereIn('slug', ['admin', 'guest'])->pluck('id')->toArray();
        $users = User::query()
            ->whereNotIn('role_id', $excludedRoleIds)
            ->with('role')
            ->get(['id', 'name', 'role_id']);

        $usersByRole = [];
        foreach ($roles as $role) {
            $usersByRole[$role->id] = [];
        }

        $userIds = $users->pluck('id')->all();
        if (empty($userIds)) {
            return $usersByRole;
        }

        $monthStart = Carbon::create($this->year, $this->month, 1)->startOfMonth()->toDateString();
        $monthEnd = Carbon::create($this->year, $this->month, 1)->endOfMonth()->toDateString();

        $effectiveRoleByUserId = [];

        // If a monthly target already exists (car or motor) for the month, use its role_id
        // so targets remain tied to the historical area even when the user moves.
        $carTargetRoles = DB::table('monthly_car_targets')
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->whereIn('user_id', $userIds)
            ->pluck('role_id', 'user_id')
            ->toArray();

        $motorTargetRoles = DB::table('monthly_motor_targets')
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->whereIn('user_id', $userIds)
            ->pluck('role_id', 'user_id')
            ->toArray();

        $targetRoleByUserId = $carTargetRoles + $motorTargetRoles;

        $carRows = DB::table('car_reports')
            ->select('submitted_by as user_id', 'role_id', 'date')
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->whereIn('submitted_by', $userIds)
            ->orderBy('date', 'desc')
            ->get();

        $motorRows = DB::table('motor_reports')
            ->select('submitted_by as user_id', 'role_id', 'date')
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->whereIn('submitted_by', $userIds)
            ->orderBy('date', 'desc')
            ->get();

        $reportRows = $carRows
            ->concat($motorRows)
            ->sortByDesc('date')
            ->values();

        foreach ($reportRows as $row) {
            $uId = intval($row->user_id ?? 0);
            $rId = intval($row->role_id ?? 0);
            if ($uId && $rId && ! array_key_exists($uId, $effectiveRoleByUserId)) {
                $effectiveRoleByUserId[$uId] = $rId;
            }
        }

        foreach ($users as $user) {
            $uId = intval($user->id);
            $currentRoleId = intval($user->role_id);
            $effectiveRoleId = intval($targetRoleByUserId[$uId] ?? ($effectiveRoleByUserId[$uId] ?? $currentRoleId));

            if (! $rolesById->has($effectiveRoleId)) {
                $effectiveRoleId = $currentRoleId;
            }

            if (! $rolesById->has($effectiveRoleId)) {
                continue;
            }

            $moved = $effectiveRoleId !== $currentRoleId;

            $usersByRole[$effectiveRoleId][] = [
                'id' => $uId,
                'name' => $user->name,
                'moved' => $moved,
                'current_role_name' => $moved ? ($user->role?->name) : null,
            ];
        }

        return $usersByRole;
    }

    protected function getEffectiveRoleForUser($userId)
    {
        $user = User::find($userId);
        if (! $user) return null;

        // Prefer existing monthly target role_id (car or motor)
        $targetRole = DB::table('monthly_car_targets')
            ->where('user_id', $userId)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->value('role_id');
        if ($targetRole) {
            return intval($targetRole);
        }

        $targetRole = DB::table('monthly_motor_targets')
            ->where('user_id', $userId)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->value('role_id');
        if ($targetRole) {
            return intval($targetRole);
        }

        $monthStart = Carbon::create($this->year, $this->month, 1)->startOfMonth()->toDateString();
        $monthEnd = Carbon::create($this->year, $this->month, 1)->endOfMonth()->toDateString();

        $carRow = DB::table('car_reports')
            ->where('submitted_by', $userId)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->orderBy('date', 'desc')
            ->first();

        $motorRow = DB::table('motor_reports')
            ->where('submitted_by', $userId)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->orderBy('date', 'desc')
            ->first();

        $last = null;
        if ($carRow && $motorRow) {
            $last = $carRow->date >= $motorRow->date ? $carRow : $motorRow;
        } elseif ($carRow) {
            $last = $carRow;
        } elseif ($motorRow) {
            $last = $motorRow;
        }

        if ($last && ! empty($last->role_id)) {
            return intval($last->role_id);
        }

        return intval($user->role_id ?? 0);
    }

    public function dismissConflictModal()
    {
        $this->showConflictModal = false;
        $this->conflictMessage = null;
    }

    public function dismissAdminRestrictionModal()
    {
        $this->showAdminRestrictionModal = false;
        $this->adminRestrictionMessage = null;
    }

    public function toggleRole($roleId)
    {
        if (in_array($roleId, $this->expandedRoles)) {
            $this->expandedRoles = array_values(array_diff($this->expandedRoles, [$roleId]));
        } else {
            $this->expandedRoles[] = $roleId;
        }
    }

    public function editTarget($userId)
    {
        $model = $this->vehicle_type === 'motor' ? MonthlyMotorTarget::class : MonthlyCarTarget::class;
        $t = $model::where('user_id', $userId)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        if ($t) {
            $this->editingTargetId = $t->id;
            $this->user_id = $t->user_id;
            $this->target_units = $t->target_units;
            $this->target_amount = $t->target_amount;
        } else {
            $this->editingTargetId = null;
            $this->user_id = $userId;
            $this->target_units = 0;
            $this->target_amount = 0;
        }

        // enable inline editing for this user's row
        $this->editingUserId = $userId;
    }

    public function editTargetForRole($roleId, $userId)
    {
        $currentId = Auth::id();
        if ($currentId && (int) $userId === (int) $currentId) {
            $this->adminRestrictionMessage = 'Admin tidak bisa menginputkan target bulanan untuk dirinya sendiri.';
            $this->showAdminRestrictionModal = true;
            return;
        }

        if ($this->isAdminUserId($userId)) {
            $this->adminRestrictionMessage = 'Admin tidak bisa menginputkan target bulanan untuk user dengan role Admin.';
            $this->showAdminRestrictionModal = true;
            return;
        }

        $this->editingRoleId = $roleId;
        $this->editTarget($userId);
    }

    public function cancelEdit()
    {
        $this->editingUserId = null;
        $this->editingTargetId = null;
        $this->editingRoleId = null;
        $this->user_id = null;
        $this->target_units = 0;
        $this->target_amount = 0.00;
    }

    public function saveTarget()
    {
        $this->validate();
        $model = $this->vehicle_type === 'motor' ? MonthlyMotorTarget::class : MonthlyCarTarget::class;
        // determine user id for this save
        $user = \App\Models\User::find($this->editingUserId ?? $this->user_id);
        $userId = $user->id ?? $this->user_id;

        $currentId = Auth::id();
        if ($currentId && (int) $userId === (int) $currentId) {
            $this->adminRestrictionMessage = 'Admin tidak bisa menginputkan target bulanan untuk dirinya sendiri.';
            $this->showAdminRestrictionModal = true;
            return;
        }

        if ($userId && $this->isAdminUserId($userId)) {
            $this->adminRestrictionMessage = 'Admin tidak bisa menginputkan target bulanan untuk user dengan role Admin.';
            $this->showAdminRestrictionModal = true;
            return;
        }

        $targetRoleId = intval($this->editingRoleId ?? $this->getEffectiveRoleForUser($userId) ?? ($user?->role_id ?? 0));
        if (! $targetRoleId) {
            $this->conflictMessage = 'Tidak bisa menentukan area untuk target bulanan user ini.';
            $this->showConflictModal = true;
            return;
        }

        // Enforce tie to daily reports: if the user already has reports in this month,
        // targets must be assigned to the same area.
        $monthStart = Carbon::create($this->year, $this->month, 1)->startOfMonth()->toDateString();
        $monthEnd = Carbon::create($this->year, $this->month, 1)->endOfMonth()->toDateString();

        $carReportRoles = DB::table('car_reports')
            ->where('submitted_by', $userId)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->distinct()
            ->pluck('role_id')
            ->filter()
            ->map(fn ($v) => intval($v))
            ->unique()
            ->values()
            ->all();

        $motorReportRoles = DB::table('motor_reports')
            ->where('submitted_by', $userId)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->distinct()
            ->pluck('role_id')
            ->filter()
            ->map(fn ($v) => intval($v))
            ->unique()
            ->values()
            ->all();

        $reportRoleIds = array_values(array_unique(array_merge($carReportRoles, $motorReportRoles)));
        foreach ($reportRoleIds as $rid) {
            if ($rid !== $targetRoleId) {
                $reportName = Role::withTrashed()->whereKey($rid)->value('name');
                $targetName = Role::withTrashed()->whereKey($targetRoleId)->value('name');
                $this->conflictMessage = 'User ini sudah punya laporan di bulan ini untuk area lain, jadi target harus mengikuti area laporan.'
                    . (! empty($reportName) ? ' Area laporan: ' . $reportName . '.' : '')
                    . (! empty($targetName) ? ' Area target yang dipilih: ' . $targetName . '.' : '');
                $this->showConflictModal = true;
                return;
            }
        }

        // Conflict rule: user cannot have targets in two different areas for the same month/year,
        // including across car vs motor targets.
        $existingCarRoleId = DB::table('monthly_car_targets')
            ->where('user_id', $userId)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->value('role_id');

        $existingMotorRoleId = DB::table('monthly_motor_targets')
            ->where('user_id', $userId)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->value('role_id');

        $existingRoleIds = collect([$existingCarRoleId, $existingMotorRoleId])
            ->filter(fn ($v) => ! empty($v))
            ->map(fn ($v) => intval($v))
            ->unique()
            ->values()
            ->all();

        foreach ($existingRoleIds as $rid) {
            if ($rid !== $targetRoleId) {
                $existingName = Role::withTrashed()->whereKey($rid)->value('name');
                $targetName = Role::withTrashed()->whereKey($targetRoleId)->value('name');
                $this->conflictMessage = 'User ini sudah punya target bulanan untuk area lain pada periode yang sama.'
                    . (! empty($existingName) ? ' Area existing: ' . $existingName . '.' : '')
                    . (! empty($targetName) ? ' Area yang dipilih: ' . $targetName . '.' : '');
                $this->showConflictModal = true;
                return;
            }
        }

        // prevent duplicate per-user entries for same year/month
        $existingQuery = $model::where('user_id', $userId)
            ->where('year', $this->year)
            ->where('month', $this->month);
        if ($this->editingTargetId) {
            $existingQuery->where('id', '!=', $this->editingTargetId);
        }
        if ($existingQuery->exists()) {
            $this->addError('user_id', __('A target for this user in the selected month/year already exists.'));
            return;
        }

        if ($this->editingTargetId) {
            $t = $model::find($this->editingTargetId);
            if ($t) {
                $t->update([
                    'user_id' => $userId,
                    'role_id' => $targetRoleId,
                    'year' => $this->year,
                    'month' => $this->month,
                    'target_units' => $this->target_units ?? 0,
                    'target_amount' => $this->target_amount ?? 0,
                ]);
            }
        } else {
            $model::updateOrCreate(
                ['user_id' => $userId, 'year' => $this->year, 'month' => $this->month],
                ['role_id' => $targetRoleId, 'target_units' => $this->target_units ?? 0, 'target_amount' => $this->target_amount ?? 0]
            );
        }

        $this->loadTargets();
        $this->cancelEdit();
        // show inline confirmation modal
        $this->showSavedConfirmation = true;
    }

    public function dismissSavedConfirmation()
    {
        $this->showSavedConfirmation = false;
    }

    public function deleteTarget($id)
    {
        $model = $this->vehicle_type === 'motor' ? MonthlyMotorTarget::class : MonthlyCarTarget::class;
        $t = $model::find($id);
        if ($t) {
            $t->delete();
            // trigger inline confirmation modal similar to save
            $this->showDeletedConfirmation = true;
            $this->loadTargets();
        }
    }

    public function dismissDeletedConfirmation()
    {
        $this->showDeletedConfirmation = false;
    }

    public function render()
    {
        // don't show admin/guest for monthly-targets management
        $activeRoles = Role::whereNotIn('slug', ['admin', 'guest'])->orderBy('name')->get();

        // include deleted roles if they have targets in the selected month/year
        $targetRoleIds = collect(DB::table('monthly_car_targets')
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->pluck('role_id'))
            ->merge(DB::table('monthly_motor_targets')
                ->where('year', $this->year)
                ->where('month', $this->month)
                ->pluck('role_id'))
            ->filter()
            ->unique()
            ->values();

        $deletedRolesWithTargets = $targetRoleIds->isNotEmpty()
            ? Role::onlyTrashed()->whereIn('id', $targetRoleIds)->orderBy('name')->get()
            : collect();

        $roles = $activeRoles
            ->concat($deletedRolesWithTargets)
            ->sortBy('name')
            ->values();

        $this->usersByRole = $this->computeUsersByRole($roles);

        return view('livewire.admin.monthly-targets-manager', compact('roles'));
    }
}
