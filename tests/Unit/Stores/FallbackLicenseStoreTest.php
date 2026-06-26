<?php

declare(strict_types=1);

use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\Stores\FallbackLicenseStore;

/** In-memory LicenseStore whose reachability can be toggled by setting $throw. */
function flakyStore(): LicenseStore
{
    return new class implements LicenseStore
    {
        /** @var array<string, array<string, mixed>> */
        public array $data = [];

        public ?Throwable $throw = null;

        public function put(string $key, array $data): void
        {
            $this->guard();
            $this->data[$key] = $data;
        }

        public function get(string $key): ?array
        {
            $this->guard();

            return $this->data[$key] ?? null;
        }

        public function has(string $key): bool
        {
            $this->guard();

            return isset($this->data[$key]);
        }

        public function forget(string $key): void
        {
            $this->guard();
            unset($this->data[$key]);
        }

        private function guard(): void
        {
            if ($this->throw instanceof Throwable) {
                throw $this->throw;
            }
        }
    };
}

function connectionError(): PDOException
{
    return new PDOException('SQLSTATE[HY000] [2002] Connection refused');
}

beforeEach(function (): void {
    config()->set('license-verifier.cache.store', 'array');
    config()->set('license-verifier.storage.fallback_cooldown', 15);
    $this->primary = flakyStore();
    $this->fallback = flakyStore();
    $this->store = new FallbackLicenseStore($this->primary, $this->fallback);
});

it('writes through to the primary and mirrors to the fallback', function (): void {
    $this->store->put('K', ['token' => 'T']);

    expect($this->primary->data['K']['token'])->toBe('T')
        ->and($this->fallback->has('K'))->toBeTrue()
        ->and($this->store->get('K'))->toBe(['token' => 'T']); // clean (no sync flag)
});

it('serves the local copy when the primary is unreachable', function (): void {
    $this->store->put('K', ['token' => 'T']);       // mirrored
    $this->primary->throw = connectionError();      // primary goes down

    expect($this->store->get('K'))->toBe(['token' => 'T'])
        ->and($this->store->onFallback())->toBeTrue();
});

it('clears the mirror when a reachable primary reports the record gone (deactivation)', function (): void {
    $this->store->put('K', ['token' => 'T']);       // mirrored
    unset($this->primary->data['K']);               // deactivated on the primary (still reachable)

    expect($this->store->get('K'))->toBeNull()
        ->and($this->fallback->has('K'))->toBeFalse(); // stale mirror cleared, not resurrected
});

it('reconciles a write made during an outage once the primary recovers', function (): void {
    $this->primary->throw = connectionError();
    $this->store->put('K', ['token' => 'T']);       // primary down → pending fallback write

    expect($this->primary->data)->toBe([])
        ->and($this->store->pendingSyncCount())->toBe(1);

    // Primary recovers; cooldown elapses (clear the breaker held in the fallback).
    $this->primary->throw = null;
    $this->fallback->forget('__lvfb_breaker__');

    $this->store->get('K'); // triggers reconcile-before-read

    expect($this->primary->data['K']['token'])->toBe('T')
        ->and($this->store->pendingSyncCount())->toBe(0);
});

it('queues a deactivation made during an outage and replays it on recovery', function (): void {
    $this->store->put('K', ['token' => 'T']); // synced to primary + mirror

    $this->primary->throw = connectionError(); // primary goes down
    $this->store->forget('K');                 // deactivate while unreachable

    // Local copy is gone immediately; the delete is queued (primary still holds it).
    expect($this->fallback->has('K'))->toBeFalse()
        ->and($this->primary->data)->toHaveKey('K')
        ->and($this->store->pendingSyncCount())->toBe(1);

    // Recover and clear the breaker.
    $this->primary->throw = null;
    $this->fallback->forget('__lvfb_breaker__');

    $this->store->get('K'); // reconcile replays the queued delete before reading

    expect($this->primary->data)->not->toHaveKey('K') // deactivation finally applied
        ->and($this->store->get('K'))->toBeNull()
        ->and($this->store->pendingSyncCount())->toBe(0);
});

it('rethrows a genuine (non-connection) error instead of failing over', function (): void {
    $this->primary->throw = new PDOException('SQLSTATE[42S02]: Base table or view not found: 1146');

    $this->store->get('K');
})->throws(PDOException::class, 'Base table or view not found');

it('skips the primary during the breaker cooldown', function (): void {
    $this->store->put('K', ['token' => 'OLD']);     // mirrored

    $this->primary->throw = connectionError();
    $this->store->get('K');                          // trips the breaker

    // Primary "recovers" with a different value, but the breaker is still open.
    $this->primary->throw = null;
    $this->primary->data['K'] = ['token' => 'NEW'];

    expect($this->store->get('K'))->toBe(['token' => 'OLD']); // served from fallback, primary not read
});
