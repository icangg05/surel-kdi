<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $color
 */
#[Fillable(['name', 'description', 'color'])]
class ContactGroup extends Model
{
    use Audited;

    public const COLORS = ['slate', 'blue', 'emerald', 'amber', 'rose', 'violet', 'cyan', 'orange'];

    /** @return BelongsToMany<Contact, $this> */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class);
    }
}
