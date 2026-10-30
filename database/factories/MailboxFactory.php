<?php

namespace Database\Factories;

use App\Models\Domain;
use App\Models\Mailbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mailbox>
 */
class MailboxFactory extends Factory
{
    public function definition(): array
    {
        return [
            'domain_id' => fn () => Domain::query()->firstOrCreate(['name' => 'ilmifaizan.cloud'], ['inbound_domain' => 'in.ilmifaizan.cloud'])->id,
            'local_part' => fake()->unique()->userName(),
            'display_name' => 'Dinas Kominfo',
            'is_active' => true,
        ];
    }
}
