<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreventativeReminderSnapshot extends Model
{
    use HasFactory;

    protected $table = 'preventative_reminder_snapshots';

    protected $fillable = [
        'user_id',
        'snapshot_date',
        'reminders',
        'summary',
        'last_updated_ai',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'reminders'     => 'array',
        'summary'       => 'array',
    ];

    /**
     * Relationship: Preventative Reminder snapshot belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
