<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('the engine never evaluates code dynamically')
    ->expect(['eval', 'exec', 'shell_exec'])
    ->each->not->toBeUsed();

arch('contracts are interfaces')
    ->expect('Syriable\Metrics\Contracts')
    ->toBeInterfaces();

arch('value objects and results are immutable finals')
    ->expect([
        'Syriable\Metrics\Support',
        'Syriable\Metrics\Results',
    ])
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('the engine layer stays out of HTTP')
    ->expect('Syriable\Metrics')
    ->not->toUse([
        'Illuminate\Http\Request',
        'Illuminate\Routing\Controller',
    ]);

arch('enums are enums')
    ->expect('Syriable\Metrics\Enums')
    ->toBeEnums();
