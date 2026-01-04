<div class="p-4">
    <h2 class="text-2xl font-semibold mb-4">Roles Manager</h2>

    <div class="mb-4">
        <div class="mb-3">
            <flux:input wire:model.defer="name" name="name" :label="__('Name')" placeholder="Nama area (contoh: Jabo 2)" />
            @if(!empty($slugPreview))
                <p class="text-sm text-gray-500 mt-1">Slug: <strong>{{ $slugPreview }}</strong></p>
            @endif
            @if($editingId)
                <p class="text-sm text-gray-500 mt-1">Original: <strong>{{ $originalName }}</strong></p>
            @endif

            <div class="mt-2">
                @if($editingId)
                    <flux:button wire:click="update" variant="primary">{{ __('Update') }}</flux:button>
                    <flux:button wire:click="resetInput" variant="ghost">{{ __('Cancel') }}</flux:button>
                @else
                    <flux:button wire:click="create" variant="primary">{{ __('Create') }}</flux:button>
                @endif
            </div>
        </div>

        <div>
            <table class="w-full table-auto border-collapse">
                <thead>
                    <tr class="text-left">
                        <th class="px-2 py-1">ID</th>
                        <th class="px-2 py-1">Name</th>
                        <th class="px-2 py-1">Slug</th>
                        <th class="px-2 py-1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                        <tr class="border-t">
                            <td class="px-2 py-1">{{ $role->id }}</td>
                            <td class="px-2 py-1">{{ $role->name }}</td>
                            <td class="px-2 py-1">{{ $role->slug }}</td>
                            <td class="px-2 py-1">
                                <flux:button wire:click="edit({{ $role->id }})" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" size="sm" variant="ghost" class="mr-2">{{ __('Edit') }}</flux:button>
                                @if(! in_array($role->slug, ['admin','guest']))
                                    <flux:button wire:click="confirmDelete({{ $role->id }})" size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" class="text-zinc-400" disabled title="Essential role">{{ __('Delete') }}</flux:button>
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

            <div class="fixed left-1/2 top-1/3 -translate-x-1/2 -translate-y-1/2 w-full max-w-md rounded bg-white p-6 dark:bg-zinc-900 z-[100000] ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-lg">
                <h3 class="text-lg font-semibold">Konfirmasi Penghapusan</h3>
                <p class="mt-2">Apakah Anda yakin ingin menghapus area "<strong>{{ $confirmingDeleteName }}</strong>"? Tindakan ini tidak dapat dikembalikan.</p>

                @if($roleReportsCount > 0)
                    <p class="mt-3 text-sm text-red-700">Area ini memiliki laporan harian terkait:</p>
                    <ul class="text-sm ml-4 mt-1 text-zinc-700">
                        <li>Mobil: {{ $roleCarReports }}</li>
                        <li>Motor: {{ $roleMotorReports }}</li>
                    </ul>
                    <p class="mt-3 text-sm text-zinc-600">Penghapusan tidak diperbolehkan selagi laporan terkait masih ada. Silakan hapus atau pindahkan laporan terlebih dahulu.</p>
                    <div class="mt-4 flex justify-end">
                        <flux:button wire:click="cancelConfirm" variant="primary">Tutup</flux:button>
                    </div>
                @elseif($roleUsersCount > 0)
                    <p class="mt-3 text-sm text-zinc-700">Area ini masih memiliki <strong>{{ $roleUsersCount }}</strong> pengguna terdaftar. Pindahkan atau hapus pengguna terlebih dahulu sebelum menghapus area.</p>
                    <div class="mt-4 flex justify-end">
                        <flux:button wire:click="cancelConfirm" variant="primary">Tutup</flux:button>
                    </div>
                @else

                    <div class="mt-4">
                        <label class="block text-sm">Masukkan password admin untuk konfirmasi</label>
                        <input type="password" wire:model.defer="adminPassword" class="w-full rounded border px-2 py-1 mt-1" />
                        @error('adminPassword') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <flux:button wire:click="cancelConfirm" variant="ghost">Batal</flux:button>
                        <flux:button wire:click.prevent="confirmDeletion" variant="danger">Hapus</flux:button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
