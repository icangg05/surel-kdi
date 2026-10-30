<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake('id_ID')->name(),
            // Domain example.com dicadangkan IANA — aman, tidak pernah benar-benar terkirim.
            'email' => fake()->unique()->userName().'@example.com',
            'position' => fake()->randomElement(['Kepala Dinas', 'Sekretaris', 'Kepala Bidang', 'Camat', 'Lurah', 'Staf']),
            'organization' => fake()->randomElement(['Dinas Pendidikan', 'Dinas Kesehatan', 'Bappeda', 'Kecamatan Mandonga', 'Kecamatan Poasia', 'Dinas Perhubungan']),
            'status' => ContactStatus::Active,
        ];
    }
}
