<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
    ];

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'session_id', 'session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}