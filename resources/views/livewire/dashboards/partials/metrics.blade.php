@if(isset($adminMetrics))
    <div class="flex items-center gap-4 mt-4">
        <div>
            <label class="text-sm">{{ $mode === 'month' ? 'Bulan' : 'Tanggal' }}</label>
            @if($mode === 'month')
                <input type="month" wire:model="date" wire:change="$refresh" class="block mt-1 border rounded px-2 py-1 bg-white text-black dark:bg-zinc-900 dark:text-white" />
            @else
                <input type="date" wire:model="date" wire:change="$refresh" class="block mt-1 border rounded px-2 py-1 bg-white text-black dark:bg-zinc-900 dark:text-white" />
            @endif
        </div>
        @if(($role ?? '') === 'admin')
            <div>
                <label class="text-sm">Jenis Kendaraan</label>
                <select wire:model="vehicle_type" wire:change="$refresh" class="block mt-1 border rounded px-2 py-1 bg-white text-black dark:bg-zinc-900 dark:text-white">
                    <option value="both">Keduanya</option>
                    <option value="car">Mobil</option>
                    <option value="motor">Motor</option>
                </select>
            </div>
        @endif
        <div>
            <label class="text-sm">Mode</label>
            <select wire:model="mode" wire:change="$refresh" class="block mt-1 border rounded px-2 py-1 bg-white text-black dark:bg-zinc-900 dark:text-white">
                <option value="day">Harian</option>
                <option value="month">Bulanan</option>
            </select>
        </div>
        <div>
            <label class="text-sm">Tampilan</label>
            <select wire:model="metric" wire:change="$refresh" class="block mt-1 border rounded px-2 py-1 bg-white text-black dark:bg-zinc-900 dark:text-white">
                <option value="amount">Rupiah</option>
                <option value="units">Unit</option>
            </select>
        </div>
    </div>

    <div class="mt-6">
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-lg font-semibold">{{ ($tableView ?? 'area') === 'area' ? 'Area Summary' : 'Per-RBM / User Detail' }} ({{ $adminMetrics['display_label'] }})</h2>
            @if(($role ?? '') === 'admin')
                <div class="flex items-center gap-2">
                    <flux:button wire:click.prevent="$set('tableView','area')" variant="ghost" size="sm" class="{{ ($tableView ?? 'area') === 'area' ? 'font-semibold' : '' }}">Area Summary</flux:button>
                    <flux:button wire:click.prevent="$set('tableView','user')" variant="ghost" size="sm" class="{{ ($tableView ?? '') === 'user' ? 'font-semibold' : '' }}">Per-RBM / User Detail</flux:button>
                </div>
            @endif
        </div>

        <div class="overflow-x-auto bg-white dark:bg-zinc-800 border rounded">
            @if(($tableView ?? 'area') === 'area')
                <table class="w-full text-sm">
                    <thead class="text-left">
                        <tr>
                            <th class="px-3 py-2">Area</th>
                            <th class="px-3 py-2">Target</th>
                            <th class="px-3 py-2">Realisasi</th>
                            <th class="px-3 py-2">Realisasi M-1</th>
                            <th class="px-3 py-2">Growth</th>
                            <th class="px-3 py-2">Growth %</th>
                            <th class="px-3 py-2">Achievement %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($adminMetrics['areas'] as $a)
                            @if(($a['role_deleted'] ?? false) === true && ($a['has_reports'] ?? false) !== true)
                                @continue
                            @endif
                            <tr class="border-t transition-colors duration-150 hover:shadow-sm {{ (isset($a['growth']) && $a['growth'] < 0) ? 'text-red-700 dark:text-rose-300' : ((isset($a['growth']) && $a['growth'] > 0) ? 'text-green-800 dark:text-green-200' : '') }}">
                                <td class="px-3 py-2">{{ $a['role_name'] }}@if(($a['role_deleted'] ?? false) === true) <span class="text-xs text-zinc-500">(deleted area)</span>@endif</td>
                                <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($a['target'] ?? 0) }}@else{{ number_format($a['target'] ?? 0, 2) }}@endif</td>
                                <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($a['realization'] ?? 0) }}@else{{ number_format($a['realization'] ?? 0, 2) }}@endif</td>
                                <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($a['realization_prev'] ?? 0) }}@else{{ number_format($a['realization_prev'] ?? 0, 2) }}@endif</td>
                                <td class="px-3 py-2">@if(isset($a['growth']) && $a['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($a['growth']) && $a['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">@if(($metric ?? 'amount') === 'units'){{ number_format($a['growth'] ?? 0) }}@else{{ number_format($a['growth'] ?? 0, 2) }}@endif</span></td>
                                <td class="px-3 py-2">@if(isset($a['growth']) && $a['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($a['growth']) && $a['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">{{ $a['growth_pct'] !== null ? number_format($a['growth_pct'],2) . '%' : '-' }}</span></td>
                                <td class="px-3 py-2">{{ $a['achievement_pct'] !== null ? number_format($a['achievement_pct'],2) . '%' : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-zinc-50 dark:bg-zinc-900">
                        <tr class="border-t font-semibold {{ (isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] < 0) ? 'text-red-700 dark:text-rose-300' : ((isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] > 0) ? 'text-green-800 dark:text-green-200' : '') }}">
                            <td class="px-3 py-2">Grand Total</td>
                            <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($adminMetrics['totals']['target'] ?? 0) }}@else{{ number_format($adminMetrics['totals']['target'] ?? 0, 2) }}@endif</td>
                            <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($adminMetrics['totals']['realization'] ?? 0) }}@else{{ number_format($adminMetrics['totals']['realization'] ?? 0, 2) }}@endif</td>
                            <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($adminMetrics['totals']['realization_prev'] ?? 0) }}@else{{ number_format($adminMetrics['totals']['realization_prev'] ?? 0, 2) }}@endif</td>
                            <td class="px-3 py-2">@if(isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">@if(($metric ?? 'amount') === 'units'){{ number_format($adminMetrics['totals']['growth'] ?? 0) }}@else{{ number_format($adminMetrics['totals']['growth'] ?? 0, 2) }}@endif</span></td>
                            <td class="px-3 py-2">@if(isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">{{ isset($adminMetrics['totals']['growth_pct']) ? number_format($adminMetrics['totals']['growth_pct'],2) . '%' : '-' }}</span></td>
                            <td class="px-3 py-2">{{ isset($adminMetrics['totals']['achievement_pct']) ? number_format($adminMetrics['totals']['achievement_pct'],2) . '%' : '-' }}</td>
                        </tr>
                    </tfoot>
                </table>
            @elseif((($role ?? '') !== 'admin'))
                    <div class="p-4">
                        <h3 class="font-semibold mb-2">Mobil</h3>
                        <table class="w-full text-sm mb-6">
                            <thead class="text-left">
                                <tr>
                                    <th class="px-3 py-2">Area</th>
                                    <th class="px-3 py-2">User</th>
                                    <th class="px-3 py-2">Target</th>
                                    <th class="px-3 py-2">Realisasi</th>
                                    <th class="px-3 py-2">Realisasi M-1</th>
                                    <th class="px-3 py-2">Growth</th>
                                    <th class="px-3 py-2">Growth %</th>
                                    <th class="px-3 py-2">Achievement %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($adminMetrics['users_car'] as $u)
                                    @if((($u['role_deleted'] ?? false) === true || ($u['user_deleted'] ?? false) === true) && ($u['has_reports'] ?? false) !== true)
                                        @continue
                                    @endif
                                    <tr class="border-t transition-colors duration-150 hover:shadow-sm {{ (isset($u['growth']) && $u['growth'] < 0) ? 'text-red-700 dark:text-rose-300' : ((isset($u['growth']) && $u['growth'] > 0) ? 'text-green-800 dark:text-green-200' : '') }}">
                                        <td class="px-3 py-2">{{ $u['role_name'] }}@if(($u['role_deleted'] ?? false) === true) <span class="text-xs text-zinc-500">(deleted)</span>@endif</td>
                                        <td class="px-3 py-2">{{ $u['user_name'] }}@if(($u['user_deleted'] ?? false) === true) <span class="text-xs text-zinc-500">(deleted user)</span>@endif @if(($u['user_moved'] ?? false) === true) <span class="text-xs text-zinc-500">(pindah area{{ !empty($u['user_current_role_name'] ?? null) ? ': ' . $u['user_current_role_name'] : '' }})</span>@endif</td>
                                        <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['target'] ?? 0) }}@else{{ number_format($u['target'] ?? 0, 2) }}@endif</td>
                                        <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['realization'] ?? 0) }}@else{{ number_format($u['realization'] ?? 0, 2) }}@endif</td>
                                        <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['realization_prev'] ?? 0) }}@else{{ number_format($u['realization_prev'] ?? 0, 2) }}@endif</td>
                                        <td class="px-3 py-2">@if(isset($u['growth']) && $u['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($u['growth']) && $u['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['growth'] ?? 0) }}@else{{ number_format($u['growth'] ?? 0, 2) }}@endif</span></td>
                                        <td class="px-3 py-2">@if(isset($u['growth']) && $u['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($u['growth']) && $u['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">{{ $u['growth_pct'] !== null ? number_format($u['growth_pct'],2) . '%' : '-' }}</span></td>
                                        <td class="px-3 py-2">{{ $u['achievement_pct'] !== null ? number_format($u['achievement_pct'],2) . '%' : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <h3 class="font-semibold mb-2">Motor</h3>
                        <table class="w-full text-sm">
                            <thead class="text-left">
                                <tr>
                                    <th class="px-3 py-2">Area</th>
                                    <th class="px-3 py-2">User</th>
                                    <th class="px-3 py-2">Target</th>
                                    <th class="px-3 py-2">Realisasi</th>
                                    <th class="px-3 py-2">Realisasi M-1</th>
                                    <th class="px-3 py-2">Growth</th>
                                    <th class="px-3 py-2">Growth %</th>
                                    <th class="px-3 py-2">Achievement %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($adminMetrics['users_motor'] as $u)
                                    @if((($u['role_deleted'] ?? false) === true || ($u['user_deleted'] ?? false) === true) && ($u['has_reports'] ?? false) !== true)
                                        @continue
                                    @endif
                                    <tr class="border-t transition-colors duration-150 hover:shadow-sm {{ (isset($u['growth']) && $u['growth'] < 0) ? 'text-red-700 dark:text-rose-300' : ((isset($u['growth']) && $u['growth'] > 0) ? 'text-green-800 dark:text-green-200' : '') }}">
                                        <td class="px-3 py-2">{{ $u['role_name'] }}@if(($u['role_deleted'] ?? false) === true) <span class="text-xs text-zinc-500">(deleted)</span>@endif</td>
                                        <td class="px-3 py-2">{{ $u['user_name'] }}@if(($u['user_deleted'] ?? false) === true) <span class="text-xs text-zinc-500">(deleted user)</span>@endif @if(($u['user_moved'] ?? false) === true) <span class="text-xs text-zinc-500">(pindah area{{ !empty($u['user_current_role_name'] ?? null) ? ': ' . $u['user_current_role_name'] : '' }})</span>@endif</td>
                                        <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['target'] ?? 0) }}@else{{ number_format($u['target'] ?? 0, 2) }}@endif</td>
                                        <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['realization'] ?? 0) }}@else{{ number_format($u['realization'] ?? 0, 2) }}@endif</td>
                                        <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['realization_prev'] ?? 0) }}@else{{ number_format($u['realization_prev'] ?? 0, 2) }}@endif</td>
                                        <td class="px-3 py-2">@if(isset($u['growth']) && $u['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($u['growth']) && $u['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['growth'] ?? 0) }}@else{{ number_format($u['growth'] ?? 0, 2) }}@endif</span></td>
                                        <td class="px-3 py-2">@if(isset($u['growth']) && $u['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($u['growth']) && $u['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">{{ $u['growth_pct'] !== null ? number_format($u['growth_pct'],2) . '%' : '-' }}</span></td>
                                        <td class="px-3 py-2">{{ $u['achievement_pct'] !== null ? number_format($u['achievement_pct'],2) . '%' : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold mt-6 mb-2">Grand Total</h3>
                        <table class="w-full text-sm">
                    <thead class="text-left">
                        <tr>
                            <th class="px-3 py-2">Area</th>
                            <th class="px-3 py-2">User</th>
                            <th class="px-3 py-2">Target</th>
                            <th class="px-3 py-2">Realisasi</th>
                            <th class="px-3 py-2">Realisasi M-1</th>
                            <th class="px-3 py-2">Growth</th>
                            <th class="px-3 py-2">Growth %</th>
                            <th class="px-3 py-2">Achievement %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($adminMetrics['users'] as $u)
                            @if((($u['role_deleted'] ?? false) === true || ($u['user_deleted'] ?? false) === true) && ($u['has_reports'] ?? false) !== true)
                                @continue
                            @endif
                            <tr class="border-t transition-colors duration-150 hover:shadow-sm {{ (isset($u['growth']) && $u['growth'] < 0) ? 'text-red-700 dark:text-rose-300' : ((isset($u['growth']) && $u['growth'] > 0) ? 'text-green-800 dark:text-green-200' : '') }}">
                                <td class="px-3 py-2">{{ $u['role_name'] }}@if(($u['role_deleted'] ?? false) === true) <span class="text-xs text-zinc-500">(deleted)</span>@endif</td>
                                <td class="px-3 py-2">{{ $u['user_name'] }}@if(($u['user_deleted'] ?? false) === true) <span class="text-xs text-zinc-500">(deleted user)</span>@endif @if(($u['user_moved'] ?? false) === true) <span class="text-xs text-zinc-500">(pindah area{{ !empty($u['user_current_role_name'] ?? null) ? ': ' . $u['user_current_role_name'] : '' }})</span>@endif</td>
                                <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['target'] ?? 0) }}@else{{ number_format($u['target'] ?? 0, 2) }}@endif</td>
                                <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['realization'] ?? 0) }}@else{{ number_format($u['realization'] ?? 0, 2) }}@endif</td>
                                <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['realization_prev'] ?? 0) }}@else{{ number_format($u['realization_prev'] ?? 0, 2) }}@endif</td>
                                <td class="px-3 py-2">@if(isset($u['growth']) && $u['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($u['growth']) && $u['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['growth'] ?? 0) }}@else{{ number_format($u['growth'] ?? 0, 2) }}@endif</span></td>
                                <td class="px-3 py-2">@if(isset($u['growth']) && $u['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($u['growth']) && $u['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">{{ $u['growth_pct'] !== null ? number_format($u['growth_pct'],2) . '%' : '-' }}</span></td>
                                <td class="px-3 py-2">{{ $u['achievement_pct'] !== null ? number_format($u['achievement_pct'],2) . '%' : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-zinc-50 dark:bg-zinc-900">
                        <tr class="border-t font-semibold {{ (isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] < 0) ? 'text-red-700 dark:text-rose-300' : ((isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] > 0) ? 'text-green-800 dark:text-green-200' : '') }}">
                            <td class="px-3 py-2">Grand Total</td>
                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($adminMetrics['totals']['target'] ?? 0) }}@else{{ number_format($adminMetrics['totals']['target'] ?? 0, 2) }}@endif</td>
                            <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($adminMetrics['totals']['realization'] ?? 0) }}@else{{ number_format($adminMetrics['totals']['realization'] ?? 0, 2) }}@endif</td>
                            <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($adminMetrics['totals']['realization_prev'] ?? 0) }}@else{{ number_format($adminMetrics['totals']['realization_prev'] ?? 0, 2) }}@endif</td>
                            <td class="px-3 py-2">@if(isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">@if(($metric ?? 'amount') === 'units'){{ number_format($adminMetrics['totals']['growth'] ?? 0) }}@else{{ number_format($adminMetrics['totals']['growth'] ?? 0, 2) }}@endif</span></td>
                            <td class="px-3 py-2">@if(isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">{{ isset($adminMetrics['totals']['growth_pct']) ? number_format($adminMetrics['totals']['growth_pct'],2) . '%' : '-' }}</span></td>
                            <td class="px-3 py-2">{{ isset($adminMetrics['totals']['achievement_pct']) ? number_format($adminMetrics['totals']['achievement_pct'],2) . '%' : '-' }}</td>
                        </tr>
                    </tfoot>
                </table>
                    </div>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left">
                        <tr>
                            <th class="px-3 py-2">Area</th>
                            <th class="px-3 py-2">User</th>
                            <th class="px-3 py-2">Target</th>
                            <th class="px-3 py-2">Realisasi</th>
                            <th class="px-3 py-2">Realisasi M-1</th>
                            <th class="px-3 py-2">Growth</th>
                            <th class="px-3 py-2">Growth %</th>
                            <th class="px-3 py-2">Achievement %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($adminMetrics['users'] as $u)
                            @if((($u['role_deleted'] ?? false) === true || ($u['user_deleted'] ?? false) === true) && ($u['has_reports'] ?? false) !== true)
                                @continue
                            @endif
                            <tr class="border-t transition-colors duration-150 hover:shadow-sm {{ (isset($u['growth']) && $u['growth'] < 0) ? 'text-red-700 dark:text-rose-300' : ((isset($u['growth']) && $u['growth'] > 0) ? 'text-green-800 dark:text-green-200' : '') }}">
                                <td class="px-3 py-2">{{ $u['role_name'] }}@if(($u['role_deleted'] ?? false) === true) <span class="text-xs text-zinc-500">(deleted)</span>@endif</td>
                                <td class="px-3 py-2">{{ $u['user_name'] }}@if(($u['user_deleted'] ?? false) === true) <span class="text-xs text-zinc-500">(deleted user)</span>@endif @if(($u['user_moved'] ?? false) === true) <span class="text-xs text-zinc-500">(pindah area{{ !empty($u['user_current_role_name'] ?? null) ? ': ' . $u['user_current_role_name'] : '' }})</span>@endif</td>
                                <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['target'] ?? 0) }}@else{{ number_format($u['target'] ?? 0, 2) }}@endif</td>
                                <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['realization'] ?? 0) }}@else{{ number_format($u['realization'] ?? 0, 2) }}@endif</td>
                                <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['realization_prev'] ?? 0) }}@else{{ number_format($u['realization_prev'] ?? 0, 2) }}@endif</td>
                                <td class="px-3 py-2">@if(isset($u['growth']) && $u['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($u['growth']) && $u['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">@if(($metric ?? 'amount') === 'units'){{ number_format($u['growth'] ?? 0) }}@else{{ number_format($u['growth'] ?? 0, 2) }}@endif</span></td>
                                <td class="px-3 py-2">@if(isset($u['growth']) && $u['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($u['growth']) && $u['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">{{ $u['growth_pct'] !== null ? number_format($u['growth_pct'],2) . '%' : '-' }}</span></td>
                                <td class="px-3 py-2">{{ $u['achievement_pct'] !== null ? number_format($u['achievement_pct'],2) . '%' : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-zinc-50 dark:bg-zinc-900">
                        <tr class="border-t font-semibold {{ (isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] < 0) ? 'text-red-700 dark:text-rose-300' : ((isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] > 0) ? 'text-green-800 dark:text-green-200' : '') }}">
                            <td class="px-3 py-2">Grand Total</td>
                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($adminMetrics['totals']['target'] ?? 0) }}@else{{ number_format($adminMetrics['totals']['target'] ?? 0, 2) }}@endif</td>
                            <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($adminMetrics['totals']['realization'] ?? 0) }}@else{{ number_format($adminMetrics['totals']['realization'] ?? 0, 2) }}@endif</td>
                            <td class="px-3 py-2">@if(($metric ?? 'amount') === 'units'){{ number_format($adminMetrics['totals']['realization_prev'] ?? 0) }}@else{{ number_format($adminMetrics['totals']['realization_prev'] ?? 0, 2) }}@endif</td>
                            <td class="px-3 py-2">@if(isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">@if(($metric ?? 'amount') === 'units'){{ number_format($adminMetrics['totals']['growth'] ?? 0) }}@else{{ number_format($adminMetrics['totals']['growth'] ?? 0, 2) }}@endif</span></td>
                            <td class="px-3 py-2">@if(isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] > 0)<flux:icon icon="arrow-up" variant="micro" class="text-green-800 inline-block" />@elseif(isset($adminMetrics['totals']['growth']) && $adminMetrics['totals']['growth'] < 0)<flux:icon icon="arrow-down" variant="micro" class="text-red-700 inline-block" />@else<span class="text-zinc-500">-</span>@endif <span class="ml-2">{{ isset($adminMetrics['totals']['growth_pct']) ? number_format($adminMetrics['totals']['growth_pct'],2) . '%' : '-' }}</span></td>
                            <td class="px-3 py-2">{{ isset($adminMetrics['totals']['achievement_pct']) ? number_format($adminMetrics['totals']['achievement_pct'],2) . '%' : '-' }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>
    </div>
@else
    <div class="mt-6 text-sm text-zinc-600">Dashboard metrics not available.</div>
@endif
