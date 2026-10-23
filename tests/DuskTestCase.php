<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\Browser;
use Laravel\Dusk\Chrome\ChromeProcess;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;
use Symfony\Component\Process\Process;

/**
 * Browser tests (Step 7.6): Chrome against a served copy of the app with the demo data (locally Herd with `.env.dusk.local`, in CI
 * `php artisan serve`). The data is seeded once per run (`composer test:browser`), not reset between tests, so each journey uses
 * data no other journey changes. After every page, `visitClean()` fails on any console error: a script error or a request the
 * Content-Security-Policy refused.
 */
abstract class DuskTestCase extends BaseTestCase
{
    private static ?Process $sharedDriver = null;

    /**
     * Console messages that are not the application's fault: no Reverb server runs for the browser tests, and the POS and kitchen
     * screens fall back to polling without it (Step 3.5).
     */
    private const array IGNORED_CONSOLE = ['favicon.ico', 'WebSocket connection to', 'Failed to load resource: net::ERR_CONNECTION_REFUSED'];

    /** Starts ChromeDriver for the run (tests/Pest.php calls this before the browser tests), unless one is given (DUSK_DRIVER_URL). */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (static::runningInSail() || self::driverUrl() !== null) {
            return;
        }

        // One ChromeDriver for the whole run (Dusk's own helper stops it after each test file, and the next file would then connect
        // to a closing driver). The process object stops it when PHP exits.
        if (self::$sharedDriver instanceof Process) {
            return;
        }

        self::$sharedDriver = (new ChromeProcess)->toProcess(['--port=9515']);
        self::$sharedDriver->start();
        usleep(500_000);
    }

    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1440,1000',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            // After a sign-in Chrome's password prompts ("Save password?", "found in a data breach") take every key and click, even
            // headless: incognito has no password manager.
            '--incognito',
            '--password-store=basic',
        ])->unless($this->hasHeadlessDisabled(), fn (Collection $items): Collection => $items->merge(['--disable-gpu', '--headless=new']))->all());

        $options->setExperimentalOption('prefs', [
            'credentials_enable_service' => false,
            'profile.password_manager_enabled' => false,
            'autofill.profile_enabled' => false,
            'autofill.credit_card_enabled' => false,
        ]);

        $capabilities = DesiredCapabilities::chrome()->setCapability(ChromeOptions::CAPABILITY, $options);
        $capabilities->setCapability('goog:loggingPrefs', ['browser' => 'ALL']);

        return RemoteWebDriver::create(self::driverUrl() ?? 'http://localhost:9515', $capabilities);
    }

    /** A ChromeDriver started elsewhere (`DUSK_DRIVER_URL`), read from the process environment because tests run before config. */
    private static function driverUrl(): ?string
    {
        $url = $_ENV['DUSK_DRIVER_URL'] ?? getenv('DUSK_DRIVER_URL');

        return is_string($url) && $url !== '' ? $url : null;
    }

    /** A page on a tenant's subdomain, e.g. `$this->tenantPage('rodela', '/dashboard')`. */
    protected function tenantPage(string $slug, string $path = '/'): string
    {
        $url = (string) config('app.url');
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'http';
        $port = parse_url($url, PHP_URL_PORT);

        return $scheme.'://'.$slug.'.'.config('tenancy.central_domain').($port !== null ? ':'.$port : '').'/'.ltrim($path, '/');
    }

    /** Signs in through the tenant's own sign-in form (demo password `password`). */
    public function signIn(Browser $browser, string $email, string $slug = 'rodela'): Browser
    {
        $browser->visit($this->tenantPage($slug, '/login'))
            ->type('email', $email)
            ->type('password', 'password')
            ->press('Sign in')
            ->waitForLocation('/dashboard', 15);

        return $this->assertConsoleClean($browser);
    }

    /** Visits a page and fails on any console error it causes. */
    public function visitClean(Browser $browser, string $url): Browser
    {
        return $this->assertConsoleClean($browser->visit($url));
    }

    /** Fails on the errors the browser logged since the last check (script errors, refused requests, CSP violations). */
    public function assertConsoleClean(Browser $browser): Browser
    {
        $errors = collect($browser->driver->manage()->getLog('browser'))
            ->filter(fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE')
            ->map(fn (array $entry): string => (string) $entry['message'])
            ->reject(fn (string $message): bool => collect(self::IGNORED_CONSOLE)->contains(fn (string $ignored): bool => str_contains($message, $ignored)))
            ->values()->all();

        $this->assertSame([], $errors, 'The browser console reported errors on '.$browser->driver->getCurrentURL());

        return $browser;
    }

    /**
     * Signs out of the tenant through the navbar's form, so the session ends on the server too: deleting the cookies alone loses to
     * a poll still in flight, whose response sets the signed-in session cookie again.
     */
    public function signOut(Browser $browser, string $slug = 'rodela'): void
    {
        $browser->script("document.querySelector('form[action$=\"/logout\"]')?.submit()");
        $browser->pause(500);
        $browser->driver->manage()->deleteAllCookies();
        $browser->visit($this->tenantPage($slug, '/login'))->waitFor('[name=email]', 15);
    }
}
