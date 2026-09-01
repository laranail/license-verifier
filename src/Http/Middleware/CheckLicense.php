<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Simtabi\Laranail\Licence\Verifier\Exceptions\LicensingException;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Gate requests behind a valid license. Driver-agnostic: it goes through the
 * LicenseManager, whose verify() applies the configured driver, the verification
 * cache, the grace/fail-open window and domain binding.
 */
class CheckLicense
{
    public function __construct(protected LicenseManager $manager) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->isExcludedRoute($request)) {
            return $next($request);
        }

        try {
            $result = $this->manager->verify();

            if (! $result->isUsable()) {
                return $this->deny($request, 'Invalid or expired license', 'LICENSE_INVALID');
            }

            $this->manager->heartbeat();
            $this->flagExpiry($request, $result);

            return $next($request);
        } catch (LicensingException $e) {
            return $this->deny($request, $e->getMessage(), 'LICENSE_ERROR');
        }
    }

    protected function isExcludedRoute(Request $request): bool
    {
        return array_any((array) config('license-verifier.excluded_routes', []), fn ($pattern) => $request->is($pattern));
    }

    protected function deny(Request $request, string $message, string $code): mixed
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message, 'code' => $code], 403);
        }

        abort(403, $message);
    }

    protected function flagExpiry(Request $request, VerificationResult $result): void
    {
        if ($result->expiresAt === null) {
            return;
        }

        $expiresAt = Carbon::parse($result->expiresAt);

        // Carbon 3: diffInDays is signed; a future expiry yields a positive value
        // only with this argument order. Flag when expiry is within the next 7 days.
        $daysUntilExpiry = now()->diffInDays($expiresAt, false);

        if ($daysUntilExpiry > 0 && $daysUntilExpiry <= 7) {
            $request->attributes->set('license_expiring_soon', true);
            $request->attributes->set('license_expires_at', $result->expiresAt);
        }
    }
}
