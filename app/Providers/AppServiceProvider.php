<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Livewire component aliases in case auto-discovery misses them
        if (class_exists(\Livewire\Livewire::class) && class_exists(\App\Livewire\Admin\RolesManager::class)) {
            \Livewire\Livewire::component('admin.roles-manager', \App\Livewire\Admin\RolesManager::class);
            // also register the full namespaced style Livewire may look for
            \Livewire\Livewire::component('app.livewire.admin.roles-manager', \App\Livewire\Admin\RolesManager::class);
        }

        // Register UsersManager as well so Livewire can resolve it by alias
        if (class_exists(\Livewire\Livewire::class) && class_exists(\App\Livewire\Admin\UsersManager::class)) {
            \Livewire\Livewire::component('admin.users-manager', \App\Livewire\Admin\UsersManager::class);
            \Livewire\Livewire::component('app.livewire.admin.users-manager', \App\Livewire\Admin\UsersManager::class);
        }
    }
}
