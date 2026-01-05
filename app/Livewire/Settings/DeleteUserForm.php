<?php

namespace App\Livewire\Settings;

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DeleteUserForm extends Component
{
    public string $password = '';

    public bool $willForceDelete = false;

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $user = Auth::user();
        if (! $user) {
            $this->redirect('/', navigate: true);
            return;
        }

        $hasCarReports = DB::table('car_reports')->where('submitted_by', $user->id)->exists();
        $hasMotorReports = DB::table('motor_reports')->where('submitted_by', $user->id)->exists();
        $hasReports = $hasCarReports || $hasMotorReports;

        // If user has ever submitted a report, keep history by soft-deleting.
        // Otherwise, permanently delete (and cascade monthly targets).
        $this->willForceDelete = ! $hasReports;

        if ($hasReports) {
            $user->delete();
        } else {
            // Use a hard delete to allow email reuse and cascade monthly targets.
            // First delete sessions and monthly targets to avoid FK constraints, then delete user.
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('monthly_car_targets')->where('user_id', $user->id)->delete();
            DB::table('monthly_motor_targets')->where('user_id', $user->id)->delete();
            DB::table('users')->where('id', $user->id)->delete();
        }

        $logout();
        $this->redirect('/', navigate: true);
    }

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->willForceDelete = false;
            return;
        }

        $hasCarReports = DB::table('car_reports')->where('submitted_by', $user->id)->exists();
        $hasMotorReports = DB::table('motor_reports')->where('submitted_by', $user->id)->exists();

        $this->willForceDelete = ! ($hasCarReports || $hasMotorReports);
    }
}
