<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('car_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->index();
            $table->date('date')->index();
            $table->integer('units')->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->unsignedBigInteger('submitted_by')->index();

            $table->unique(['role_id', 'date', 'submitted_by'], 'car_reports_role_date_submitted_by_unique');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            $table->foreign('submitted_by')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('car_reports');
    }
};
