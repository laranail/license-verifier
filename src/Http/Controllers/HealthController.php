<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\Licence\Verifier\Doctor\Checks;
use Simtabi\Laranail\Package\Tools\Services\Doctor\HealthResponder;

/**
 * Opt-in health endpoint — runs the verifier's doctor checks and reports a JSON
 * status for monitoring (200 healthy / 503 degraded).
 */
final class HealthController
{
    public function show(): JsonResponse
    {
        return HealthResponder::json(Checks::all());
    }
}
