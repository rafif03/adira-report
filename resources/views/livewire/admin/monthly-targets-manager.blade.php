<div>
    <h2 class="text-2xl font-semibold mb-4">{{ __('Monthly Targets') }}</h2>

    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <label>{{ __('Vehicle') }}</label>
            <select wire:model.lazy="vehicle_type" class="mx-2 rounded border px-2 py-1 bg-white text-black dark:bg-zinc-900 dark:text-white">
                <option value="car">Car</option>
                <option value="motor">Motor</option>
            </select>

            <label>{{ __('Year') }}</label>
            <input type="number" wire:model.lazy="year" class="mx-2 w-24">

            <label>{{ __('Month') }}</label>
            <select wire:model.lazy="month" class="mx-2 rounded border px-2 py-1 bg-white text-black dark:bg-zinc-900 dark:text-white">
                @for($m=1;$m<=12;$m++)
                    <option value="{{ $m }}">{{ $m }}</option>
                @endfor
            </select>
        </div>
    </div>

    <table class="w-full table-auto border-collapse">
        <thead>
            <tr>
                <th class="px-2 py-1 text-left">Area</th>
                <th class="px-2 py-1 text-left">Units</th>
                <th class="px-2 py-1 text-left">Amount</th>
                <th class="px-2 py-1 text-left">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($roles as $role)
                @php
                    // sum targets for users that belong to this role
                    $areaUnits = 0;
                    $areaAmount = 0;
                    $users = $usersByRole[$role->id] ?? [];
                    foreach ($users as $u) {
                        $uRows = $targets->where('user_id', $u['id'])->values();
                        foreach ($uRows as $ur) {
                            $areaUnits += intval($ur->target_units ?? 0);
                            $areaAmount += floatval($ur->target_amount ?? 0);
                        }
                    }
                @endphp

                <tr class="bg-green-50">
                    <td class="px-2 py-1 font-semibold">{{ $role->name }}</td>
                    <td class="px-2 py-1 font-semibold">{{ $areaUnits ?: '-' }}</td>
                    <td class="px-2 py-1 font-semibold">{{ $areaAmount ? number_format($areaAmount,2) : '-' }}</td>
                    <td class="px-2 py-1 font-semibold">
                        <flux:button wire:click.prevent="toggleRole({{ $role->id }})" variant="ghost" size="sm">
                            @if(in_array($role->id, $expandedRoles))
                                {{ __('Hide Details') }}
                            @else
                                {{ __('Details') }}
                            @endif
                        </flux:button>
                    </td>
                </tr>

                {{-- list users (RBM) under this area --}}
                @if(in_array($role->id, $expandedRoles))
                    @foreach(($usersByRole[$role->id] ?? []) as $user)
                    @php $t = $targets->firstWhere('user_id', $user['id']); @endphp
                    <tr wire:key="user-{{ $user['id'] }}" class="border-t @if($loop->last) border-b @endif">
                        <td class="px-2 py-1">&nbsp;&nbsp;{{ $user['name'] }} @if(($user['moved'] ?? false) === true) <span class="text-xs text-zinc-500">(pindah area{{ !empty($user['current_role_name'] ?? null) ? ': ' . $user['current_role_name'] : '' }})</span>@endif</td>

                        @if($editingUserId === $user['id'])
                            <td class="px-2 py-1">
                                <input type="number" wire:model.lazy="target_units" min="0" class="w-24">
                                @error('target_units') <div class="error">{{ $message }}</div> @enderror
                            </td>
                            <td class="px-2 py-1">
                                <input type="number" wire:model.lazy="target_amount" step="0.01" min="0" class="w-36">
                                @error('target_amount') <div class="error">{{ $message }}</div> @enderror
                            </td>
                            <td class="px-2 py-1">
                                <flux:button wire:click.prevent="saveTarget" variant="primary" size="sm">{{ __('Save') }}</flux:button>
                                <flux:button wire:click.prevent="cancelEdit" variant="ghost" size="sm">{{ __('Cancel') }}</flux:button>
                                @error('user_id') <div class="error mt-1">{{ $message }}</div> @enderror
                            </td>
                        @else
                            <td class="px-2 py-1">{{ $t?->target_units ?? '-' }}</td>
                            <td class="px-2 py-1">{{ $t ? number_format($t->target_amount,2) : '-' }}</td>
                            <td class="px-2 py-1">
                                <flux:button wire:click="editTargetForRole({{ $role->id }}, {{ $user['id'] }})" variant="ghost" size="sm">{{ $t ? __('Edit') : __('Add') }}</flux:button>
                                @if($t)
                                    <flux:button wire:click.prevent="deleteTarget({{ $t->id }})" variant="danger" size="sm">{{ __('Delete') }}</flux:button>
                                @endif
                            </td>
                        @endif
                    </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>

{{-- Saved confirmation modal --}}
@if($showSavedConfirmation)
    <div class="fixed inset-0 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/80 z-[9998]"></div>

        <div class="fixed left-1/2 top-1/3 -translate-x-1/2 -translate-y-1/2 w-full max-w-md rounded p-6 bg-zinc-50 text-black dark:bg-zinc-900 dark:text-white z-[100000] ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-lg">
            <h3 class="text-lg font-semibold mb-2">{{ __('Monthly target saved.') }}</h3>
            <div class="flex justify-end">
                <flux:button wire:click.prevent="dismissSavedConfirmation" variant="primary">OK</flux:button>
            </div>
        </div>
    </div>
@endif
@if($showDeletedConfirmation)
    <div class="fixed inset-0 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/80 z-[9998]"></div>

        <div class="fixed left-1/2 top-1/3 -translate-x-1/2 -translate-y-1/2 w-full max-w-md rounded p-6 bg-zinc-50 text-black dark:bg-zinc-900 dark:text-white z-[100000] ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-lg">
            <h3 class="text-lg font-semibold mb-2">{{ __('Monthly target deleted.') }}</h3>
            <div class="flex justify-end">
                <flux:button wire:click.prevent="dismissDeletedConfirmation" variant="primary">OK</flux:button>
            </div>
        </div>
    </div>
@endif
    @if($showConflictModal)
        <div class="fixed inset-0 flex items-center justify-center">
            <div class="fixed inset-0 bg-black/80 z-[9998]"></div>

            <div class="fixed left-1/2 top-1/3 -translate-x-1/2 -translate-y-1/2 w-full max-w-md rounded p-6 bg-zinc-50 text-black dark:bg-zinc-900 dark:text-white z-[100000] ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-lg">
                <h3 class="text-lg font-semibold mb-2">{{ __('Conflict: different area detected') }}</h3>
                <p class="mb-4">{{ $conflictMessage }}</p>
                <div class="flex justify-end">
                    <flux:button wire:click.prevent="dismissConflictModal" variant="primary">OK</flux:button>
                </div>
            </div>
        </div>
    @endif

    @if($showAdminRestrictionModal)
        <div class="fixed inset-0 flex items-center justify-center">
            <div class="fixed inset-0 bg-black/80 z-[9998]"></div>

            <div class="fixed left-1/2 top-1/3 -translate-x-1/2 -translate-y-1/2 w-full max-w-md rounded p-6 bg-zinc-50 text-black dark:bg-zinc-900 dark:text-white z-[100000] ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-lg">
                <h3 class="text-lg font-semibold mb-2">Tidak Diizinkan</h3>
                <p class="mb-4">{{ $adminRestrictionMessage }}</p>
                <div class="flex justify-end">
                    <flux:button wire:click.prevent="dismissAdminRestrictionModal" variant="primary">OK</flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
