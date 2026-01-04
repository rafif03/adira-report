<?php

use App\Livewire\Admin\MonthlyTargetsManager;
use App\Livewire\DailyReportForm;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

test('admin cannot input daily report for themselves', function () {
    $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
    $area = Role::create(['name' => 'Area', 'slug' => 'area']);

    $admin = User::factory()->withoutTwoFactor()->create(['role_id' => $adminRole->id]);

    $date = Carbon::parse('2026-01-04')->toDateString();

    Livewire::actingAs($admin)
        ->test(DailyReportForm::class)
        ->set('date', $date)
        ->set('vehicle_type', 'car')
        ->call('editUserReport', $area->id, $admin->id)
        ->assertSet('showAdminRestrictionModal', true);
});

test('admin cannot input daily report for another admin', function () {
    $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
    $area = Role::create(['name' => 'Area', 'slug' => 'area']);

    $admin = User::factory()->withoutTwoFactor()->create(['role_id' => $adminRole->id]);
    $otherAdmin = User::factory()->withoutTwoFactor()->create(['role_id' => $adminRole->id]);

    $date = Carbon::parse('2026-01-04')->toDateString();

    Livewire::actingAs($admin)
        ->test(DailyReportForm::class)
        ->set('date', $date)
        ->set('vehicle_type', 'car')
        ->call('editUserReport', $area->id, $otherAdmin->id)
        ->assertSet('showAdminRestrictionModal', true);
});

test('admin cannot input monthly target for themselves', function () {
    $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);

    $admin = User::factory()->withoutTwoFactor()->create(['role_id' => $adminRole->id]);

    Livewire::actingAs($admin)
        ->test(MonthlyTargetsManager::class)
        ->set('vehicle_type', 'car')
        ->call('editTargetForRole', 0, $admin->id)
        ->assertSet('showAdminRestrictionModal', true);
});

test('admin cannot input monthly target for another admin', function () {
    $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);

    $admin = User::factory()->withoutTwoFactor()->create(['role_id' => $adminRole->id]);
    $otherAdmin = User::factory()->withoutTwoFactor()->create(['role_id' => $adminRole->id]);

    Livewire::actingAs($admin)
        ->test(MonthlyTargetsManager::class)
        ->set('vehicle_type', 'car')
        ->call('editTargetForRole', 0, $otherAdmin->id)
        ->assertSet('showAdminRestrictionModal', true);
});
