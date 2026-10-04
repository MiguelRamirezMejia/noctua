<?php

namespace Database\Factories;

use App\Models\Publisher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Publisher>
 */
class PublisherFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Editorial '.fake()->unique()->lastName();

        return ['name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(4))];
    }
}
