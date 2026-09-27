<?php

arch('la imposición es lógica pura sin dependencias externas')
    ->expect('App\Domain\Imposition')
    ->toOnlyUse(['App\Domain\Imposition', 'InvalidArgumentException']);

arch('el dominio no depende de Laravel')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'App\Models', 'App\Http']);

arch('el dominio no usa helpers globales de depuración')
    ->expect('App\Domain')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);
