<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_OPERATOR = 'operator';

    /** @var list<string> */
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_OPERATOR];

    /**
     * NOTE: `role` is intentionally NOT mass-assignable. It is only ever
     * assigned explicitly (seeders, Filament page handlers) after an
     * admin check, so a crafted `role=admin` POST can never escalate.
     */
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['role' => self::ROLE_OPERATOR];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // Last line of defense: runs for row actions, bulk actions, header
        // actions, and direct `$user->delete()` calls alike.
        static::deleting(function (User $user): bool {
            // Never delete the last remaining admin (lockout protection).
            if ($user->isAdmin() && ! static::where('role', self::ROLE_ADMIN)->whereKeyNot($user->getKey())->exists()) {
                return false;
            }

            // Never allow self-deletion.
            if (auth()->check() && (int) auth()->id() === (int) $user->getKey()) {
                return false;
            }

            return true;
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isOperator(): bool
    {
        return $this->role === self::ROLE_OPERATOR;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, self::ROLES, true);
    }
}
