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
| `php artisan migrate:fresh --seed` | Rebuild the local database with demo data |
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

## UI (ARCHITECTURE §10)

- **Stack:** AdminLTE 4 and Bootstrap 5.3, compiled from SCSS (`resources/scss/app.scss`; tokens in `_variables.scss`), Bootstrap Icons, Alpine.js, Tom Select, flatpickr, DataTables (Bootstrap 5) and SweetAlert2. Server-side tables use `yajra/laravel-datatables-oracle`.
- **Layouts** live in `resources/views/layouts` and are used as components: `<x-layouts::app :title :breadcrumbs>` (with an optional `actions` or `header` slot), `<x-layouts::guest>` (sign-in pages) and `<x-layouts::print>` (A4 documents, `resources/scss/print.scss`). The POS and KDS layouts come in Phase 3.
- **Components** live in `resources/views/components`: `card`, `page-header`, `form.input`, `form.select` (Tom Select), `form.date` (flatpickr, submits `Y-m-d`), `form.money` (decimal string plus currency, never a float), `status-badge` (any `HasLabelAndColor` enum), `datatable`, `modal`, `confirm-delete`, `stat-box`, `empty-state`, `flash-messages`, `attachments`, `approval-panel` and `audit-trail`. Form components handle the label, required marker, help text, `old()` input and validation errors, so always use them.
- **JS behaviour comes from data attributes:**
  - `data-tom-select` and `data-flatpickr` (JSON options) set up selects and date pickers.
  - `data-datatable` (JSON config) sets up server-side tables.
  - `data-confirm="Title"` on a form or button opens the global confirm dialog; `data-confirm-text`, `data-confirm-variant` and the other `data-confirm-*` attributes customise it.
  - After inserting HTML, call `window.initUi(element)`.
- **Colour mode:** light, dark or auto through AdminLTE's colour mode (`localStorage` key `lte-theme`, applied before first paint). Enum `color()` values are `primary`, `secondary`, `success`, `danger`, `warning` or `info`, because `light` and `dark` are unreadable in one of the modes.
- **Sidebar menu:** `App\Support\Ui\SidebarMenu` is a placeholder until the menu registry (Step 0.6).
- **`/ui-kit`** (local only; 404 elsewhere) shows every layout piece and component. Add new shared components to it, and check it in light and dark mode and at tablet width.

## Modules

Modules live in `Modules/<Name>/` (`nwidart/laravel-modules`, autoloaded through each module's `composer.json`). Only **Core** exists so far; each module is created by the plan step that first needs it. Dependencies point one way only (ARCHITECTURE §4.3): a downstream module reacts to an upstream module's events, and an upstream module never calls a downstream one.

| Module | Purpose | Depends on |
|---|---|---|
| Core | Tenancy context, settings, document numbering, approval workflow, notifications, tax engine, audit log, attachments, reference data, menu and permission registry | — |
| Platform | SaaS: tenant lifecycle, plans and module entitlements, subscriptions, super-admin console | Core |
| IAM | Users, invitations, login, 2FA, roles and permissions, property access | Core |
| Property | Properties, cottage types, cottages, room types, rooms, amenities, departments, business date | Core |
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

- `app/`: application shell only. Business code goes in modules. `app/Support/` holds the shared base classes: `Actions\Action`, `DTOs\Data`, and `Enums\HasLabelAndColor` + `EnumHelpers`. Tenancy and money base classes are added in later steps.
- `Modules/<Name>/`: a self-contained module (see ARCHITECTURE §11 for the internal layout).
- `database/`: central (non-module) migrations and seeders.
- `tests/`: cross-module tests, `tests/Architecture` (auto-discovers every module) and `tests/Fixtures`. Module tests live in `Modules/<Name>/tests`.
- `stubs/modules/`: project stubs for the module generators. Edit these, not vendor, to change what `module:make*` produces.
- `docs/adr/`: short Architecture Decision Records for changes of direction.

## Demo data

| Tenant | Subdomain (local) | Properties |
|---|---|---|
| Sunrise Resorts Ltd | `sunrise.resort365.test` | Sunrise Cox's Bazar (8 cottages), Sunrise Sylhet (4 cottages) |
| Green Valley Resort | `greenvalley.resort365.test` | Green Valley (5 cottages) |

Each tenant gets one demo user per default role (e.g. `frontdesk@sunrise.test` / `password`). The two tenants must never see each other's data.
