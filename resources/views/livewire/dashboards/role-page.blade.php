@php
    $roleName = \App\Models\Role::where('slug', $role)->value('name') ?? ucfirst($role);
@endphp

<x-layouts.app :title="$roleName . ' Dashboard'">
    <livewire:dashboards.role-dashboard :role="$role" />
</x-layouts.app>
