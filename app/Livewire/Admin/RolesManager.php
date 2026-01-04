<?php

namespace App\Livewire\Admin;

use App\Models\Role;
use App\Models\CarReport;
use App\Models\MotorReport;
use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class RolesManager extends Component
{
    public $name;
    public $editingId = null;
    public $originalName = null;
    public $slugPreview = null;

    public $confirmingDeleteId = null;
    public $confirmingDeleteName = null;
    public $adminPassword = null;
    public $roleReportsCount = 0;
    public $roleCarReports = 0;
    public $roleMotorReports = 0;
    public $roleUsersCount = 0;

    public $showFlash = false;
    public $flashMessage = '';
    public $flashType = 'success';

    protected function rules()
    {
        $uniqueRule = 'unique:roles,name';
        if ($this->editingId) {
            $uniqueRule = 'unique:roles,name,' . $this->editingId;
        }

        return [
            'name' => ['required', 'string', 'max:255', $uniqueRule],
        ];
    }

    public function updatedName()
    {
        $this->slugPreview = Str::slug((string) $this->name);
    }

    public function render()
    {
        $roles = Role::orderBy('id', 'asc')->get();

        return view('livewire.admin.roles-manager', compact('roles'));
    }

    public function resetInput()
    {
        $this->name = null;
        $this->editingId = null;
        $this->originalName = null;
        $this->slugPreview = null;
    }

    protected function makeUniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $base = $base !== '' ? $base : 'role';

        $slug = $base;
        $i = 2;

        while (Role::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    public function create()
    {
        // If a trashed role with same name exists, restore it instead of failing unique validation.
        $existingByName = Role::withTrashed()->where('name', $this->name)->first();
        if ($existingByName) {
            if ($existingByName->trashed()) {
                $existingByName->restore();
                // ensure slug is unique among active roles
                $existingByName->slug = $this->makeUniqueSlug(Str::slug((string) $this->name), $existingByName->id);
                $existingByName->save();
                $this->flash('Role restored.', 'success');
                $this->resetInput();
                return;
            }

            $this->flash('Role already exists.', 'error');
            return;
        }

        $this->validate();

        $baseSlug = Str::slug((string) $this->name);
        $slug = $this->makeUniqueSlug($baseSlug);

        Role::create([
            'name' => $this->name,
            'slug' => $slug,
        ]);

        $this->flash('Role created.', 'success');
        $this->resetInput();
    }

    public function edit($id)
    {
        $role = Role::findOrFail($id);
        $this->editingId = $id;
        $this->name = $role->name;
        $this->originalName = $role->name;
        $this->slugPreview = $role->slug;
    }

    public function update()
    {
        if (! $this->editingId) {
            $this->flash('Nothing to update.', 'error');
            return;
        }

        $this->validate();

        $role = Role::find($this->editingId);
        if (! $role) {
            $this->flash('Role not found.', 'error');
            $this->resetInput();
            return;
        }

        $role->name = $this->name;
        $baseSlug = Str::slug((string) $this->name);
        $role->slug = $this->makeUniqueSlug($baseSlug, $role->id);
        $role->save();

        $this->flash('Role updated.', 'success');
        $this->resetInput();
    }

    public function confirmDelete($id)
    {
        $role = Role::find($id);
        if (! $role) {
            $this->flashMessage = 'Role not found.';
            $this->flashType = 'error';
            $this->showFlash = true;
            return;
        }

        // compute related report counts and user count for informative modal
        $this->roleCarReports = CarReport::where('role_id', $role->id)->count();
        $this->roleMotorReports = MotorReport::where('role_id', $role->id)->count();
        $this->roleReportsCount = $this->roleCarReports + $this->roleMotorReports;
        $this->roleUsersCount = $role->users()->count();

        $this->confirmingDeleteId = $role->id;
        $this->confirmingDeleteName = $role->name;
        $this->adminPassword = null;
    }

    public function cancelConfirm()
    {
        $this->confirmingDeleteId = null;
        $this->confirmingDeleteName = null;
    }

    public function confirmDeletion()
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        $role = Role::find($this->confirmingDeleteId);
        if (! $role) {
            $this->flashMessage = 'Role not found.';
            $this->flashType = 'error';
            $this->showFlash = true;
            $this->cancelConfirm();
            return;
        }

        if (in_array($role->slug, ['admin', 'guest'])) {
            $this->flashMessage = 'Cannot delete essential role.';
            $this->flashType = 'error';
            $this->showFlash = true;
            $this->cancelConfirm();
            return;
        }
        // block deletion if there are related daily reports
        $usersCount = $role->users()->count();
        if ($this->roleReportsCount > 0) {
            $this->flashMessage = 'Role ini memiliki laporan harian terkait (Mobil: ' . $this->roleCarReports . ', Motor: ' . $this->roleMotorReports . '). Hapus tidak diperbolehkan. Silakan hapus atau pindahkan laporan terlebih dahulu.';
            $this->flashType = 'error';
            $this->showFlash = true;
            $this->cancelConfirm();
            return;
        }

        if ($usersCount > 0) {
            $this->flashMessage = 'Tidak dapat menghapus role yang memiliki pengguna. Pindahkan atau hapus pengguna terlebih dahulu.';
            $this->flashType = 'error';
            $this->showFlash = true;
            $this->cancelConfirm();
            return;
        }

        // require admin password confirmation before force-deleting (no reports/users)
        if (! Auth::check() || ! Hash::check($this->adminPassword ?? '', Auth::user()->password)) {
            $this->addError('adminPassword', 'Password salah. Masukkan password admin untuk konfirmasi.');
            return;
        }

        $role->delete();
        $this->flashMessage = 'Role berhasil dihapus.';
        $this->flashType = 'success';
        $this->showFlash = true;
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
