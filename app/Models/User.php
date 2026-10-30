<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Audited;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property Role $role
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Audited, HasFactory, Notifiable, TwoFactorAuthenticatable;

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
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Mailbox, $this> */
    public function mailboxes(): BelongsToMany
    {
        return $this->belongsToMany(Mailbox::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * Mailbox yang boleh dipakai: admin melihat semua, operator hanya yang ditugaskan.
     *
     * @return Builder<Mailbox>
     */
    public function accessibleMailboxes(): Builder
    {
        $query = Mailbox::query()->with('domain')->where('is_active', true)->orderBy('display_name');

        return $this->isAdmin() ? $query : $query->whereHas('users', fn ($q) => $q->whereKey($this->id));
    }

    public function canAccessMailbox(Mailbox $mailbox): bool
    {
        return $this->isAdmin() || $this->mailboxes()->whereKey($mailbox->id)->exists();
    }
}
