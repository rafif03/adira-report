<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RoleUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'sumbagut', 'sumbateng', 'sumbagsel', 'jabo1', 'jabo2', 'jabar', 'jateng', 'jatim', 'bnt', 'sultan', 'pasima', 'kalimantan', 'guest', 'admin',
        ];

        foreach ($roles as $role) {
            // attach role_id by matching URL-friendly name
            $roleRow = DB::table('roles')->where('slug', \Illuminate\Support\Str::slug($role))->first();
            $roleId = $roleRow->id ?? null;

            // create two users per role
            for ($i = 1; $i <= 2; $i++) {
                $email = "{$role}{$i}@example.com";

                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => ucfirst($role) . ' User ' . $i,
                        'password' => Hash::make('password'),
                        'email_verified_at' => Carbon::now(),
                    ]
                );

                if ($roleId) {
                    $user->role_id = $roleId;
                    $user->save();
                }
            }
        }
    }
}
