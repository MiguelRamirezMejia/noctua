<?php

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/noctua-cmd-'.bin2hex(random_bytes(4));
    mkdir($this->dir);
});

afterEach(function () {
    array_map(unlink(...), glob($this->dir.'/*') ?: []);
    rmdir($this->dir);
});

it('genera un documento de prueba y lo impone', function () {
    $this->artisan('noctua:test-document', ['output' => "{$this->dir}/prueba.pdf", '--pages' => 20])
        ->assertSuccessful();

    $this->artisan('noctua:impose', ['source' => "{$this->dir}/prueba.pdf", '--short-last' => true])
        ->expectsOutputToContain('prueba-impuesto.pdf')
        ->assertSuccessful();

    expect("{$this->dir}/prueba-impuesto.pdf")->toBeFile();
});

it('genera la hoja de calibración con offsets', function () {
    $this->artisan('noctua:calibration', ['output' => "{$this->dir}/cal.pdf", '--back-offset' => '0.5,-0.25'])
        ->assertSuccessful();

    expect("{$this->dir}/cal.pdf")->toBeFile();
});

it('informa de errores de configuración sin lanzar excepciones', function (array $options, string $message) {
    $this->artisan('noctua:test-document', ['output' => "{$this->dir}/prueba.pdf"])->assertSuccessful();

    $this->artisan('noctua:impose', ['source' => "{$this->dir}/prueba.pdf", ...$options])
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'hojas fuera de rango' => [['--sheets' => 9], 'entre 1 y 8'],
    'offset mal formado' => [['--back-offset' => 'abc'], 'X,Y'],
    'creep no numérico' => [['--creep' => 'mucho'], '--creep'],
]);

it('falla si el PDF de origen no existe', function () {
    $this->artisan('noctua:impose', ['source' => "{$this->dir}/nada.pdf"])
        ->expectsOutputToContain('No existe')
        ->assertFailed();
});
