<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\Licence\Verifier\Http\Controllers\HealthController;

Route::prefix((string) config('license-verifier.api.prefix', 'api/license-verifier/v1'))
    ->middleware((array) config('license-verifier.api.middleware', ['api']))
    ->group(function (): void {
        Route::get('health', [HealthController::class, 'show'])->name('license-verifier.health');
    });
