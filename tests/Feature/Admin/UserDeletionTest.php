<?php

use App\Livewire\Admin\UsersManager;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('admin force-deletes user without reports (cascades monthly targets)', function () {
    $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
    $areaRole = Role::create(['name' => 'AREA', 'slug' => 'area']);

    $admin = User::factory()->create(['role_id' => $adminRole->id]);
    $user = User::factory()->create(['role_id' => $areaRole->id]);

    // Targets should be removed via FK cascade when user is force-deleted.
    DB::table('monthly_car_targets')->insert([
        'user_id' => $user->id,
        'role_id' => $areaRole->id,
        'year' => 2026,
        'month' => 1,
        'target_units' => 10,
        'target_amount' => 10000,
    ]);

    DB::table('monthly_motor_targets')->insert([
        'user_id' => $user->id,
        'role_id' => $areaRole->id,
        'year' => 2026,
        'month' => 1,
        'target_units' => 5,
        'target_amount' => 5000,
    ]);

    $this->actingAs($admin);

    Livewire::test(UsersManager::class)
        ->call('confirmDelete', $user->id)
        ->set('adminPassword', 'password')
        ->call('confirmDeletion')
        ->assertHasNoErrors();

    expect(User::withTrashed()->find($user->id))->toBeNull();
    expect(DB::table('monthly_car_targets')->where('user_id', $user->id)->count())->toBe(0);
    expect(DB::table('monthly_motor_targets')->where('user_id', $user->id)->count())->toBe(0);
});

test('admin soft-deletes user with reports (keeps report history)', function () {
    $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
    $areaRole = Role::create(['name' => 'AREA', 'slug' => 'area']);

    $admin = User::factory()->create(['role_id' => $adminRole->id]);
    $user = User::factory()->create(['role_id' => $areaRole->id]);

    DB::table('car_reports')->insert([
        'role_id' => $areaRole->id,
        'date' => now()->toDateString(),
        'units' => 1,
        'amount' => 1000,
        'submitted_by' => $user->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(UsersManager::class)
        ->call('confirmDelete', $user->id)
        ->set('adminPassword', 'password')
        ->call('confirmDeletion')
        ->assertHasNoErrors();

    expect(User::withTrashed()->find($user->id)?->trashed())->toBeTrue();
});

test('admin cannot delete an admin account', function () {
    $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);

    $admin = User::factory()->create(['role_id' => $adminRole->id]);
    $otherAdmin = User::factory()->create(['role_id' => $adminRole->id]);

    $this->actingAs($admin);

    Livewire::test(UsersManager::class)
        ->call('confirmDelete', $otherAdmin->id)
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('showFlash', true)
        ->assertSet('flashType', 'error');

    expect(User::withTrashed()->find($otherAdmin->id))->not->toBeNull();
});
