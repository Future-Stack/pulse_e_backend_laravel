<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabReport extends Model
{
    protected $fillable = [
        'user_id',
        'lab_report',
    ];

    /**
     * Get the user that owns the lab report.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}