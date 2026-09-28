<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GsmCheckinLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'checkin_date',
        'vaginal_dryness',
        'urinary_frequency',
        'pelvic_discomfort',
        'libido_impact',
    ];

    protected $casts = [
        'checkin_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
