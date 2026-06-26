<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\Licence\Verifier\Commands\DoctorCommand;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorService;

/**
 * Opt-in health endpoint that runs the verifier's doctor checks and reports a
 * JSON status for monitoring (200 healthy / 503 degraded).
 */
final class HealthController
{
    public function show(): JsonResponse
    {
        $service = new DoctorService;

        foreach (DoctorCommand::CHECKS as $check) {
            $service->register($check);
        }

        $report = $service->run();
        $degraded = $service->summarise($report)['fail'] > 0;

        return response()->json([
            'status' => $degraded ? 'degraded' : 'healthy',
            'checks' => array_map(static fn (array $row): array => [
                'name' => $row['check']->name(),
                'status' => $row['result']->status->value,
                'message' => $row['result']->message,
            ], $report),
        ], $degraded ? 503 : 200);
    }
}
