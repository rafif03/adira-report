<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SlugTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure every role has a unique slug; backfill if missing
        $roles = DB::table('roles')->select('id', 'name', 'slug')->get();

        foreach ($roles as $role) {
            if (! empty($role->slug)) continue;

            $base = Str::slug((string) $role->name);
            $base = $base !== '' ? $base : 'role-' . $role->id;

            $slug = $base;
            $i = 2;
            while (DB::table('roles')->where('slug', $slug)->where('id', '!=', $role->id)->exists()) {
                $slug = $base . '-' . $i;
                $i++;
            }

            DB::table('roles')->where('id', $role->id)->update(['slug' => $slug]);
        }
    }
}
