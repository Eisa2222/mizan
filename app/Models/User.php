<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'org_id', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

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
            'role' => \App\Enums\UserRole::class,
        ];
    }

    public function hasRole(\App\Enums\UserRole $role): bool
    {
        return $this->role === $role;
    }

    public function hasAtLeastRole(\App\Enums\UserRole $role): bool
    {
        return $this->role?->isAtLeast($role) ?? false;
    }

    /**
     * Check if this user's role grants the given dotted permission.
     * Accepts either a Permission enum case or the raw dotted string.
     */
    public function hasPermission(\App\Enums\Permission|string $permission): bool
    {
        if ($this->role === null) {
            return false;
        }

        return $permission instanceof \App\Enums\Permission
            ? $this->role->hasPermission($permission)
            : $this->role->hasPermissionString($permission);
    }
}
