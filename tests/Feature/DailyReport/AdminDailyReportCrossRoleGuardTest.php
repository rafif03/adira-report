<?php

use App\Livewire\DailyReportForm;
use App\Models\CarReport;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('prevents admin from entering a report for a different area if user already reported on same date', function () {
	$adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
	$areaA = Role::create(['name' => 'Area A', 'slug' => 'area-a']);
	$areaB = Role::create(['name' => 'Area B', 'slug' => 'area-b']);

	$admin = User::factory()->withoutTwoFactor()->create(['role_id' => $adminRole->id]);
	$user = User::factory()->withoutTwoFactor()->create(['role_id' => $areaB->id]);

	$date = Carbon::parse('2026-01-04')->toDateString();

	// User previously reported when they were in Area A (role_id snapshot on report).
	CarReport::create([
		'role_id' => $areaA->id,
		'date' => $date,
		'units' => 1,
		'amount' => 1000,
		'submitted_by' => $user->id,
	]);

	Livewire::actingAs($admin)
		->test(DailyReportForm::class)
		->set('date', $date)
		->set('vehicle_type', 'motor')
		// Attempt to input under Area B (user current role)
		->call('editUserReport', $areaB->id, $user->id)
		->set('units', 2)
		->set('amount', 2000)
		->call('saveReport')
		->assertSet('showConflictModal', true)
		->assertSet('conflictMessage', function ($v) {
			return str_contains($v, 'User ini sudah punya laporan') || str_contains($v, 'area:');
		});
});