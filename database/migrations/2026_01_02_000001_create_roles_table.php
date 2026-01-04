<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->nullable()->unique();
            $table->softDeletes();
        });

        // add foreign key on users.role_id after roles table is created
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role_id')) {
                $table->foreign('role_id')
                    ->references('id')
                    ->on('roles')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign key first if it exists
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
            });
        }

        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'slug')) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            }
        });

        Schema::dropIfExists('roles');
    }
};
