<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
       protected $fillable = [
        'full_name',
        'email',
        'password',
        'otp',
        'otp_expire_at',
        'status',
        'suspend_reason',
        'user_type',
        'fcm_token',
        'apple_id',
        'email_verified_at',
        'is_privacy_accepted',
        'onboardingCompleted',
        'stripe_customer_id',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'otp_expire_at' => 'datetime',
        'last_login_at' => 'datetime',

        'password' => 'hashed',

        'is_privacy_accepted' => 'boolean',
        'onboardingCompleted' => 'boolean',
    ];
}

    public function hasRole(string $role): bool
    {
        return $this->user_type === $role;
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);

    }
//health log
    public function healthLogs()
    {
        return $this->hasMany(HealthLog::class);

    }

    public function labReports()
{
    return $this->hasMany(LabReport::class);
}


public function payments()
{
    return $this->hasMany(Payment::class);
}

public function latestSubscription()
{
    return $this->hasOne(Payment::class)
        ->where('type', 'subscription')
        ->where('status', 'paid')
        ->latestOfMany();
}
}
