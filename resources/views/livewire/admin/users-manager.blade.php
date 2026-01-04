<div class="p-4">
    <h2 class="text-2xl font-semibold mb-4">Users Manager</h2>

    <div class="mb-4">
        <label class="block text-sm mb-1">Filter Role</label>
        <select wire:model="filterRoleId" class="w-full max-w-sm rounded border px-2 py-1 bg-white text-black dark:bg-zinc-900 dark:text-white">
            <option value="">-- All roles --</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}">{{ $role->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="mb-4">
        <div class="mb-3">
            @if($editingUserId)
                <p class="text-sm mb-1">User: <strong>{{ $originalUserName ?? '—' }}</strong></p>
                <p class="text-sm text-gray-500 mb-2">Original role: <strong>{{ $originalRoleName ?? '-' }}</strong></p>

                <label class="block text-sm mb-1">Role</label>
                <select wire:model.defer="selectedRoleId" class="w-full rounded border px-2 py-1 bg-white text-black dark:bg-zinc-900 dark:text-white">
                    <option value="">-- Select role --</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>

                <div class="mt-2">
                    <flux:button wire:click="updateRole" variant="primary">{{ __('Update Role') }}</flux:button>
                    <flux:button wire:click="resetInput" variant="ghost">{{ __('Cancel') }}</flux:button>
                </div>
            @else
                <p class="text-sm text-gray-500">Pilih user di daftar untuk mengubah role.</p>
            @endif
        </div>

        <div>
            <table class="w-full table-auto border-collapse">
                <thead>
                    <tr class="text-left">
                        <th class="px-2 py-1">ID</th>
                        <th class="px-2 py-1">Name</th>
                        <th class="px-2 py-1">Email</th>
                        <th class="px-2 py-1">Role</th>
                        <th class="px-2 py-1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr class="border-t">
                            <td class="px-2 py-1">{{ $user->id }}</td>
                            <td class="px-2 py-1">{{ $user->name }}</td>
                            <td class="px-2 py-1">{{ $user->email }}</td>
                            <td class="px-2 py-1">{{ $user->role?->name ?? '-' }}</td>
                            <td class="px-2 py-1">
                                <flux:button wire:click="edit({{ $user->id }})" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" size="sm" variant="ghost" class="mr-2">{{ __('Edit role') }}</flux:button>
                                @if($user->role?->slug === 'admin')
                                    <flux:button size="sm" variant="ghost" disabled>Admin</flux:button>
                                @else
                                    <flux:button wire:click="confirmDelete({{ $user->id }})" size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($showFlash)
        <div class="fixed inset-0 flex items-center justify-center">
            <div class="fixed inset-0 bg-black/80 z-[9998]"></div>

            <div class="fixed left-1/2 top-1/3 -translate-x-1/2 -translate-y-1/2 w-full max-w-md rounded p-6 bg-zinc-50 text-black dark:bg-zinc-900 dark:text-white z-[100000] ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-lg">
                <h3 class="text-lg font-semibold mb-2">{{ $flashType === 'success' ? 'Success' : 'Error' }}</h3>
                <p class="mb-4">{{ $flashMessage }}</p>

                <div class="flex justify-end">
                    <flux:button wire:click="hideFlash" variant="primary">OK</flux:button>
                </div>
            </div>
        </div>
    @endif

    @if($confirmingDeleteId)
        <div class="fixed inset-0 flex items-center justify-center">
            <div class="fixed inset-0 bg-black/80 z-[9998]"></div>

            <div class="fixed left-1/2 top-1/3 -translate-x-1/2 -translate-y-1/2 w-full max-w-md rounded bg-white p-6 dark:bg-zinc-900 z-[100000]">
                <h3 class="text-lg font-semibold">Confirm deletion</h3>
                <p class="mt-2">
                    User: <strong>{{ $confirmingDeleteUserName ?? '—' }}</strong><br>
                    Email: <strong>{{ $confirmingDeleteUserEmail ?? '—' }}</strong>
                </p>

                @if($confirmingDeleteWillForce)
                    <p class="mt-3 text-sm">
                        Akun ini akan <strong>dihapus permanen (force delete)</strong> karena user belum pernah menginput laporan harian.
                        Target bulanan user juga akan ikut terhapus (cascading).
                    </p>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        Setelah force delete, email user bisa digunakan lagi untuk register.
                    </p>
                @else
                    <p class="mt-3 text-sm">
                        Akun ini akan <strong>di-soft delete</strong> karena user sudah pernah menginput laporan harian.
                        Riwayat laporan tetap tersimpan.
                    </p>
                    <p class="mt-2 text-sm text-red-600">
                        Email user tidak bisa digunakan lagi untuk register.
                    </p>
                @endif

                <flux:subheading class="mt-3">Please enter your admin password to confirm deletion.</flux:subheading>
                <div class="mt-2">
                    <flux:input wire:model.defer="adminPassword" :label="__('Admin Password')" type="password" />
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <flux:button wire:click="cancelConfirm" variant="ghost">{{ __('Cancel') }}</flux:button>
                    <flux:button wire:click.prevent="confirmDeletion" variant="danger">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
    // Listen for Livewire emit and scroll window to top so edit form is visible
    if (typeof Livewire !== 'undefined') {
        Livewire.on && Livewire.on('scrollToTop', function () {
            try {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (e) {
                window.scrollTo(0, 0);
            }
        });
    }
</script>
