<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\Role;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UsersManager extends Component
{
    public $editingUserId = null;
    public $selectedRoleId = null;
    public $originalUserName = null;
    public $originalRoleName = null;

    public $filterRoleId = '';

    public $confirmingDeleteId = null;
    public $adminPassword = '';

    public $confirmingDeleteWillForce = false;
    public $confirmingDeleteHasReports = false;
    public $confirmingDeleteUserName = null;
    public $confirmingDeleteUserEmail = null;

    public $showFlash = false;
    public $flashMessage = '';
    public $flashType = 'success';

    public function render()
    {
        $roles = Role::orderBy('id', 'asc')->get();

        $usersQuery = User::with('role')->orderBy('id', 'asc');
        if ($this->filterRoleId !== '' && $this->filterRoleId !== null) {
            $usersQuery->where('role_id', (int) $this->filterRoleId);
        }
        $users = $usersQuery->get();

        return view('livewire.admin.users-manager', compact('users', 'roles'));
    }

    public function resetInput()
    {
        $this->editingUserId = null;
        $this->selectedRoleId = null;
        $this->originalUserName = null;
        $this->originalRoleName = null;
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $this->editingUserId = $user->id;
        $this->selectedRoleId = $user->role_id;
        $this->originalUserName = $user->name;
        $this->originalRoleName = $user->role?->name ?? '-';
    }

    public function updateRole()
    {
        if (! $this->editingUserId) {
            $this->flash('Nothing to update.', 'error');
            return;
        }

        $this->validate([
            'selectedRoleId' => ['required', 'exists:roles,id'],
        ]);

        $user = User::find($this->editingUserId);
        if (! $user) {
            $this->flash('User not found.', 'error');
            $this->resetInput();
            return;
        }

        $role = Role::find($this->selectedRoleId);

        $oldRoleId = $user->role_id;

        $user->role()->associate($role);
        $user->save();

        // If user moved and user has never submitted any daily reports,
        // remove any monthly targets that were assigned under the previous role.
        if ($oldRoleId && $oldRoleId !== $role->id) {
            $hasCarReports = DB::table('car_reports')->where('submitted_by', $user->id)->exists();
            $hasMotorReports = DB::table('motor_reports')->where('submitted_by', $user->id)->exists();

            if (! $hasCarReports && ! $hasMotorReports) {
                DB::table('monthly_car_targets')
                    ->where('user_id', $user->id)
                    ->where('role_id', $oldRoleId)
                    ->delete();

                DB::table('monthly_motor_targets')
                    ->where('user_id', $user->id)
                    ->where('role_id', $oldRoleId)
                    ->delete();

                $this->flash('User role updated. Old monthly targets (previous area) removed because user had no reports.', 'success');
                $this->resetInput();
                return;
            }
        }

        $this->flash('User role updated.', 'success');
        $this->resetInput();
    }

    public function confirmDelete($id)
    {
        $user = User::with('role')->find($id);
        if (! $user) {
            $this->flash('User not found.', 'error');
            return;
        }

        if ($user->role?->slug === 'admin') {
            $this->flash('Admin tidak bisa menghapus akun admin.', 'error');
            return;
        }

        $hasCarReports = DB::table('car_reports')->where('submitted_by', $user->id)->exists();
        $hasMotorReports = DB::table('motor_reports')->where('submitted_by', $user->id)->exists();
        $hasReports = $hasCarReports || $hasMotorReports;

        $this->confirmingDeleteHasReports = $hasReports;
        $this->confirmingDeleteWillForce = ! $hasReports;
        $this->confirmingDeleteUserName = $user->name;
        $this->confirmingDeleteUserEmail = $user->email;

        $this->confirmingDeleteId = $user->id;
    }

    public function cancelConfirm()
    {
        $this->confirmingDeleteId = null;
        $this->adminPassword = '';
        $this->confirmingDeleteWillForce = false;
        $this->confirmingDeleteHasReports = false;
        $this->confirmingDeleteUserName = null;
        $this->confirmingDeleteUserEmail = null;
    }

    public function confirmDeletion()
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        $user = User::with('role')->find($this->confirmingDeleteId);
        if (! $user) {
            $this->flash('User not found.', 'error');
            $this->cancelConfirm();
            return;
        }

        if ($user->role?->slug === 'admin') {
            $this->flash('Admin tidak bisa menghapus akun admin.', 'error');
            $this->cancelConfirm();
            return;
        }

        // Require admin password confirmation
        $this->validate(["adminPassword" => ['required', 'string']]);

        $admin = Auth::user();
        if (! $admin || ! Hash::check($this->adminPassword, $admin->password)) {
            $this->flash('Invalid admin password.', 'error');
            $this->adminPassword = '';
            return;
        }

        $hasCarReports = DB::table('car_reports')->where('submitted_by', $user->id)->exists();
        $hasMotorReports = DB::table('motor_reports')->where('submitted_by', $user->id)->exists();
        $hasReports = $hasCarReports || $hasMotorReports;

        try {
            if ($hasReports) {
                $user->delete();
                $this->flash('User dihapus (soft delete). Email tidak bisa digunakan lagi untuk mendaftar ulang.', 'success');
            } else {
                $user->forceDelete();
                $this->flash('User dihapus permanen (force delete). User bisa mendaftar ulang dengan email yang sama.', 'success');
            }
        } catch (\Throwable $e) {
            $this->flash('Gagal menghapus user: ' . $e->getMessage(), 'error');
            return;
        }

        $this->cancelConfirm();
    }

    public function hideFlash()
    {
        $this->showFlash = false;
    }

    public function flash(string $message, string $type = 'success')
    {
        $this->flashMessage = $message;
        $this->flashType = $type;
        $this->showFlash = true;
    }
}
