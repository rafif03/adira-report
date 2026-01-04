<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolesTableSeeder extends Seeder
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
            $slug = Str::slug($role);
            $name = in_array($slug, ['admin', 'guest']) ? ucfirst($slug) : strtoupper($slug);

            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'slug' => $slug]
            );
        }
    }
}
