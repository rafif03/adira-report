<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('monthly_motor_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('role_id')->index();
            $table->integer('year');
            $table->integer('month');
            $table->integer('target_units')->default(0);
            $table->decimal('target_amount', 14, 2)->default(0);

            $table->unique(['user_id', 'year', 'month'], 'monthly_motor_targets_user_year_month_unique');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')

                ->onDelete('cascade');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('monthly_motor_targets');
    }
};
