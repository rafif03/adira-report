<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Role;

class CarReport extends Model
{
    protected $table = 'car_reports';

    public $timestamps = false;

    protected $fillable = [
        'role_id', 'date', 'units', 'amount', 'submitted_by',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'units' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'submitted_by')->withTrashed();
    }
}
