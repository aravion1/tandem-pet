<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '+7'.fake()->unique()->numerify('9#########');

        return [
            'phone_ciphertext' => Crypt::encryptString($phone),
            'phone_hash' => hash('sha256', $phone),
            'password_hash' => static::$password ??= Hash::make('password'),
            'consented_at' => now(),
            'consent_version' => 'test',
            'activated_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'consented_at' => null,
        ]);
    }
}
