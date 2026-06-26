<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Override;
use Simtabi\Laranail\Licence\Verifier\Bindings\DomainBinding;
use Simtabi\Laranail\Licence\Verifier\Commands\ActivateLicenseCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\ClearCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\DeactivateLicenseCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\DoctorCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\DriverCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\DriversCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\FingerprintCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\KeysCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\LicenseInfoCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\ManageCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\PingCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\RefreshLicenseCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\ReminderCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\SeatsCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\SourceCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\StatusCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\TokenCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\ValidateLicenseCommand;
use Simtabi\Laranail\Licence\Verifier\Commands\WatchCommand;
use Simtabi\Laranail\Licence\Verifier\Contracts\Driver;
use Simtabi\Laranail\Licence\Verifier\Contracts\IpResolver;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseKeyResolver;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;
use Simtabi\Laranail\Licence\Verifier\Http\Middleware\CheckLicense;
use Simtabi\Laranail\Licence\Verifier\LicenceVerifier;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\Resolvers\ConfigKeyResolver;
use Simtabi\Laranail\Licence\Verifier\Resolvers\ModelKeyResolver;
use Simtabi\Laranail\Licence\Verifier\Services\FingerprintGenerator;
use Simtabi\Laranail\Licence\Verifier\Services\LicensingApiClient;
use Simtabi\Laranail\Licence\Verifier\Services\TokenStorage;
use Simtabi\Laranail\Licence\Verifier\Services\TokenValidator;
use Simtabi\Laranail\Licence\Verifier\Stores\CacheStore;
use Simtabi\Laranail\Licence\Verifier\Stores\DatabaseStore;
use Simtabi\Laranail\Licence\Verifier\Stores\FallbackLicenseStore;
use Simtabi\Laranail\Licence\Verifier\Stores\FileStore;
use Simtabi\Laranail\Licence\Verifier\Support\ConnectionChecker;
use Simtabi\Laranail\Licence\Verifier\Support\ReminderManager;
use Simtabi\Laranail\Licence\Verifier\Support\ThirdPartyIpResolver;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

final class LicenceVerifierServiceProvider extends PackageServiceProvider
{
    #[Override]
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/license-verifier')
            ->hasConfigFile('license-verifier')
            ->hasTranslations()
            ->hasMigration('create_license_verifier_table')
            ->hasCommands(
                ActivateLicenseCommand::class,
                DeactivateLicenseCommand::class,
                RefreshLicenseCommand::class,
                ValidateLicenseCommand::class,
                LicenseInfoCommand::class,
                StatusCommand::class,
                DriversCommand::class,
                FingerprintCommand::class,
                ReminderCommand::class,
                PingCommand::class,
                DoctorCommand::class,
                ManageCommand::class,
                ClearCommand::class,
                SourceCommand::class,
                DriverCommand::class,
                TokenCommand::class,
                KeysCommand::class,
                WatchCommand::class,
                SeatsCommand::class,
            )
            ->hasDoctorChecks(DoctorCommand::CHECKS)
            ->registerRouteMiddleware('license', CheckLicense::class)
            ->hasInstallCommand(fn ($command) => $command
                ->publishConfigFile()
                ->publishMigrations()
                ->askToRunMigrations()
                ->askToStarRepoOnGitHub('laranail/license-verifier'));
    }

    #[Override]
    public function packageRegistered(): void
    {
        $this->app->singleton(FingerprintGenerator::class);
        $this->app->singleton(LicensingApiClient::class);
        $this->app->singleton(TokenStorage::class);
        $this->app->singleton(TokenValidator::class);

        $this->app->singleton(LicenceVerifier::class, static function ($app): LicenceVerifier {
            $client = new LicenceVerifier(
                $app->make(FingerprintGenerator::class),
                $app->make(LicensingApiClient::class),
                $app->make(TokenStorage::class),
                $app->make(TokenValidator::class),
            );

            $client->initializeFromStoredBundle();

            return $client;
        });

        $this->app->singleton(DriverManager::class, static fn ($app): DriverManager => new DriverManager($app));

        $this->app->bind(Driver::class, static fn ($app): Driver => $app->make(DriverManager::class)->active());

        $this->app->singleton(LicenseManager::class, static fn ($app): LicenseManager => new LicenseManager(
            $app->make(DriverManager::class),
            $app->make(DomainBinding::class),
        ));

        $this->registerLicenseStore();
        $this->registerKeyResolver();

        $this->app->singleton(IpResolver::class, ThirdPartyIpResolver::class);
        $this->app->singleton(ReminderManager::class);
        $this->app->singleton(ConnectionChecker::class);
        $this->app->singleton(DomainBinding::class);
    }

    /**
     * Bind the configured license-record store (file|database|cache|callback).
     */
    private function registerLicenseStore(): void
    {
        $this->app->singleton(LicenseStore::class, function (Application $app): LicenseStore {
            $driver = (string) config('license-verifier.storage.driver', 'file');
            $primary = $this->makeStore($driver, $app);

            // Wrap a remote primary (database/cache) with an encrypted local file
            // fallback so the client stays usable when the primary is unreachable.
            // The fallback is ALWAYS a local FileStore — it must be schemaless and
            // independent of the (possibly-down) primary; `storage.fallback` only
            // toggles it on/off.
            if ($this->fallbackEnabled() && in_array($driver, ['database', 'cache'], true)) {
                return new FallbackLicenseStore($primary, new FileStore);
            }

            return $primary;
        });
    }

    private function fallbackEnabled(): bool
    {
        $fallback = config('license-verifier.storage.fallback', 'file');

        return ! in_array($fallback, [null, '', false], true);
    }

    private function makeStore(string $driver, Application $app): LicenseStore
    {
        return match ($driver) {
            'database' => new DatabaseStore,
            'cache' => new CacheStore,
            'callback' => $app->bound('license-verifier.store')
                ? $app->make('license-verifier.store')
                : new FileStore,
            default => new FileStore,
        };
    }

    /**
     * Bind the configured license-detail source resolver (config|model|callback).
     */
    private function registerKeyResolver(): void
    {
        $this->app->singleton(LicenseKeyResolver::class, static fn ($app): LicenseKeyResolver => match ((string) config('license-verifier.source', 'config')) {
            'model' => new ModelKeyResolver,
            'callback' => $app->bound('license-verifier.resolver')
                ? $app->make('license-verifier.resolver')
                : new ConfigKeyResolver,
            default => new ConfigKeyResolver,
        });
    }

    #[Override]
    public function packageBooted(): void
    {
        // package-tools registers translations under the vendor/package namespace
        // (laranail/license-verifier); also expose the short `license-verifier::`
        // namespace that the package + presets reference.
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'license-verifier');

        $this->applyMiddlewareGroups();
        $this->scheduleHeartbeat();
    }

    /**
     * Optionally attach the license check to configured middleware groups.
     */
    private function applyMiddlewareGroups(): void
    {
        $groups = (array) config('license-verifier.middleware_groups', []);

        if ($groups === []) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);

        if (! $kernel instanceof \Illuminate\Foundation\Http\Kernel) {
            return;
        }

        foreach ($groups as $group) {
            $kernel->appendMiddlewareToGroup($group, CheckLicense::class);
        }
    }

    /**
     * Schedule the heartbeat when enabled. Drivers that do not support
     * heartbeats simply no-op inside LicenceVerifier::heartbeat().
     */
    private function scheduleHeartbeat(): void
    {
        if (! config('license-verifier.heartbeat.enabled')) {
            return;
        }

        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);
            $interval = (int) config('license-verifier.heartbeat.interval', 3600);
            $minutes = max(1, (int) round($interval / 60));

            $task = $schedule->call(static function (): void {
                app(LicenseManager::class)->heartbeat();
            })->name('license-verifier:heartbeat')->withoutOverlapping();

            // Build a valid cron: minute-stepped under an hour, hour-stepped above
            // (a bare "*/120 * * * *" is out of range and collapses to hourly).
            if ($minutes === 1) {
                $task->everyMinute();
            } elseif ($minutes < 60) {
                $task->cron("*/{$minutes} * * * *");
            } else {
                $hours = max(1, (int) round($minutes / 60));
                $hours >= 24 ? $task->daily() : $task->cron("0 */{$hours} * * *");
            }
        });
    }
}
