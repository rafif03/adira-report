<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'role_id',
        'units',
        'amount',
        'submitted_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }
}
