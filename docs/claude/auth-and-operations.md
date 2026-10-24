# Users, authentication and operations

Moved from CLAUDE.md.

## Local environment (more)

- **Redis 7** runs in Docker as the container `resort365-redis` on `127.0.0.1:6379` (restart policy `unless-stopped`, data in the `resort365-redis` volume). Docker Desktop must be running. Herd Free has no Redis service, and Homebrew's Redis 8 has to be compiled with Rust/LLVM on this Intel Mac. Recreate it with:
- **Real time (Step 3.5):** Laravel Reverb on `localhost:8080` (`php artisan reverb:start`) plus a queue worker (`php artisan queue:work`), because broadcasts are queued. `composer dev` starts both. Without them the POS and kitchen display still work, refreshing every 5 seconds. Tests use the `null` broadcaster; the channel tests switch to Reverb with test keys.
- **Browser tests (Step 7.6):** Laravel Dusk with Chrome. `.env.dusk.local` (not committed: a copy of `.env` with `DB_DATABASE=resort365_dusk`, file session and cache, `sync` queue, `log` mail and broadcasting, Pulse off) replaces `.env` while `php artisan dusk` runs, and Herd serves it. Match ChromeDriver to Chrome with `php artisan dusk:chrome-driver --detect`. Journeys share the seeded data without resetting it, so each uses demo records no other test changes; reseed before a run (`composer test:browser` does). `visitClean()` / `assertConsoleClean()` fail on console errors (script errors, CSP refusals). Chrome runs in incognito, because its password prompts swallow input after a sign-in. Coverage of Actions and Services is measured in CI only (PCOV; `scripts/coverage-report.php`, floor `scripts/coverage-floor.json`).

## Users and authentication (IAM, Platform)

- **Tenant users** (`Modules\IAM\Models\User`, the `web` guard) belong to one tenant, with email unique per tenant. Sign-in, password reset, email verification and TOTP 2FA come from **Laravel Fortify**, whose routes are served on tenant subdomains only (`config/fortify.php`, with views in `Modules/IAM/resources/views/auth`). There's no self-registration; users join by **invitation**, a signed link valid for 7 days (`/iam/users`).
- **Statuses:** `invited`, `active`, `inactive`. Only active users can sign in. `EnsureUserIsActive` signs out a deactivated user on their next request, and `EnsureUserBelongsToTenant` ends a session presented on another tenant's subdomain.
- **Middleware order** in the `tenant` group matters, and is pinned by a test: `IdentifyTenant`, `EnsureUserBelongsToTenant`, `SetCurrentProperty`, `EnsureUserIsActive`, `SetUserLocale`, `EnsureTwoFactorEnabled`, then `auth`. Modules add to the group through the HTTP kernel (`appendMiddlewareToGroup`), not the router.
- **Security:**
  - Password policy: `Password::defaults()` in `AppServiceProvider`.
  - Lockout: 5 failed sign-ins per tenant, email and IP (Fortify with `TenantLoginRateLimiter`).
  - Idle timeout: `SESSION_LIFETIME`.
  - Reset tokens are stored per tenant (`User::getEmailForPasswordReset()`).
  - Every sign-in event is recorded in `login_histories`.
- **Profile** (`/iam/profile`): details, password, language, colour mode (saved to the user, including from the navbar toggle), 2FA setup and recent sign-ins.
- **Platform admins** (`Modules\Platform\Models\PlatformAdmin`, the `platform` guard) sign in on the central domain at `/platform/login`. Create one with `php artisan platform:create-admin <email> "<Name>"`. Two-factor sign-in (Phase 7 review): Console → Security (`/platform/security/two-factor`, an authenticator app plus 8 one-time recovery codes; `StartPlatformTwoFactor`, `ConfirmPlatformTwoFactor`, `VerifyPlatformTwoFactor` with Fortify's provider); once on, the password step is followed by `/platform/two-factor-challenge` (5 minutes, limiter `platform-two-factor`). `EnsurePlatformTwoFactor` (console, Horizon, Pulse) requires it when `platform.require_two_factor` is true, default null = production only, so the local demo admin needs none. Lost phone and codes: `php artisan platform:reset-two-factor <email>`.
- **Demo logins** (password `password`): one user per default role in each tenant, named `<mailbox>@rodelaresort.com` at `http://rodela.resort365.test` and `<mailbox>@greenvalley.test` at `http://greenvalley.resort365.test`. Mailboxes: `owner`, `gm`, `fomanager`, `frontdesk`, `reservations`, `housekeeping`, `maintenance`, `fnb`, `cashier`, `waiter`, `chef`, `bartender`, `store`, `procurement`, `accountant`, `hr`, `payroll`, `auditor` (see `DefaultRole::demoMailbox()`). The platform admin is `admin@resort365.test` at `http://resort365.test/platform/login`. API: `curl -H "Authorization: Bearer DEMO-API-RODELA-0001" http://rodela.resort365.test/api/v1/iam/me` (documents at `http://resort365.test/docs/api`). Mail uses the `log` driver: invitation and reset emails appear in `storage/logs/laravel.log`. Locally, the invitation link is also shown after sending.

## Hardening and operations (Step 7.5, docs/OPERATIONS.md)

- **Security headers** (`App\Http\Middleware\SecurityHeaders`, every response): a CSP with a per-request nonce. Give any inline `<script>` `nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}"`, and never write inline event handlers (`onclick=`…): use `data-submit-on-change`, `data-print`, Alpine or a module script. External scripts, styles or fonts must be added to the policy.
- **Lazy loading throws** outside production: eager-load relations (`with()`), or fetch on purpose.
- **Cache only plain values**: the cache does not rebuild objects (`cache.serializable_classes = false`); store arrays and rebuild DTOs (`Data::from`).
- **Production refuses `APP_DEBUG=true`.** Rate limiters `reports` and `device-api` (AppServiceProvider). Scheduled tasks use `onOneServer()->withoutOverlapping()`. Long jobs go on the `long` queue (`redis-long` connection when the queue is Redis).
- **Monitoring:** Horizon at `/platform/horizon` and Pulse at `/platform/pulse` (central domain, platform admins), Sentry with `SENTRY_LARAVEL_DSN` (tenant slug and user id only), `/up` checks the database and cache.
- **Backups and restore:** `spatie/laravel-backup` nightly to the `backups` disk (`backups-s3` in production), `backup:restore-drill` monthly, `scripts/restore.sh` for a real restore. Deploy with Deployer (`deploy.php`, `deploy/`, GitHub Actions *Deploy*).
- **Load test:** `tests/Load` (k6). Locally Herd adds several hundred milliseconds per request, so the response-time budget is judged on staging; locally it proves there are no errors under concurrency.
