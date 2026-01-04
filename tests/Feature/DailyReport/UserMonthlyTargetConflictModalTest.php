<?php

use App\Livewire\DailyReportForm;
use App\Models\MonthlyCarTarget;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows a conflict modal for users when monthly target area differs', function () {
    $roleCurrent = Role::create(['name' => 'JABAR', 'slug' => 'jabar']);
    $roleTarget = Role::create(['name' => 'BNT', 'slug' => 'bnt']);

    $user = User::factory()->create([
        'role_id' => $roleCurrent->id,
    ]);

    // Monthly target is tied to a different area than the user's current role
    MonthlyCarTarget::create([
        'user_id' => $user->id,
        'role_id' => $roleTarget->id,
        'year' => 2026,
        'month' => 1,
        'target_units' => 10,
        'target_amount' => 1000,
    ]);

    Livewire::actingAs($user)
        ->test(DailyReportForm::class)
        ->set('date', '2026-01-04')
        ->set('vehicle_type', 'car')
        ->set('units', 1)
        ->set('amount', 100)
        ->call('submit')
        ->assertSet('showConflictModal', true)
        ->assertSee('Silakan hubungi Admin');
});
