<?php

use App\Models\ActivityEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Las cuentas de prueba caducadas se borran con todos sus archivos.
Schedule::command('noctua:demo:purge')->hourly();

// El registro de actividad se conserva 90 días.
Schedule::command('model:prune', ['--model' => [ActivityEvent::class]])->daily();
