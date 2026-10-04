<?php

use App\Models\User;
use App\Support\AccentPalette;
use Inertia\Testing\AssertableInertia as Assert;

it('guarda el modo y el color de acento en la cuenta', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('appearance.update'), ['appearance' => 'dark', 'accent_color' => '#2563EB'])
        ->assertSessionHasNoErrors()
        ->assertCookie('appearance', 'dark', encrypted: false);

    $user->refresh();
    expect($user->appearance)->toBe('dark')
        ->and($user->accent_color)->toBe('#2563eb');

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('appearance', ['mode' => 'dark', 'accent' => '#2563eb']));
});

it('pinta la página ya con el tema de la cuenta para que no parpadee', function () {
    $user = User::factory()->create(['appearance' => 'dark', 'accent_color' => '#059669']);

    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toContain('class="dark"')
        ->and($html)->toContain('--primary: #059669;')
        // Sobre este verde contrasta más el texto oscuro (WCAG).
        ->and($html)->toContain('--primary-foreground: #111111;');
});

it('vuelve al color de Noctua sin color de acento', function () {
    $user = User::factory()->create(['accent_color' => '#059669']);

    $this->actingAs($user)->patch(route('appearance.update'), ['appearance' => 'system', 'accent_color' => null])->assertSessionHasNoErrors();

    expect($user->refresh()->accent_color)->toBeNull()
        ->and($this->get(route('dashboard'))->getContent())->not->toContain('--primary:');
});

it('valida modo y color', function (array $payload, string $field) {
    $this->actingAs(User::factory()->create())
        ->patch(route('appearance.update'), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'modo desconocido' => [['appearance' => 'sepia', 'accent_color' => null], 'appearance'],
    'color mal escrito' => [['appearance' => 'light', 'accent_color' => 'azul'], 'accent_color'],
    'inyección en el estilo' => [['appearance' => 'light', 'accent_color' => '#000;}x'], 'accent_color'],
]);

it('elige texto legible sobre el color de acento', function () {
    expect(AccentPalette::readableOn('#2563eb'))->toBe('#ffffff')
        ->and(AccentPalette::readableOn('#facc15'))->toBe('#111111')
        ->and(AccentPalette::style('no es un color'))->toBe('');
});
