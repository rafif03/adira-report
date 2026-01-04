<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyMotorTarget extends Model
{
    protected $table = 'monthly_motor_targets';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'role_id',
        'year',
        'month',
        'target_units',
        'target_amount',
    ];
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class)->withTrashed();
    }

    public function role()
    {
        return $this->belongsTo(\App\Models\Role::class)->withTrashed();
    }
}
