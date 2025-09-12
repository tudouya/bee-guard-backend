<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role',
        'openid',
        'unionid',
        'nickname',
        'avatar',
        'phone',
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
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $role = (string) ($this->role ?? '');

        // Super admin can access all panels.
        if ($role === 'super_admin') {
            return true;
        }

        return match ($panel->getId()) {
            'admin' => false, // non-super admins cannot access admin panel
            'enterprise' => $role === 'enterprise_admin',
            default => false,
        };
    }

    public function getDisplayNameAttribute(): string
    {
        $role = (string) ($this->role ?? '');
        if ($role === 'farmer' && filled($this->nickname)) {
            return (string) $this->nickname;
        }
        return (string) ($this->name ?: ($this->username ?: ($this->email ?: '')));
    }
}
