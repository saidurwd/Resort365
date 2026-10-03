# Resort365 — Claude Code Guide

Resort365 is a multi-tenant SaaS resort management system: booking engine, front office, billing, restaurant POS, housekeeping, inventory, procurement, accounting, HR and payroll. A **tenant** is a company that may run several resorts (**properties**). Each property has cottages, and each cottage has rooms. It is a **modular monolith**: one Laravel app, split into modules under `Modules/`.

## Source-of-truth documents

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): **what** to build (requirements, design, data model, conventions).
- [docs/DEVELOPMENT_PLAN.md](docs/DEVELOPMENT_PLAN.md): **in what order** to build it. It is split into phases and steps, and has a progress tracker (§3).
- [docs/MODULE_GUIDE.md](docs/MODULE_GUIDE.md): **how** to create a module and generate classes in it, what is public between modules, and what the architecture tests enforce.

Work one plan step per session. Before planning, read the step and every ARCHITECTURE section it lists under "Read". If the implementation must differ from the documents, say so in the plan and update the document in the same step (rule R13).

## Stack

| Area | Choice |
|---|---|
| Framework | Laravel 13 · PHP 8.4 |
| Database | MySQL 8.4 (InnoDB, `utf8mb4`); the schema stays PostgreSQL-compatible |
| Cache / queue / session | Redis 7 (phpredis) |
| UI | Blade + AdminLTE 4 (Bootstrap 5.3) + Alpine.js, built with Vite |
| Modules | `nwidart/laravel-modules` |
| Key packages (added in later steps) | Fortify, Sanctum, spatie/laravel-permission (teams), spatie/laravel-activitylog, spatie/laravel-medialibrary, brick/money, yajra/laravel-datatables, Horizon, Reverb |
| Quality | Pest, Laravel Pint, Larastan (level 6), Rector |
| CI | GitHub Actions (`.github/workflows/ci.yml`): lint → analyse → test, on MySQL 8.4 + Redis 7 |

Only packages that a completed step needs are installed. See ARCHITECTURE §4.6 for the full stack.

## Local environment

- **Laravel Herd** serves the app at `http://resort365.test` (the central domain). Tenants will use subdomains such as `sunrise.resort365.test` (Step 0.4).
- **Database:** locally the Homebrew **MariaDB** service on port 3306, with user `resort365` / `secret` and the databases `resort365` (app) and `resort365_testing` (tests). CI and production run **MySQL 8.4**, so keep `DB_CONNECTION=mysql` and write SQL that works on both. CI is the authority on MySQL compatibility.
- **Redis 7** runs in Docker as the container `resort365-redis` on `127.0.0.1:6379` (restart policy `unless-stopped`, data in the `resort365-redis` volume). Docker Desktop must be running. Herd Free has no Redis service, and Homebrew's Redis 8 has to be compiled with Rust/LLVM on this Intel Mac. Recreate it with:
  `docker run -d --name resort365-redis --restart unless-stopped -p 127.0.0.1:6379:6379 -v resort365-redis:/data redis:7-alpine redis-server --appendonly yes`
- First-time setup: `cp .env.example .env && php artisan key:generate && php artisan migrate`.

## Commands

| Command | What it does |
|---|---|
| `composer test` | Run the Pest suite (`php artisan test`) against the `resort365_testing` database |
| `composer lint` | Check code style (Pint) and Rector rules without changing files |
| `composer fix` | Apply Rector refactorings, then Pint formatting |
| `composer analyse` | Larastan static analysis, level 6 |
| `npm run dev` / `npm run build` | Vite dev server / production build of `resources/scss` and `resources/js` |
| `php artisan test --filter=<name>` | Run a single test |
| `php artisan migrate:fresh --seed` | Rebuild the local database with reference data and demo data (tenants `sunrise`, `greenvalley`); also flushes the cache |
| `php artisan tenant:create <slug> "<Name>"` | Create a tenant; open `http://<slug>.resort365.test` |
| `php artisan platform:create-admin <email> "<Name>"` | Create a platform super admin (prompts for the password) |
| `php artisan permissions:sync [--prune]` | Store registered permissions and update every tenant's default roles |
| `php artisan module:make <Name>` then `composer dump-autoload` | Create a module with the §11 structure |
| `php artisan module:make-action <Class> <Module>` (also `-enum`, `-event`, `-model … -mf`, `-request`, `-interface`, …) | Generate a class inside a module; see MODULE_GUIDE §2 |
| `php artisan test --testsuite=Architecture` | Run only the architecture tests |

**Quality gate (R12):** before reporting a step as done, run `composer fix`, then make sure `composer lint`, `composer analyse` and `composer test` all pass. Generated stubs (`artisan make:*`) usually need `composer fix` to satisfy Rector, which adds closure return types.

## Workflow for a plan step

1. Read this file, the Standard Step Rules below, the step, and the ARCHITECTURE sections it lists.
2. Plan first: list the files you will create or change, your assumptions and any conflicts. Wait for approval.
3. Implement the whole step following the rules below, then run the quality gate.
4. Report what was built, how to try it in the browser (URLs, demo logins), test results and any deviations.
5. When the user confirms, tick the step in DEVELOPMENT_PLAN §3 and commit with `feat(step-X.Y): <short summary>`.

## Standard Step Rules (DEVELOPMENT_PLAN §2)

| # | Rule |
|---|---|
| R1 | **Scope:** build only what the step lists. If a later step's feature seems necessary, add a contract or a clearly marked `TODO(step-X.Y)` and mention it in the report. Do not build it. |
| R2 | **Tenancy:** every new tenant-owned table has `tenant_id` (and `property_id` where the data belongs to one resort). Its model uses `BelongsToTenant` / `BelongsToProperty`, and the model is added to the tenant-isolation test dataset. |
| R3 | **Migrations:** foreign keys, composite indexes starting with `tenant_id`, `DECIMAL(15,2)` for money, `DATE` for stay dates. Never edit a migration from an earlier committed step; add a new migration. |
| R4 | **Models:** casts, PHP backed enums for statuses and types, relationships, and a factory with realistic data. Activity logging on business models. |
| R5 | **Logic:** in Actions and Services only, never in controllers, models or Blade. Multi-row writes in `DB::transaction()`. Events dispatched after commit. |
| R6 | **Modules:** other modules are used only through their Contracts, DTOs, Enums and Events (see ARCHITECTURE §4.3). |
| R7 | **Validation:** FormRequests; every `exists` and `unique` rule is tenant-scoped. |
| R8 | **Authorization:** permissions named `module.resource.action`, registered in the module's service provider, enforced by policies and route middleware, and assigned to the default roles seeder. |
| R9 | **UI:** AdminLTE layout and the shared Blade components; server-side DataTables for lists; breadcrumbs; a sidebar menu item registered with its permission; every string through `__()`. |
| R10 | **Tests:** a feature test per action and endpoint (success, validation failure, unauthorized), unit tests for every calculation, and the tenant-isolation test. |
| R11 | **Demo data:** extend `DemoSeeder` so the step can be tried immediately after `php artisan migrate:fresh --seed`. |
| R12 | **Quality gate:** Pint, Larastan and Pest all pass before reporting the step as done. |
| R13 | **Documents:** if the implementation must differ from ARCHITECTURE.md, say so in the plan, and update the document in the same step once approved. |

## Coding conventions (ARCHITECTURE §12)

1. **Tenancy:** every tenant-owned model uses `BelongsToTenant`; every property-level model also uses `BelongsToProperty`. Never call `withoutGlobalScopes()` outside Platform code.
2. **Validation:** always use FormRequests; `exists` and `unique` rules are always tenant-scoped.
3. **Business logic** lives in Actions and Services, never in controllers, models or Blade.
4. **Transactions:** an Action that writes more than one row wraps the work in `DB::transaction()`.
5. **Events** are dispatched after commit (`ShouldDispatchAfterCommit`).
6. **Module boundaries:** a module may use another module's **Contracts**, **Enums**, **DTOs** and **Events** only, never its Models directly for writes. This is enforced with Pest architecture tests.
7. **Enums:** every status or type is a PHP backed enum with `label()` and `color()` for badges.
8. **Money:** never use floats. Use the `Money` cast and `brick/money` for calculation; round only at defined points.
9. **Dates:** stay dates are `Carbon` date-only; timestamps are stored in UTC and displayed in the property timezone.
10. **Authorization:** every controller action is authorized through a Policy or permission middleware.
11. **Naming:** tables are plural snake_case; models singular; routes are kebab-case and named `module.resource.action`; permissions are `module.resource.action`.
12. **UI:** use the shared Blade components; no inline styles; every string goes through `__()`.
13. **Tests:** every Action has a feature test; every tenant model has an isolation test; money and booking logic have unit tests.
14. **Quality gates:** Pint, Larastan and Pest must pass before a commit.
15. **POS screens:** Blade renders the page shell; Alpine.js holds the order state and calls JSON endpoints (`/pos/api/...`) that reuse the same Actions as the admin screens. Business rules are never duplicated in JavaScript; the server recalculates every total.

Inside a module, keep to the layering in ARCHITECTURE §4.4. Controllers are thin (authorize, validate, delegate, respond). Actions own one use case and its transaction. Services hold stateless domain logic. DTOs are `readonly` classes.

## Tenancy (ARCHITECTURE §4.2)

- **Tenant** = a customer company: `App\Models\Tenant` (central table `tenants`), served from `{slug}.{TENANCY_CENTRAL_DOMAIN}`, e.g. `sunrise.resort365.test`. Statuses: `trial` and `active` can use the app. `suspended` shows the suspended page (403). `cancelled` and unknown subdomains get 404.
- **Routes:**
  - `routes/web.php` holds the **central** domain (welcome page, `/ui-kit`).
  - `routes/tenant.php` and every module's routes are **tenant** routes, with the `web` and `tenant` middleware on `{tenant}.` domains.
  - `IdentifyTenant` sets the tenant **before** route-model binding, hides the `{tenant}` parameter from controllers, and makes `route()` fill it in.
- **`TenantContext`** (scoped per request and per job) holds the current tenant: `id()`, `tenant()`, `tenantOrFail()`, and `run($tenant, fn)` for console commands, seeders and scripts.
- **Tenant-owned models** use `App\Support\Tenancy\BelongsToTenant`, which **fails closed**: using the model with no current tenant throws `TenantContextMissing`. It fills `tenant_id` on create, and refuses to change `tenant_id` or to write another tenant's record (`TenantMismatch`). An architecture test requires the trait on every module model.
- **Properties (resorts):** `Modules\Property\Models\Property`. A user works in the properties assigned in `property_user` (screen: `/property/access`), or in every property with `property.property.access-all` (Tenant Owner, Auditor).
  - **`PropertyContext`** (app shell, per request): set by `SetCurrentProperty`, which also picks the **current property** (session; navbar switcher posts to `/property/switch/{id}`). The navbar also shows the current property's **business date**.
  - **Property-level models** use `App\Support\Tenancy\BelongsToProperty` together with `BelongsToTenant`. Queries only return the user's properties, `property_id` comes from the current property on create, and writes to other properties are refused (`PropertyAccessDenied`). Screens about "this property" add `where('property_id', $context->currentId())`. Outside a user request (console, jobs), only the tenant scope applies.
  - **Property harness:** `tests/Tenancy/PropertyIsolationTest.php` runs every `BelongsToProperty` model automatically.
  - **Other modules** read property details through `Modules\Property\Contracts\PropertyDirectory`, never the model; a new property fires `App\Support\Tenancy\Events\PropertyCreated` (ids only).
- **Cottages and rooms** (Property module, Setup menu): cottage types, room types, cottages (`booking_mode` `rooms_only` / `whole_only` / `both`) and rooms are property-level and soft-deleted; amenities and departments are tenant-wide. These screens work on the **current property**. New cottages come from the quick "add cottage with N rooms" form (`CreateCottageWithRooms`, numbers from `RoomNumberSequence`). Capacity comes from `OccupancyCalculator`. Deleting a room asks `Modules\Property\Contracts\RoomUsage::hasFutureBookings()`; Property binds a "no bookings" default until Reservation binds the real one (Step 1.6). Permissions: `property.{cottage,room,amenity,department}.{view,manage}`.
- **Guests** (Guest module, sidebar **Guests**): guests, companies and travel agents are tenant-wide. Other modules use `Modules\Guest\Contracts\GuestLookup` (search, find, isBlacklisted, companies, travel agents) and listen to `GuestsMerged` (move records from the merged guest to the kept one). `SaveGuest` normalises the phone (`PhoneNumber`, setting `guest.default_calling_code`) and keeps `id_number_hash` (`IdNumberHasher`) in step with the encrypted `id_number`; never write guests around it. Search (`GuestSearch`) uses indexed prefixes: phone, email, ID hash, name words. ID numbers show in full and ID documents download only with `guest.guest.view-id`. The navbar **quick search** (`$quickSearch`, shared by the Guest module) opens the guest list with the term; reservations join it later.
- **Rates** (Rates module, sidebar **Rates**): seasons (with periods), rate plans, rates per room/cottage type × season × weekday set, date prices (`rate_overrides`) and restrictions, all per property. Rates reaches Property only through `Modules\Property\Contracts\InventoryCatalog` (room and cottage types as `UnitTypeSummary`, keys like `room_type:4`). `NightlyRateResolver` (pure) decides a night's price; `RateCalendar` loads a plan's data for a date range. Rates factories find a property through `ResolvesProperty`, because they may not use Property's models. Deposit and cancellation policies (one default each per property; a rate plan may choose others) and promotions also live here; `DepositCalculator`, `CancellationFeeCalculator` and `PromotionMatcher` are pure and unit-tested (the §6.5 worked example is a test).
- **Reservations** (Reservation module, sidebar **Reservations**): `inventory_locks` (one row per room and night, `UNIQUE(room_id, stay_date)`; types reservation, hold, out of order, owner block) is the single source of availability. `AvailabilityService` / `PricingService` use only contracts: `InventoryCatalog::rooms()/cottages()` and `Rates\Contracts\RateLookup`. The pure `AvailabilityCalculator`, `RestrictionChecker` and `PriceCalculator` hold the rules and are unit-tested. Reservation binds `LockedRoomUsage` as Property's `RoomUsage`.
- **Validation:** `TenantRule::exists('table')` and `TenantRule::unique('table', 'column')`, never plain `Rule::exists` or `Rule::unique`.
- **Queued jobs** that touch tenant data implement `TenantAware` and use `InteractsWithTenant`, which restores the dispatching tenant. Dispatch as a statement inside `TenantContext::run()`; a returned `PendingDispatch` queues the job too late.
- **Cache and files:** `TenantCache` (keys `t:{id}:…`) and `TenantStorage` (paths `tenants/{id}/…`).
- **Isolation tests:** `tests/Tenancy/TenantIsolationTest.php` automatically runs every model that uses `BelongsToTenant` (found in `app/Models` and `Modules/*/app/Models`) through the list, find, route-binding, update, delete, create, move, reference and fail-closed checks. A new tenant model only needs a factory. Test-only tables live in `tests/Fixtures/Tenancy/migrations`.
- **Development:** `php artisan tenant:create <slug> "<Name>" [--email=] [--status=trial|active|suspended|cancelled]`. `php artisan migrate:fresh --seed` creates the demo tenants `sunrise` and `greenvalley`.

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
- **Platform admins** (`Modules\Platform\Models\PlatformAdmin`, the `platform` guard) sign in on the central domain at `/platform/login`. Create one with `php artisan platform:create-admin <email> "<Name>"`.
- **Demo logins** (password `password`): one user per default role in each tenant, named `<mailbox>@sunrise.test` at `http://sunrise.resort365.test` and `<mailbox>@greenvalley.test` at `http://greenvalley.resort365.test`. Mailboxes: `owner`, `gm`, `fomanager`, `frontdesk` (Cox's Bazar only), `reservations`, `housekeeping`, `maintenance`, `fnb`, `cashier`, `waiter`, `chef`, `bartender`, `store`, `procurement`, `accountant`, `hr`, `payroll`, `auditor` (see `DefaultRole::demoMailbox()`), plus `frontdesk.sylhet@sunrise.test` (Sunrise Sylhet only). The platform admin is `admin@resort365.test` at `http://resort365.test/platform/login`. Mail uses the `log` driver: invitation and reset emails appear in `storage/logs/laravel.log`. Locally, the invitation link is also shown after sending.

## Core services (ARCHITECTURE §5.1, §9.2)

Other modules use these through Core's **contracts** (`Modules\Core\Contracts\*`), never Core's models.

- **Settings** (`Settings`): register a `SettingDefinition` (`module.name`, a `SettingType`, a default, a scope of tenant or property, a group) in your provider. Read it with `Settings::get('core.night_audit_time', $propertyId)`: the property value wins, then the tenant value, then the default, cast to the type. Values are cached per tenant and edited at `/core/settings`, company-wide or per property (`?property=`).
- **Document numbers** (`DocumentNumbers`): register a `DocumentType` (key, prefix, format such as `{PREFIX}-{YYYY}-{SEQ:5}`, yearly reset or never), then call `next('reservation', $propertyId)` **inside the transaction that saves the document**. Numbers are taken under a row lock and are gap-free. New tenants get every sequence up front (`CreateDocumentSequences`). If you wrap `next()` in your own transaction, pass retry attempts, because a lock conflict rolls back the whole transaction. Sequences are set up at `/core/document-sequences`.
- **Audit log:** add `App\Support\Audit\RecordsActivity` to every business model (R4). It records create, update and delete with old and new values and who made the change, and never the hidden attributes, keys or timestamps. For pivot or other changes model events don't see, log explicitly with `activity()->performedOn($model)->withProperties(['old' => …, 'attributes' => …])->log('…')`. Show history with `<x-audit-trail :entries="app(AuditTrail::class)->for($model)" />`. The whole tenant's log is at `/core/audit-log` (`core.audit.view`).
- **Attachments:** a model implements `Spatie\MediaLibrary\HasMedia`, uses `App\Support\Attachments\HasAttachments`, has a morph-map alias and a policy (`view` to download, or `viewAttachments` when the policy has it; `update` to upload or delete). Render `<x-attachments :subject="$model" />`. For a **photo gallery** use `HasPhotos` instead (adds the `photos` collection with a `thumb` conversion) and render `<x-photo-gallery :subject="$model" />`; uploads send `collection=photos`. Files live on the private `attachments` disk under `tenants/{id}/media/…` and are served only through Core's authorized routes. `ATTACHMENTS_DISK=s3` switches to S3-compatible storage.
- **Reference data:** central `countries`, `currencies` and `timezones` tables (ISO codes, `ReferenceDataSeeder`). Exchange rates are per tenant, through `ExchangeRates::rate('USD', 'BDT', $date)`, as decimal strings.
- **Notifications:** notifiable models return `App\Models\DatabaseNotification` from `notifications()`, so in-app notifications carry `tenant_id`. The navbar bell shows them, with all of them at `/core/notifications`. Use `database` channel data keys `title`, `body`, `icon` and `url`. For wording, register a `NotificationTemplateDefinition` and render it with `NotificationTemplates::render(key, channel, data)`. Tenants may override a template (`notification_templates`); an editor screen comes later.
- **Taxes** (`TaxEngine`): taxes and tax categories per tenant (Setup → Taxes). Other modules store a tax category id and call `TaxEngine::calculate(amount, categoryId, inclusive, quantity)`, which returns a `TaxBreakdown` of decimal strings. The math lives in `TaxCalculator` (brick/math `BigDecimal`; each tax rounded half-up, inclusive net = gross − taxes).
- **Mandatory 2FA:** the `iam.require_two_factor` setting (Settings → Security) sends Tenant Owners and General Managers without 2FA to their profile.
- **Local MariaDB note:** MariaDB has snapshot isolation on, so a locking read of a row changed after the transaction's first plain read fails with error 1020. Laravel retries it as a concurrency error. MySQL 8.4 (CI and production) behaves normally.

## Roles, permissions and the sidebar (ARCHITECTURE §3, §10.2)

- **Permissions** are named `module.resource.action`. Each module registers its permissions in its service provider through `App\Support\Authorization\PermissionRegistry`, as `PermissionDefinition(name, label, defaultRoles)`. Tenant Owner gets every permission and Auditor every `*.view`, automatically.
- **After adding or changing permissions**, run `php artisan permissions:sync [--prune]`. It stores them and updates every tenant's **default roles**: the 18 roles from §3.2, which are read-only system roles. Tenants build **custom roles** at `/iam/roles`, and new tenants get the default roles automatically (`TenantCreated`).
- **Roles belong to a tenant:** spatie/laravel-permission with teams = tenants (`TenantTeamResolver`), and a per-tenant permission cache (`UseTenantPermissionCache`).
- **Enforce permissions twice:** with `can:<permission>` route middleware and with a Policy in the controller (R8). Default roles get their permissions from the registry, never by hand.
- **Sidebar:** each module registers groups and items in `App\Support\Menu\MenuRegistry` (label, icon, route, order, permission, module). The sidebar shows only what the user may see in enabled modules. Shared groups such as `setup` are created with `$menu->group()`.
- **Modules per tenant:** the `tenant_modules` table (no row means enabled; plans come in Phase 7). Module routes get `module:<alias>` middleware from the route-provider stub, so a disabled module returns 403 and its menu disappears. `core`, `iam` and `platform` are always on.
- **Tests:** `withDefaultRoles($tenant)`, `tenantUserAs($tenant, DefaultRole::X)` and `roleId(...)` in `tests/Pest.php`.

## UI (ARCHITECTURE §10)

- **Stack:** AdminLTE 4 and Bootstrap 5.3, compiled from SCSS (`resources/scss/app.scss`; tokens in `_variables.scss`), Bootstrap Icons, Alpine.js, Tom Select, flatpickr, DataTables (Bootstrap 5) and SweetAlert2. Server-side tables use `yajra/laravel-datatables-oracle`.
- **Layouts** live in `resources/views/layouts` and are used as components: `<x-layouts::app :title :subtitle :breadcrumbs>` (with an optional `actions` or `header` slot; property screens pass the property name as `subtitle`), `<x-layouts::guest>` (sign-in pages) and `<x-layouts::print>` (A4 documents, `resources/scss/print.scss`). The POS and KDS layouts come in Phase 3.
- **Components** live in `resources/views/components`: `card`, `page-header`, `form.input`, `form.select` (Tom Select), `form.date` (flatpickr, submits `Y-m-d`), `form.money` (decimal string plus currency, never a float), `status-badge` (any `HasLabelAndColor` enum), `datatable`, `modal`, `confirm-delete`, `stat-box`, `empty-state`, `flash-messages`, `attachments`, `photo-gallery`, `approval-panel` and `audit-trail`. Form components handle the label, required marker, help text, `old()` input and validation errors, so always use them.
- **JS behaviour comes from data attributes:**
  - `data-tom-select` and `data-flatpickr` (JSON options) set up selects and date pickers. Tom Select option `{"remote": url}` searches the server as the user types (`url?q=term` returns `[{value, text}]`).
  - `data-datatable` (JSON config) sets up server-side tables.
  - `data-confirm="Title"` on a form or button opens the global confirm dialog; `data-confirm-text`, `data-confirm-variant` and the other `data-confirm-*` attributes customise it.
  - After inserting HTML, call `window.initUi(element)`.
- **Colour mode:** light, dark or auto through AdminLTE's colour mode (`localStorage` key `lte-theme`, applied before first paint). Enum `color()` values are `primary`, `secondary`, `success`, `danger`, `warning` or `info`, because `light` and `dark` are unreadable in one of the modes.
- **`/ui-kit`** (local only; 404 elsewhere) shows every layout piece and component. Add new shared components to it, and check it in light and dark mode and at tablet width.

## Modules

Modules live in `Modules/<Name>/` (`nwidart/laravel-modules`, autoloaded through each module's `composer.json`). So far: **Core**, **IAM**, **Platform**, **Property**, **Guest**, **Rates** and **Reservation**. Each module is created by the plan step that first needs it. Dependencies point one way only (ARCHITECTURE §4.3): a downstream module reacts to an upstream module's events, and an upstream module never calls a downstream one.

| Module | Purpose | Depends on |
|---|---|---|
| Core | Tenancy context, settings, document numbering, approval workflow, notifications, tax engine, audit log, attachments, reference data, menu and permission registry | — |
| Platform | SaaS: tenant lifecycle, plans and module entitlements, subscriptions, super-admin console | Core |
| IAM | Users, invitations, login, 2FA, roles and permissions, property access | Core |
| Property | Properties, property access (`property_user`), switcher and business date, cottage types, cottages, room types, rooms, amenities, departments | Core, IAM (`UserDirectory` contract) |
| Guest | Guest profiles (CRM), companies, travel agents | Core |
| Rates | Seasons, rate plans, rates, deposit and cancellation policies, promotions | Property |
| Reservation | Booking engine: availability, pricing, reservations, inventory locks, holds | Property, Rates, Guest |
| FrontOffice | Front desk, check-in and check-out, stay changes, tape chart, night audit, cashier shifts | Reservation |
| Billing | Folios, charges, payments, invoices, refunds; `FolioPostingContract` | Reservation |
| Restaurant | F&B outlets, menus, tables, POS orders, KOT and kitchen display, charge to room, meal-plan redemption | Property, Billing (Inventory optional) |
| Housekeeping | Room status board, cleaning tasks, maintenance, out-of-order rooms | Property |
| Inventory | Items, units, stores, stock ledger, transfers, requisitions, stock counts, recipes | Core |
| Procurement | Vendors, purchase requisitions, RFQs, purchase orders, goods receipt, vendor bills and payments | Inventory |
| Accounting | Double-entry GL, chart of accounts, periods, automatic posting from other modules' events, statements | Core (listens to events only) |
| HR | Organisation, employees, shifts, attendance, leave | Core |
| Payroll | Salary components and structures, payroll runs, payslips, loans, service charge distribution (Bangladesh defaults) | HR |
| Reports | Dashboards, KPIs, cross-module reports | Core |

## Project structure

- `app/`: application shell only. Business code goes in modules. `app/Support/` holds the shared foundations: `Actions\Action`, `DTOs\Data`, `Enums\HasLabelAndColor` + `EnumHelpers`, `Tenancy\*` (tenant and property context), `Authorization\*`, `Menu\*`, `Audit\RecordsActivity` and `Attachments\*`. Money helpers come with the first money feature.
- `Modules/<Name>/`: a self-contained module (see ARCHITECTURE §11 for the internal layout).
- `database/`: central (non-module) migrations and seeders.
- `tests/`: cross-module tests, `tests/Architecture` (auto-discovers every module) and `tests/Fixtures`. Module tests live in `Modules/<Name>/tests`.
- `stubs/modules/`: project stubs for the module generators. Edit these, not vendor, to change what `module:make*` produces.
- `docs/adr/`: short Architecture Decision Records for changes of direction.

## Demo data

| Tenant | Subdomain (local) | Properties |
|---|---|---|
| Sunrise Resorts Ltd | `sunrise.resort365.test` | Sunrise Cox's Bazar (`CXB`: 8 cottages, 15 rooms: 3 single-room honeymoon cottages, 3 two-room garden cottages, 2 three-room family villas booked whole only), Sunrise Sylhet (`SYL`: 4 cottages, 10 rooms) |
| Green Valley Resort | `greenvalley.resort365.test` | Green Valley (`GVR`: 5 cottages, 9 rooms) |

Each tenant gets one demo user per default role (e.g. `frontdesk@sunrise.test` / `password`), 15 amenities, 11 departments, 8 companies and 5 travel agents. Sunrise has 10,000 guests (Green Valley 200), including a duplicate pair (two "Rahim Uddin" with phone 01711-000001) and a blacklisted guest (Kamal Hossain); see `database/seeders/DemoGuests.php`. Taxes (service charge 10% then VAT 15%, compound) and rates come from `DemoRates.php`: Cox's Bazar has Peak, Shoulder and Monsoon seasons, Room Only / Bed & Breakfast / Half Board plans, a New Year's Eve date price, a 2-night minimum over New Year and a stop-sell date for the family villas; every property has a *Standard advance* deposit policy (30%, negotiable, 30 minutes to pay) and the *Flexible* cancellation policy; Cox's Bazar also has *Non-refundable* (used by the *Non-refundable saver* plan) and the promotions *Long stay* (automatic, 7+ nights), **MONSOON20** and **EARLYBIRD**. `DemoLocks.php`: room 702 (Lagoon Villa) is out of order from 7 to 13 days ahead and room 401 has an owner block 3–5 days ahead, so availability shows their effect. Layouts live in `database/seeders/DemoResorts.php`. The two tenants must never see each other's data.
