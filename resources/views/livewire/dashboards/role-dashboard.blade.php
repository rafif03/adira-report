<div>
    {{-- Include a role-specific header/view if available, then always render the shared admin metrics (area/user tables) so area dashboards show the tables too. --}}
    @php $partial = 'livewire.dashboards.partials.' . $role; @endphp

    @if(view()->exists($partial))
        @include($partial)
    @elseif(view()->exists('dashboards.' . $role))
        @include('dashboards.' . $role)
    @else
        <h1 class="text-2xl font-semibold">{{ $roleName }} Dashboard</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-300">Welcome, {{ auth()->user()->name }}.</p>
    @endif

    @if(($role ?? '') !== 'guest')
        @include('livewire.dashboards.partials.metrics')
    @endif
</div>
