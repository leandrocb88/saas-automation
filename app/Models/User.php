<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'trial_ends_at',
        'is_admin',
        'is_blocked',
        'service_type',
        'daily_usage',
        'last_quota_reset',
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
            'password' => 'hashed',
            'trial_ends_at' => 'datetime',
            'last_quota_reset' => 'datetime',
            'is_admin' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return (bool) ($this->is_admin ?? false);
    }

    public function isBlocked(): bool
    {
        return (bool) ($this->is_blocked ?? false);
    }
    public function channels()
    {
        return $this->hasMany(Channel::class);
    }

    public function digestSchedule()
    {
        return $this->hasOne(DigestSchedule::class);
    }

    public function digests()
    {
        return $this->hasMany(Digest::class);
    }
}
