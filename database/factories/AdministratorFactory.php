<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Administrator>
 */
class AdministratorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'first_name' => "Super",
            'middle_name' => "",
            'last_name' => "Admin",
            'email' => "codebynikki@gmail.com",
            'password' => Hash::make("abc123456"),
            'is_super_admin' => true,
            'created_at' => now(),
        ];
    }
}
