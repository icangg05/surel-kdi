<?php

namespace App\Models;

use App\Enums\ContactStatus;
use App\Models\Concerns\Audited;
use Carbon\CarbonImmutable;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $position
 * @property string|null $organization
 * @property string|null $phone
 * @property string|null $notes
 * @property ContactStatus $status
 * @property string|null $suppressed_reason
 * @property CarbonImmutable|null $suppressed_at
 * @property CarbonImmutable|null $last_contacted_at
 */
#[Fillable(['name', 'email', 'position', 'organization', 'phone', 'notes', 'status', 'suppressed_reason', 'suppressed_at', 'created_by'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use Audited, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'suppressed_at' => 'datetime',
            'last_contacted_at' => 'datetime',
        ];
    }

    /** Alamat disimpan huruf kecil supaya unik dan cocok dengan payload Brevo. */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = mb_strtolower(trim($value));
    }

    /** @return BelongsToMany<ContactGroup, $this> */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class);
    }

    /** @param Builder<Contact> $query */
    public function scopeSendable(Builder $query): void
    {
        $query->where('status', ContactStatus::Active);
    }

    /** @param Builder<Contact> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (! filled($term)) {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('organization', 'like', $like)
            ->orWhere('position', 'like', $like));
    }

    /** Hentikan pengiriman ke alamat ini (dipanggil dari webhook Brevo). */
    public function suppress(ContactStatus $status, string $reason): void
    {
        $this->forceFill([
            'status' => $status,
            'suppressed_reason' => mb_substr($reason, 0, 250),
            'suppressed_at' => now(),
        ])->save();
    }
}
