<div class="p-4 bg-white rounded shadow-sm dark:bg-zinc-900">
    <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-100">Form Pengisian Target Bulanan</h2>

    <form wire:submit.prevent="saveTarget" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Role</label>
                <select wire:model.defer="role_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-0 bg-white text-black dark:bg-zinc-900 dark:text-white">
                    <option value="">-- Pilih Role --</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                @error('role_id') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Tipe Kendaraan</label>
                <select wire:model.defer="vehicle_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-0 bg-white text-black dark:bg-zinc-900 dark:text-white">
                    <option value="car">Mobil</option>
                    <option value="motor">Motor</option>
                </select>
                @error('vehicle_type') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Bulan / Tahun</label>
                <div class="flex gap-2">
                    <select wire:model.defer="month" class="mt-1 block w-1/2 rounded-md border-gray-300 shadow-sm focus:ring-0 bg-white text-black dark:bg-zinc-900 dark:text-white">
                        @foreach(range(1,12) as $m)
                            <option value="{{ $m }}">{{ str_pad($m,2,'0',STR_PAD_LEFT) }}</option>
                        @endforeach
                    </select>
                    <input type="number" wire:model.defer="year" min="2000" max="2100" class="mt-1 block w-1/2 rounded-md border-gray-300 shadow-sm bg-white text-black dark:bg-zinc-900 dark:text-white" value="{{ date('Y') }}">
                </div>
                @error('month') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                @error('year') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Target (jumlah)</label>
            <input type="number" wire:model.defer="amount" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-white text-black dark:bg-zinc-900 dark:text-white" />
            @error('amount') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Simpan Target</button>
            <button type="button" wire:click="resetForm" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 dark:bg-zinc-800 dark:text-gray-200">Bersihkan</button>
        </div>
    </form>
</div>
