<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('dashboard', function () {
    $user = auth()->user();

    if (! $user) {
        return redirect()->route('home');
    }

    $role = strtolower($user->role?->slug ?? $user->role?->name ?? 'guest');

    return redirect()->route('dashboard.role', ['role' => $role]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('dashboard/{role}', function (string $role) {
    return view('livewire.dashboards.role-page', ['role' => strtolower($role)]);
})
    ->middleware(['auth', 'verified', \App\Http\Middleware\EnsureRoleDashboard::class])
    ->name('dashboard.role');

Route::get('dashboard/{role}/daily-report', function (string $role) {
    return view('livewire.dashboards.daily-report-page', ['role' => strtolower($role)]);
})
    ->middleware(['auth', 'verified', \App\Http\Middleware\EnsureRoleDashboard::class])
    ->name('dashboard.role.daily-report');

// Admin role management UI
Route::get('admin/roles', \App\Livewire\Admin\RolesManager::class)
    ->middleware(['auth', 'verified', \App\Http\Middleware\IsAdmin::class])
    ->name('admin.roles');

// Admin users management UI
Route::get('admin/users', \App\Livewire\Admin\UsersManager::class)
    ->middleware(['auth', 'verified', \App\Http\Middleware\IsAdmin::class])
    ->name('admin.users');

// Admin monthly targets manager
Route::get('admin/monthly-targets', \App\Livewire\Admin\MonthlyTargetsManager::class)
    ->middleware(['auth', 'verified', \App\Http\Middleware\IsAdmin::class])
    ->name('admin.monthly-targets');

// Admin daily report (uses existing Livewire component)
Route::get('admin/daily-report', \App\Livewire\DailyReportForm::class)
    ->middleware(['auth', 'verified', \App\Http\Middleware\IsAdmin::class])
    ->name('admin.daily-report');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('profile.edit');
    Route::get('settings/password', Password::class)->name('user-password.edit');
    Route::get('settings/appearance', Appearance::class)->name('appearance.edit');

    Route::get('settings/two-factor', TwoFactor::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    // simple route for submitting daily reports
    Route::get('daily-report', function () {
        $role = strtolower(auth()->user()?->role?->name ?? 'guest');
        if ($role === 'guest') {
            return redirect()->route('dashboard');
        }

        return view('daily-report');
    })->middleware(['auth', 'verified'])->name('daily-report');
});
