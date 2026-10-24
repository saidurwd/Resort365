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

- **Laravel Herd** serves the app at `http://resort365.test` (the central domain). Tenants will use subdomains such as `rodela.resort365.test` (Step 0.4).
- **Database:** locally the Homebrew **MariaDB** service on port 3306, with user `resort365` / `secret` and the databases `resort365` (app) and `resort365_testing` (tests). CI and production run **MySQL 8.4**, so keep `DB_CONNECTION=mysql` and write SQL that works on both. CI is the authority on MySQL compatibility.
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
| `php artisan migrate:fresh --seed` | Rebuild the local database with reference data and demo data (tenants `rodela`, `greenvalley`); also flushes the cache |
| `php artisan tenant:create <slug> "<Name>"` | Create a tenant; open `http://<slug>.resort365.test` |
| `php artisan platform:create-admin <email> "<Name>"` | Create a platform super admin (prompts for the password) |
| `php artisan backup:run` / `php artisan backup:restore-drill` | Back up the database (and local attachments) / restore the newest backup into a scratch database and check it (Step 7.5, docs/OPERATIONS.md) |
| `php artisan platform:purge-tenants [--dry-run]` | Delete the data of tenants cancelled more than 30 days ago (daily) |
| `k6 run tests/Load/run.js` | The 50-user load test (after `php tests/Load/prepare.php`; see tests/Load/README.md) |
| `php artisan platform:billing` | Daily billing run: end trials, renew subscriptions with their invoices, close cancelled tenants, suspend overdue ones (scheduled 00:30) |
| `php artisan permissions:sync [--prune]` | Store registered permissions and update every tenant's default roles |
| `php artisan module:make <Name>` then `composer dump-autoload` | Create a module with the §11 structure |
| `php artisan module:make-action <Class> <Module>` (also `-enum`, `-event`, `-model … -mf`, `-request`, `-interface`, …) | Generate a class inside a module; see MODULE_GUIDE §2 |
| `php artisan test --testsuite=Architecture` | Run only the architecture tests |
| `composer test:browser` | Reseed `resort365_dusk` and run the Dusk browser tests (`tests/Browser`) against Herd (Step 7.6) |

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
| R6 | **Modules:** other modules are used only through their Contracts, DTOs, Enums, Events and Exceptions (see ARCHITECTURE §4.3). |
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
6. **Module boundaries:** a module may use another module's **Contracts**, **Enums**, **DTOs**, **Events** and **Exceptions** (those its contracts throw) only, never its Models directly for writes. This is enforced with Pest architecture tests.
7. **Enums:** every status or type is a PHP backed enum with `label()` and `color()` for badges.
8. **Money:** never use floats. Use the `Money` cast and `brick/money` for calculation; round only at defined points.
9. **Dates:** stay dates are `Carbon` date-only; timestamps are stored in UTC and displayed in the property timezone: show a stored timestamp with `->inPropertyTime()` (`{{ $order->opened_at->inPropertyTime()->format('H:i') }}`), a Carbon macro backed by `App\Support\Tenancy\DisplayTimezone` (the current property, or the POS terminal's / kitchen display's property, else UTC). Never change the application's timezone (that would shift stored values) and never format a stored timestamp without it.
10. **Authorization:** every controller action is authorized through a Policy or permission middleware.
11. **Naming:** tables are plural snake_case; models singular; routes are kebab-case and named `module.resource.action`; permissions are `module.resource.action`.
12. **UI:** use the shared Blade components; no inline styles; every string goes through `__()`.
13. **Tests:** every Action has a feature test; every tenant model has an isolation test; money and booking logic have unit tests.
14. **Quality gates:** Pint, Larastan and Pest must pass before a commit.
15. **POS screens:** Blade renders the page shell; Alpine.js holds the order state and calls JSON endpoints (`/pos/api/...`) that reuse the same Actions as the admin screens. Business rules are never duplicated in JavaScript; the server recalculates every total.

Inside a module, keep to the layering in ARCHITECTURE §4.4. Controllers are thin (authorize, validate, delegate, respond). Actions own one use case and its transaction. Services hold stateless domain logic. DTOs are `readonly` classes.

## Tenancy (ARCHITECTURE §4.2)

- **Tenant** = a customer company: `App\Models\Tenant` (central table `tenants`), served from `{slug}.{TENANCY_CENTRAL_DOMAIN}`, e.g. `rodela.resort365.test`. Statuses: `trial` and `active` can use the app. `suspended` shows the suspended page (403). `cancelled` and unknown subdomains get 404.
- **Routes:**
  - `routes/web.php` holds the **central** domain (welcome page, `/ui-kit`).
  - `routes/tenant.php` and every module's routes are **tenant** routes, with the `web` and `tenant` middleware on `{tenant}.` domains.
  - `IdentifyTenant` sets the tenant **before** route-model binding, hides the `{tenant}` parameter from controllers, and makes `route()` fill it in.
- **`TenantContext`** (scoped per request and per job) holds the current tenant: `id()`, `tenant()`, `tenantOrFail()`, and `run($tenant, fn)` for console commands, seeders and scripts. Setting it also sets the URL default for `{tenant}`, so `route()` builds tenant links in jobs and commands.
- **Tenant-owned models** use `App\Support\Tenancy\BelongsToTenant`, which **fails closed**: using the model with no current tenant throws `TenantContextMissing`. It fills `tenant_id` on create, and refuses to change `tenant_id` or to write another tenant's record (`TenantMismatch`). An architecture test requires the trait on every module model.
- **Properties (resorts):** `Modules\Property\Models\Property`. A user works in the properties assigned in `property_user` (screen: `/property/access`), or in every property with `property.property.access-all` (Tenant Owner, Auditor).
  - **`PropertyContext`** (app shell, per request): set by `SetCurrentProperty`, which also picks the **current property** (session; navbar switcher posts to `/property/switch/{id}`). The navbar also shows the current property's **business date**. `unrestricted(fn)` runs a tenant-wide system reaction (e.g. moving bookings after a guest merge) across every property.
  - **Property-level models** use `App\Support\Tenancy\BelongsToProperty` together with `BelongsToTenant`. Queries only return the user's properties, `property_id` comes from the current property on create, and writes to other properties are refused (`PropertyAccessDenied`). Screens about "this property" add `where('property_id', $context->currentId())`. Outside a user request (console, jobs), only the tenant scope applies.
  - **Property harness:** `tests/Tenancy/PropertyIsolationTest.php` runs every `BelongsToProperty` model automatically.
  - **Other modules** read property details through `Modules\Property\Contracts\PropertyDirectory`, never the model; a new property fires `App\Support\Tenancy\Events\PropertyCreated` (ids only).
- **Module notes** (Property, Guest, Rates, Reservation, Front office, Housekeeping, Restaurant, Accounting, Inventory, Procurement, HR, Payroll, Reports, Platform, Billing, REST API, Website): [docs/claude/modules.md](docs/claude/modules.md). Read the module's part before working in it.
- **Validation:** `TenantRule::exists('table')` and `TenantRule::unique('table', 'column')`, never plain `Rule::exists` or `Rule::unique`.
- **Queued jobs** that touch tenant data implement `TenantAware` and use `InteractsWithTenant`, which restores the dispatching tenant. Dispatch as a statement inside `TenantContext::run()`; a returned `PendingDispatch` queues the job too late.
- **Cache and files:** `TenantCache` (keys `t:{id}:…`) and `TenantStorage` (paths `tenants/{id}/…`).
- **Isolation tests:** `tests/Tenancy/TenantIsolationTest.php` automatically runs every model that uses `BelongsToTenant` (found in `app/Models` and `Modules/*/app/Models`) through the list, find, route-binding, update, delete, create, move, reference and fail-closed checks. A new tenant model only needs a factory. Test-only tables live in `tests/Fixtures/Tenancy/migrations`.
- **Development:** `php artisan tenant:create <slug> "<Name>" [--email=] [--status=trial|active|suspended|cancelled]`. `php artisan migrate:fresh --seed` creates the demo tenants `rodela` and `greenvalley`.

## Core services (ARCHITECTURE §5.1, §9.2)

Other modules use these through Core's **contracts** (`Modules\Core\Contracts\*`), never Core's models.

- **Settings** (`Settings`): register a `SettingDefinition` (`module.name`, a `SettingType`, a default, a scope of tenant or property, a group) in your provider. Read it with `Settings::get('core.night_audit_time', $propertyId)`: the property value wins, then the tenant value, then the default, cast to the type. Values are cached per tenant and edited at `/core/settings`, company-wide or per property (`?property=`).
- **Document numbers** (`DocumentNumbers`): register a `DocumentType` (key, prefix, format such as `{PREFIX}-{YYYY}-{SEQ:5}`, yearly reset or never), then call `next('reservation', $propertyId)` **inside the transaction that saves the document**. Numbers are taken under a row lock and are gap-free. New tenants get every sequence up front (`CreateDocumentSequences`). If you wrap `next()` in your own transaction, pass retry attempts, because a lock conflict rolls back the whole transaction. Sequences are set up at `/core/document-sequences`.
- **Audit log:** add `App\Support\Audit\RecordsActivity` to every business model (R4). It records create, update and delete with old and new values and who made the change, and never the hidden attributes, keys or timestamps. For pivot or other changes model events don't see, log explicitly with `activity()->performedOn($model)->withProperties(['old' => …, 'attributes' => …])->log('…')`. Show history with `<x-audit-trail :entries="app(AuditTrail::class)->for($model)" />`. The whole tenant's log is at `/core/audit-log` (`core.audit.view`).
- **Attachments:** a model implements `Spatie\MediaLibrary\HasMedia`, uses `App\Support\Attachments\HasAttachments`, has a morph-map alias and a policy (`view` to download, or `viewAttachments` when the policy has it; `update` to upload or delete). Render `<x-attachments :subject="$model" />`. For a **photo gallery** use `HasPhotos` instead (adds the `photos` collection with a `thumb` conversion) and render `<x-photo-gallery :subject="$model" />`; uploads send `collection=photos`. Files live on the private `attachments` disk under `tenants/{id}/media/…` and are served only through Core's authorized routes. `ATTACHMENTS_DISK=s3` switches to S3-compatible storage.
- **Reference data:** central `countries`, `currencies` and `timezones` tables (ISO codes, `ReferenceDataSeeder`). Exchange rates are per tenant, through `ExchangeRates::rate('USD', 'BDT', $date)`, as decimal strings.
- **Notifications:** notifiable models return `App\Models\DatabaseNotification` from `notifications()`, so in-app notifications carry `tenant_id`. The navbar bell shows them, with all of them at `/core/notifications`. Use `database` channel data keys `title`, `body`, `icon` and `url`. For wording, register a `NotificationTemplateDefinition` (with a label and description for the editor) and render it with `NotificationTemplates::render(key, channel, data)`. Tenants rewrite templates at Setup → Email templates (`/core/notification-templates`) and can reset them.
- **Taxes** (`TaxEngine`): taxes and tax categories per tenant (Setup → Taxes). Other modules store a tax category id and call `TaxEngine::calculate(amount, categoryId, inclusive, quantity)`, which returns a `TaxBreakdown` of decimal strings. The math lives in `TaxCalculator` (brick/math `BigDecimal`; each tax rounded half-up, inclusive net = gross − taxes).
- **Approvals** (Step 5.1, sidebar **Approvals**, Setup → **Approval workflows**): `ApprovalEngine` (Core contract) takes a document type, amount, reference, requester and a JSON payload: `submit()` returns null when the tenant's workflow needs no approval for the amount (the module goes ahead at once), else a request waits for the approvers of each applicable step (steps are a label, a **permission** and a minimum amount; the applicable ones are frozen on the request). A module registers its document type, default steps and an `ApprovalHandler` with `ApprovalRegistry` in its provider (`approved()` runs in the last approval's transaction, throw `ApprovalExecutionFailed` to refuse; `rejected()`), new tenants get the defaults (`SeedApprovalWorkflows`; `php artisan approvals:seed-defaults`). The requester never approves their own request; delegation (`SaveApprovalDelegation`) lets a colleague use your permission between two dates. Core reaches people only through `ApprovalParticipants` (IAM implements it). Show a request with `<x-approval-panel :steps="app(ApprovalPanelData::class)->steps($request)">`. Refunds use it: `RequestRefund` (replaces calling `RefundPayment` from screens) pays at once below the `refund` workflow's amount (default 5,000, General Manager `billing.refund.approve`) and otherwise waits; `RefundApprovalHandler` pays on approval. Permissions `core.approval.view` (all roles), `core.approval.manage`.
- **Mandatory 2FA:** the `iam.require_two_factor` setting (Settings → Security) sends Tenant Owners and General Managers without 2FA to their profile.
- **Local MariaDB note:** MariaDB has snapshot isolation on, so a locking read of a row changed after the transaction's first plain read fails with error 1020. Laravel retries it as a concurrency error. MySQL 8.4 (CI and production) behaves normally.

## Roles, permissions and the sidebar (ARCHITECTURE §3, §10.2)

- **Permissions** are named `module.resource.action`. Each module registers its permissions in its service provider through `App\Support\Authorization\PermissionRegistry`, as `PermissionDefinition(name, label, defaultRoles)`. Tenant Owner gets every permission and Auditor every `*.view`, automatically.
- **After adding or changing permissions**, run `php artisan permissions:sync [--prune]`. It stores them and updates every tenant's **default roles**: the 18 roles from §3.2, which are read-only system roles. Tenants build **custom roles** at `/iam/roles`, and new tenants get the default roles automatically (`TenantCreated`).
- **Roles belong to a tenant:** spatie/laravel-permission with teams = tenants (`TenantTeamResolver`), and a per-tenant permission cache (`UseTenantPermissionCache`).
- **Enforce permissions twice:** with `can:<permission>` route middleware and with a Policy in the controller (R8). Default roles get their permissions from the registry, never by hand.
- **Sidebar:** each module registers groups and items in `App\Support\Menu\MenuRegistry` (label, icon, route, order, permission, module). The sidebar shows only what the user may see in enabled modules. Shared groups such as `setup` are created with `$menu->group()`. `active` takes one route-name pattern or a list of them.
- **Modules per tenant:** the `tenant_modules` table (no row means enabled; `ChangeTenantPlan` writes the rows from a tenant's plan, Step 7.3). Module routes get `module:<alias>` middleware from the route-provider stub, so a disabled module returns 403 and its menu disappears. `core`, `iam`, `platform` and `reports` are always on (Reports owns the home page).
- **Tests:** `withDefaultRoles($tenant)`, `tenantUserAs($tenant, DefaultRole::X)` and `roleId(...)` in `tests/Pest.php`.

## UI (ARCHITECTURE §10)

- **Stack:** AdminLTE 4 and Bootstrap 5.3, compiled from SCSS (`resources/scss/app.scss`; tokens in `_variables.scss`), Bootstrap Icons, Alpine.js, Tom Select, flatpickr, DataTables (Bootstrap 5) and SweetAlert2. Server-side tables use `yajra/laravel-datatables-oracle`.
- **Layouts** live in `resources/views/layouts` and are used as components: `<x-layouts::app :title :subtitle :breadcrumbs>` (with an optional `actions` or `header` slot; property screens pass the property name as `subtitle`), `<x-layouts::guest>` (sign-in pages) and `<x-layouts::print>` (A4 documents, `resources/scss/print.scss`). `<x-layouts::pos>` (Step 3.3) and `<x-layouts::kds>` (Step 3.5) are the full-screen restaurant layouts.
- **Components** live in `resources/views/components`: `card`, `page-header`, `form.input`, `form.select` (Tom Select), `form.date` (flatpickr, submits `Y-m-d`), `form.money` (decimal string plus currency, never a float), `status-badge` (any `HasLabelAndColor` enum), `datatable`, `modal`, `confirm-delete`, `stat-box`, `empty-state`, `flash-messages`, `attachments`, `photo-gallery`, `approval-panel` and `audit-trail`. Form components handle the label, required marker, help text, `old()` input and validation errors, so always use them.
- **JS behaviour comes from data attributes:**
  - `data-tom-select` and `data-flatpickr` (JSON options) set up selects and date pickers. Tom Select option `{"remote": url}` searches the server as the user types (`url?q=term` returns `[{value, text}]`).
  - `data-datatable` (JSON config) sets up server-side tables.
  - `data-confirm="Title"` on a form or button opens the global confirm dialog; `data-confirm-text`, `data-confirm-variant` and the other `data-confirm-*` attributes customise it.
  - `data-modal-action="url"` on a button that opens a modal sets the action of the modal's form (one modal shared by a list's rows).
  - `data-hash-tabs` on a `.nav-tabs` list makes its tabs follow the URL hash: buttons carry `data-bs-target="#tab-x" data-tab-hash="x"`, so a redirect to `page#x` opens that tab.
  - After inserting HTML, call `window.initUi(element)`.
- **Colour mode:** light, dark or auto through AdminLTE's colour mode (`localStorage` key `lte-theme`, applied before first paint). Enum `color()` values are `primary`, `secondary`, `success`, `danger`, `warning` or `info`, because `light` and `dark` are unreadable in one of the modes.
- **`/ui-kit`** (local only; 404 elsewhere) shows every layout piece and component. Add new shared components to it, and check it in light and dark mode and at tablet width.

## Modules

Modules live in `Modules/<Name>/` (`nwidart/laravel-modules`, autoloaded through each module's `composer.json`). So far: **Core**, **IAM**, **Platform**, **Property**, **Guest**, **Rates**, **Reservation**, **Billing**, **FrontOffice**, **Housekeeping**, **Restaurant**, **Accounting**, **Inventory**, **Procurement**, **HR** and **Payroll**. Each module is created by the plan step that first needs it. Dependencies point one way only (ARCHITECTURE §4.3): a downstream module reacts to an upstream module's events, and an upstream module never calls a downstream one.

| Module | Purpose | Depends on |
|---|---|---|
| Core | Tenancy context, settings, document numbering, approval workflow, notifications, tax engine, audit log, attachments, reference data, menu and permission registry | — |
| Platform | SaaS: sign-up, tenant lifecycle, plans and module entitlements, limits, subscriptions and platform invoices, super-admin console, impersonation, announcements | Core; IAM `UserDirectory`, `OwnerAccounts` |
| IAM | Users, invitations, login, 2FA, roles and permissions, property access | Core |
| Property | Properties, property access (`property_user`), switcher and business date, cottage types, cottages, room types, rooms, amenities, departments | Core, IAM (`UserDirectory` contract) |
| Guest | Guest profiles (CRM), companies, travel agents | Core |
| Rates | Seasons, rate plans, rates, deposit and cancellation policies, promotions | Property |
| Reservation | Booking engine: availability, pricing, reservations, inventory locks, holds | Property, Rates, Guest |
| FrontOffice | Front desk, check-in and check-out, stay changes, night audit, daily statistics and the flash report | Reservation |
| Billing | Folios, charges, payments, invoices, refunds, cashier shifts; `FolioPostingContract` | Reservation |
| Restaurant | F&B outlets, menus, tables, POS orders, KOT and kitchen display, charge to room, meal-plan redemption | Property, Billing (Inventory optional) |
| Housekeeping | Room status board, cleaning tasks, out-of-order blocks, work orders, preventive maintenance, lost & found | Property, Reservation, Inventory (contracts); FrontOffice and Reservation events |
| Inventory | Items, units, stores, stock ledger, transfers, requisitions, stock counts, recipes | Core, Property (`DepartmentDirectory`) |
| Procurement | Vendors, purchase requisitions, RFQs, purchase orders, goods receipt, vendor bills and payments | Inventory (contracts), Property (`DepartmentDirectory`), Core |
| Accounting | Double-entry GL, chart of accounts, periods, account mapping, automatic posting from other modules' events, vouchers, statements | Core; Billing, Reservation, Restaurant, Inventory and Procurement contracts and events (read only) |
| HR | Designations, grades, employees and their lifecycle, shifts, attendance, leave | Core, Property (`DepartmentDirectory`, `PropertyDirectory`), IAM (`UserDirectory`) |
| Payroll | Salary components and structures, effective-dated salaries, income tax tables, payroll runs, payslips, loans, service charge distribution (Bangladesh defaults) | HR (`EmployeeDirectory`), Property, Core |
| Reports | Management dashboard, KPI and rooms and guest reports, the report framework and its Excel/PDF exports | Core; FrontOffice, Reservation, Guest, Accounting, Property and IAM contracts (read only) |

## Project structure

- `app/`: application shell only. Business code goes in modules. `app/Support/` holds the shared foundations: `Actions\Action`, `DTOs\Data`, `Enums\HasLabelAndColor` + `EnumHelpers`, `Tenancy\*` (tenant and property context), `Authorization\*`, `Menu\*`, `Audit\RecordsActivity` and `Attachments\*`. Money helpers come with the first money feature.
- `Modules/<Name>/`: a self-contained module (see ARCHITECTURE §11 for the internal layout).
- `database/`: central (non-module) migrations and seeders.
- `tests/`: cross-module tests, `tests/Architecture` (auto-discovers every module) and `tests/Fixtures`. Module tests live in `Modules/<Name>/tests`.
- `stubs/modules/`: project stubs for the module generators. Edit these, not vendor, to change what `module:make*` produces.
- `docs/adr/`: short Architecture Decision Records for changes of direction.

## Where to find more

- [docs/claude/modules.md](docs/claude/modules.md): what each module has built (screens, Actions, contracts, settings, permissions, demo).
- [docs/claude/auth-and-operations.md](docs/claude/auth-and-operations.md): users and sign-in (middleware order, 2FA, platform admins, demo logins), hardening (CSP nonces, lazy loading, cache rules, rate limiters), monitoring, backups, and the local Redis, Reverb and Dusk setup.
- [docs/claude/demo-data.md](docs/claude/demo-data.md): the demo tenants and what each demo seeder sets up.
- Short reminders that apply everywhere: give any inline `<script>` the CSP nonce (`Vite::cspNonce()`) and write no inline event handlers; eager-load relations (lazy loading throws outside production); cache only plain values; demo logins are `<mailbox>@rodelaresort.com` / `password`.
