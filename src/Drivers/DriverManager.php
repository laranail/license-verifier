<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Illuminate\Support\Manager;
use Simtabi\Laranail\Licence\Verifier\Contracts\Driver;
use Simtabi\Laranail\Licence\Verifier\LicenceVerifier;

/**
 * Resolves the active license driver from config('license-verifier.default').
 * Custom drivers can be registered with extend('name', fn ($app) => new MyDriver(...)).
 *
 * @method Driver driver(string|null $driver = null)
 */
class DriverManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('license-verifier.default', 'paseto');
    }

    /**
     * The currently configured driver instance.
     */
    public function active(): Driver
    {
        return $this->driver();
    }

    /**
     * @return array<string, mixed>
     */
    public function driverConfig(?string $name = null): array
    {
        $name ??= $this->getDefaultDriver();

        return (array) $this->config->get("license-verifier.drivers.{$name}", []);
    }

    protected function createPasetoDriver(): Driver
    {
        return new PasetoDriver($this->container->make(LicenceVerifier::class));
    }

    protected function createEnvatoDriver(): Driver
    {
        return new EnvatoDriver($this->driverConfig('envato'));
    }

    protected function createKeygenDriver(): Driver
    {
        return new KeygenDriver($this->driverConfig('keygen'));
    }

    protected function createLemonsqueezyDriver(): Driver
    {
        return new LemonSqueezyDriver($this->driverConfig('lemonsqueezy'));
    }

    protected function createGumroadDriver(): Driver
    {
        return new GumroadDriver($this->driverConfig('gumroad'));
    }

    protected function createCryptolensDriver(): Driver
    {
        return new CryptolensDriver($this->driverConfig('cryptolens'));
    }

    protected function createLicensespringDriver(): Driver
    {
        return new LicenseSpringDriver($this->driverConfig('licensespring'));
    }

    protected function createFreemiusDriver(): Driver
    {
        return new FreemiusDriver($this->driverConfig('freemius'));
    }

    protected function createEddDriver(): Driver
    {
        return new EasyDigitalDownloadsDriver($this->driverConfig('edd'));
    }

    protected function createWoocommerceDriver(): Driver
    {
        return new WooCommerceLicenseManagerDriver($this->driverConfig('woocommerce'));
    }

    protected function createPaddleDriver(): Driver
    {
        return new PaddleDriver($this->driverConfig('paddle'));
    }

    protected function createUnlockshDriver(): Driver
    {
        return new UnlockShDriver($this->driverConfig('unlocksh'));
    }

    protected function createWhopDriver(): Driver
    {
        return new WhopDriver($this->driverConfig('whop'));
    }

    protected function createAnystackDriver(): Driver
    {
        return new AnystackDriver($this->driverConfig('anystack'));
    }

    protected function createGenericDriver(): Driver
    {
        return new GenericHttpDriver($this->driverConfig('generic'));
    }

    protected function createNullDriver(): Driver
    {
        return new NullDriver($this->driverConfig('null'));
    }
}
