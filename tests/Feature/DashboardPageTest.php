<?php

use App\Models\Book;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('muestra los primeros pasos a quien empieza', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('bookCount', 0)
            ->where('recentBooks', [])
            ->where('steps', ['book' => false, 'edit' => false, 'print' => false]));
});

it('marca los pasos hechos y lista los libros recientes del espacio', function () {
    $user = User::factory()->create();
    Book::factory()->for($user)->create(['title' => 'Primero', 'folios' => ['enabled' => true]]);
    Book::factory()->create(['title' => 'De otro espacio']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('bookCount', 1)
            ->where('recentBooks.0.title', 'Primero')
            ->where('steps.book', true)
            ->where('steps.edit', true)
            ->where('steps.print', false));
});
