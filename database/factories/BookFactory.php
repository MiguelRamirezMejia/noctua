<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // El libro es del espacio activo de quien lo crea.
            'publisher_id' => function (array $attributes): ?int {
                $id = User::query()->whereKey($attributes['user_id'])->value('current_publisher_id');

                return is_numeric($id) ? (int) $id : null;
            },
            'title' => fake()->sentence(3),
            'source_filename' => 'libro.pdf',
            'source_page_count' => 8,
            'source_width_mm' => 139.7,
            'source_height_mm' => 215.9,
            'pages' => array_map(fn (int $p): array => ['id' => "p{$p}", 'source' => $p, 'folio' => true], range(1, 8)),
            'folios' => ['enabled' => false],
        ];
    }
}
