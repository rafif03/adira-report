<div>
    @if (session()->has('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <h2 class="text-2xl font-semibold mb-4">Daily Report</h2>

    <div class="mb-4 flex items-center gap-4">
        <div>
            <label>Date</label>
            <input type="date" wire:model="date" wire:change="loadReports" class="rounded border px-2 py-1">
            @error('date') <span class="error">{{ $message }}</span> @enderror
        </div>

        @if($isAdmin)
            <div>
                <label>Vehicle Type</label>
                <select wire:model="vehicle_type" wire:change="loadReports" class="rounded border px-2 py-1 bg-white text-black dark:bg-zinc-900 dark:text-white">
                    <option value="car">Car</option>
                    <option value="motor">Motor</option>
                </select>
                @error('vehicle_type') <span class="error">{{ $message }}</span> @enderror
            </div>
        @endif
    </div>
    @if($isAdmin)
        @if(isset($roles) && $roles->count() > 0)
            <div class="overflow-x-auto">
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
                                $r = null;
                                if (isset($reportsAgg)) {
                                    $r = $reportsAgg->get($role->id) ?? $reportsAgg->firstWhere('role_id', $role->id) ?? null;
                                }
                            @endphp
                            <tr wire:key="role-{{ $role->id }}">
                                <td class="px-2 py-1">
                                    {{ $role->name }}
                                    @if(method_exists($role, 'trashed') && $role->trashed())
                                        <span class="text-xs text-zinc-500">(deleted area)</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1">{{ optional($r)->units ?? '-' }}</td>
                                <td class="px-2 py-1">{{ optional($r)->amount !== null ? number_format(optional($r)->amount,2) : '-' }}</td>
                                <td class="px-2 py-1">
                                    <flux:button wire:click.prevent="toggleExpand({{ $role->id }})" variant="ghost" size="sm">
                                        @if(in_array($role->id, $expandedRoleIds))
                                            {{ __('Hide Details') }}
                                        @else
                                            {{ __('Details') }}
                                        @endif
                                    </flux:button>
                                    {{-- Summary-level delete disabled: delete per-user in Details only --}}
                                </td>
                            </tr>
                            @if(in_array($role->id, $expandedRoleIds))
                                <tr class="bg-gray-50 dark:bg-zinc-800">
                                    <td colspan="4" class="px-4 py-3">
                                        <div class="text-sm font-medium mb-2">Submissions for {{ $role->name }} on {{ $date }}</div>
                                        @if(method_exists($role, 'trashed') && $role->trashed())
                                            @php
                                                $deletedRoleReports = $reports->where('role_id', $role->id)->values();
                                            @endphp

                                            @if($deletedRoleReports->isEmpty())
                                                <div class="text-sm text-zinc-600">No submissions.</div>
                                            @else
                                                <table class="w-full table-auto border-collapse">
                                                    <thead>
                                                        <tr>
                                                            <th class="px-2 py-1 text-left">User</th>
                                                            <th class="px-2 py-1 text-left">Units</th>
                                                            <th class="px-2 py-1 text-left">Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($deletedRoleReports as $rep)
                                                            <tr class="border-t">
                                                                <td class="px-2 py-1">
                                                                    {{ $rep->submittedBy?->name ?? ('Unknown user #' . $rep->submitted_by) }}
                                                                    @if($rep->submittedBy && method_exists($rep->submittedBy, 'trashed') && $rep->submittedBy->trashed())
                                                                        <span class="text-xs text-zinc-500">(deleted user)</span>
                                                                    @endif
                                                                </td>
                                                                <td class="px-2 py-1">{{ $rep->units ?? '-' }}</td>
                                                                <td class="px-2 py-1">{{ isset($rep) ? number_format($rep->amount,2) : '-' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            @endif
                                        @else
                                            <table class="w-full table-auto border-collapse">
                                                <thead>
                                                    <tr>
                                                        <th class="px-2 py-1 text-left">User</th>
                                                        <th class="px-2 py-1 text-left">Units</th>
                                                        <th class="px-2 py-1 text-left">Amount</th>
                                                        <th class="px-2 py-1 text-left">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @php
                                                        $users = $usersByRole[$role->id] ?? collect();
                                                    @endphp
                                                    @foreach($users as $user)
                                                        @php
                                                            $userId = $user['id'];
                                                            $rep = $reports->where('role_id', $role->id)->firstWhere('submitted_by', $userId);
                                                        @endphp
                                                        <tr class="border-t">
                                                            <td class="px-2 py-1">
                                                                {{ $user['name'] }}
                                                                @if(($user['deleted'] ?? false) === true)
                                                                    <span class="text-xs text-zinc-500">(deleted user)</span>
                                                                @endif
                                                                    @if(($user['moved'] ?? false) === true)
                                                                        <span class="text-xs text-zinc-500">(pindah area{{ !empty($user['current_role_name'] ?? null) ? ': ' . $user['current_role_name'] : '' }})</span>
                                                                    @endif
                                                            </td>
                                                            @if($editingUserId === $userId)
                                                                <td class="px-2 py-1">
                                                                    <input type="number" wire:model.lazy="units" min="0" class="w-24">
                                                                    @error('units') <div class="error">{{ $message }}</div> @enderror
                                                                </td>
                                                                <td class="px-2 py-1">
                                                                    <input type="number" wire:model.lazy="amount" step="0.01" min="0" class="w-36">
                                                                    @error('amount') <div class="error">{{ $message }}</div> @enderror
                                                                </td>
                                                                <td class="px-2 py-1">
                                                                    <flux:button wire:click.prevent="saveReport" variant="primary" size="sm">Save</flux:button>
                                                                    <flux:button wire:click.prevent="cancelEdit" variant="ghost" size="sm">Cancel</flux:button>
                                                                </td>
                                                            @else
                                                                <td class="px-2 py-1">{{ $rep->units ?? '-' }}</td>
                                                                <td class="px-2 py-1">{{ isset($rep) ? number_format($rep->amount,2) : '-' }}</td>
                                                                <td class="px-2 py-1">
                                                                    <flux:button wire:click.prevent="editUserReport({{ $role->id }}, {{ $userId }})" variant="ghost" size="sm">Edit</flux:button>
                                                                    @if(isset($rep))
                                                                        <flux:button wire:click.prevent="confirmDelete({{ $rep->id }})" variant="danger" size="sm">Delete</flux:button>
                                                                    @endif
                                                                </td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @else
        {{-- Non-admin user: show direct detail tables for car and motor (only their submissions) --}}
        <div class="grid gap-6">
            <div>
                <h3 class="text-lg font-semibold mb-2">Car Submissions</h3>
                <div class="overflow-x-auto">
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
                            @forelse($carReports as $rep)
                                <tr class="border-t">
                                    <td class="px-2 py-1">{{ $rep->role?->name }}</td>
                                    <td class="px-2 py-1">{{ $rep->units ?? '-' }}</td>
                                    <td class="px-2 py-1">{{ number_format($rep->amount ?? 0,2) }}</td>
                                    <td class="px-2 py-1">
                                        <flux:button wire:click.prevent="editSpecific({{ $rep->id }}, 'car')" variant="ghost" size="sm">Edit</flux:button>
                                        <flux:button wire:click.prevent="confirmDeleteSpecific({{ $rep->id }}, 'car')" variant="danger" size="sm">Delete</flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-2 py-2 text-sm text-zinc-600">
                                        No car submissions found for {{ $date }}.
                                        <flux:button wire:click.prevent="prepareAdd('car')" class="ms-4" size="sm">Add Report</flux:button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-semibold mb-2">Motor Submissions</h3>
                <div class="overflow-x-auto">
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
                            @forelse($motorReports as $rep)
                                <tr class="border-t">
                                    <td class="px-2 py-1">{{ $rep->role?->name }}</td>
                                    <td class="px-2 py-1">{{ $rep->units ?? '-' }}</td>
                                    <td class="px-2 py-1">{{ number_format($rep->amount ?? 0,2) }}</td>
                                    <td class="px-2 py-1">
                                        <flux:button wire:click.prevent="editSpecific({{ $rep->id }}, 'motor')" variant="ghost" size="sm">Edit</flux:button>
                                        <flux:button wire:click.prevent="confirmDeleteSpecific({{ $rep->id }}, 'motor')" variant="danger" size="sm">Delete</flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-2 py-2 text-sm text-zinc-600">
                                        No motor submissions found for {{ $date }}.
                                        <flux:button wire:click.prevent="prepareAdd('motor')" class="ms-4" size="sm">Add Report</flux:button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    @endif

    {{-- Saved confirmation modal --}}
    @if($showSavedConfirmation)
        <div class="fixed inset-0 flex items-center justify-center">
            <div class="fixed inset-0 bg-black/80 z-[9998]"></div>

            <div class="fixed left-1/2 top-1/3 -translate-x-1/2 -translate-y-1/2 w-full max-w-md rounded p-6 bg-zinc-50 text-black dark:bg-zinc-900 dark:text-white z-[100000] ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-lg">
                <h3 class="text-lg font-semibold mb-2">{{ __('Daily report saved.') }}</h3>
                <div class="flex justify-end">
                    <flux:button wire:click.prevent="dismissSavedConfirmation" variant="primary">OK</flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- Conflict modal: shown when admin tries to input report in different area for same date --}}
    @if($showConflictModal)
        <div class="fixed inset-0 flex items-center justify-center">
            <div class="fixed inset-0 bg-black/60 z-[9998]"></div>
            <div class="fixed left-1/2 top-1/3 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg rounded p-6 bg-zinc-50 text-black dark:bg-zinc-900 dark:text-white z-[9999] ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-lg">
                <h3 class="text-lg font-semibold mb-2">Konflik Laporan</h3>
                <div class="mb-4 text-sm text-zinc-700 dark:text-zinc-300">{{ $conflictMessage }}</div>
                <div class="flex justify-end">
                    <flux:button wire:click.prevent="dismissConflictModal" variant="primary">OK</flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- Admin restriction modal: admin cannot input for self/admin users --}}
    @if($showAdminRestrictionModal)
        <div class="fixed inset-0 flex items-center justify-center">
            <div class="fixed inset-0 bg-black/60 z-[9998]"></div>
            <div class="fixed left-1/2 top-1/3 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg rounded p-6 bg-zinc-50 text-black dark:bg-zinc-900 dark:text-white z-[9999] ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-lg">
                <h3 class="text-lg font-semibold mb-2">Tidak Diizinkan</h3>
                <div class="mb-4 text-sm text-zinc-700 dark:text-zinc-300">{{ $adminRestrictionMessage }}</div>
                <div class="flex justify-end">
                    <flux:button wire:click.prevent="dismissAdminRestrictionModal" variant="primary">OK</flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- Add/Edit modal for creating or editing a report (non-admin only) --}}
    @if(! $isAdmin && ($showAddModal || $editingReportId))
        <div class="fixed inset-0 flex items-center justify-center">
            <div class="fixed inset-0 bg-black/60 z-[9998]"></div>

            <div class="fixed left-1/2 top-1/3 -translate-x-1/2 -translate-y-1/2 w-full max-w-md rounded p-6 bg-zinc-50 text-black dark:bg-zinc-900 dark:text-white z-[100000] ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-lg">
                <h3 class="text-lg font-semibold mb-2">{{ $editingReportId ? 'Edit' : 'Add' }} Report ({{ ucfirst($vehicle_type) }})</h3>

                <div class="mb-3">
                    <label class="block text-sm">Units</label>
                    <input type="number" wire:model.defer="units" min="0" class="w-full rounded border px-2 py-1" />
                    @error('units') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="block text-sm">Amount</label>
                    <input type="number" wire:model.defer="amount" step="0.01" min="0" class="w-full rounded border px-2 py-1" />
                    @error('amount') <div class="error">{{ $message }}</div> @enderror
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button wire:click.prevent="cancelEdit" variant="ghost">Cancel</flux:button>
                    <flux:button wire:click.prevent="saveReport" variant="primary">Save</flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- Deleted confirmation modal --}}
    @if($showDeletedConfirmation)
        <div class="fixed inset-0 flex items-center justify-center">
            <div class="fixed inset-0 bg-black/80 z-[9998]"></div>

            <div class="fixed left-1/2 top-1/3 -translate-x-1/2 -translate-y-1/2 w-full max-w-md rounded p-6 bg-zinc-50 text-black dark:bg-zinc-900 dark:text-white z-[100000] ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-lg">
                <h3 class="text-lg font-semibold mb-2">{{ __('Daily report deleted.') }}</h3>
                <div class="flex justify-end">
                    <flux:button wire:click.prevent="dismissDeletedConfirmation" variant="primary">OK</flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- Confirm before delete modal --}}
    @if($confirmingDeleteId)
        <div class="fixed inset-0 flex items-center justify-center">
            <div class="fixed inset-0 bg-black/80 z-[9998]"></div>

            <div class="fixed left-1/2 top-1/3 -translate-x-1/2 -translate-y-1/2 w-full max-w-md rounded p-6 bg-zinc-50 text-black dark:bg-zinc-900 dark:text-white z-[100000] ring-1 ring-zinc-200 dark:ring-zinc-700 shadow-lg">
                <h3 class="text-lg font-semibold mb-2">{{ __('Confirm delete') }}</h3>
                <p class="mb-4 text-sm text-zinc-600">{{ __('Are you sure you want to delete this report? This action cannot be undone.') }}</p>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click.prevent="cancelDelete" variant="ghost">{{ __('Cancel') }}</flux:button>
                    <flux:button wire:click.prevent="performDelete" variant="danger">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
