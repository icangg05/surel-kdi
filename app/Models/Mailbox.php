<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\MailboxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $domain_id
 * @property string $local_part
 * @property string $display_name
 * @property string|null $signature_html
 * @property bool $is_active
 * @property-read string $address
 * @property-read string $inbound_address
 * @property-read Domain $domain
 */
#[Fillable(['domain_id', 'local_part', 'display_name', 'signature_html', 'is_active'])]
class Mailbox extends Model
{
    /** @use HasFactory<MailboxFactory> */
    use Audited, HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Domain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return HasMany<Thread, $this> */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Alamat pengirim, mis. kominfo@ilmifaizan.cloud.
     *
     * @return Attribute<string, never>
     */
    protected function address(): Attribute
    {
        return Attribute::get(fn () => $this->local_part.'@'.$this->domain->name);
    }

    /**
     * Alamat penerima di subdomain inbound Brevo, mis. kominfo@in.ilmifaizan.cloud.
     *
     * @return Attribute<string, never>
     */
    protected function inboundAddress(): Attribute
    {
        return Attribute::get(fn () => $this->local_part.'@'.$this->domain->inbound_domain);
    }

    /** Reply-To berpelacak: kominfo+token@in.ilmifaizan.cloud. */
    public function replyAddress(?string $token): string
    {
        return $token
            ? $this->local_part.'+'.$token.'@'.$this->domain->inbound_domain
            : $this->inbound_address;
    }
}
