# Module Guide

How to create and work in a Resort365 module. The rules come from [ARCHITECTURE.md](ARCHITECTURE.md) §4.3 (modular architecture), §4.4 (layering) and §11 (project structure). Modules use [`nwidart/laravel-modules`](https://laravelmodules.com) v13.

## 1. Create a module

```sh
php artisan module:make Housekeeping
composer dump-autoload      # registers the module's composer.json via the merge plugin
```

This creates `Modules/Housekeeping/`, enables it in `modules_statuses.json`, and generates the structure below from the project stubs in `stubs/modules/`. It adds no sample controller, routes or views.

```
Modules/Housekeeping/
├── app/
│   ├── Actions/            one use case per class; extends App\Support\Actions\Action
│   ├── Contracts/          public interfaces other modules may call
│   ├── DTOs/               readonly data classes; extend App\Support\DTOs\Data
│   ├── Enums/              backed enums implementing HasLabelAndColor
│   ├── Events/             domain events; implement ShouldDispatchAfterCommit
│   ├── Exceptions/
│   ├── Http/Controllers/   thin: authorize, validate, delegate, respond
│   ├── Http/Requests/      FormRequests (all validation)
│   ├── Jobs/
│   ├── Listeners/          reactions to other modules' events
│   ├── Models/             private to this module
│   ├── Policies/
│   ├── Providers/          HousekeepingServiceProvider, EventServiceProvider, RouteServiceProvider
│   └── Services/           stateless, reusable domain logic
├── config/config.php
├── database/{factories,migrations,seeders}/
├── lang/en/
├── resources/views/
├── routes/web.php, api.php
├── tests/{Feature,Unit}/
├── composer.json           PSR-4: Modules\Housekeeping\ → app/
└── module.json             name, alias, priority, providers
```

The namespace drops `app/`: `Modules/Housekeeping/app/Actions/StartCleaning.php` is `Modules\Housekeeping\Actions\StartCleaning`.

## 2. Generate classes

Use the module generators. They write to the folders above, and the stubs produce code that passes Pint, Rector and Larastan as generated.

| Command | Creates |
|---|---|
| `module:make-action StartCleaning Housekeeping` | `app/Actions`, extends `Action` with a `handle()` method |
| `module:make-interface RoomStatusContract Housekeeping` | `app/Contracts` |
| `module:make-enum TaskStatus Housekeeping` | `app/Enums`, a string-backed enum with `label()` / `color()`; replace the example cases |
| `module:make-event CleaningCompleted Housekeeping` | `app/Events`, dispatched after commit |
| `module:make-model HousekeepingTask Housekeeping -mf` | model + migration + factory (always create the factory) |
| `module:make-request StoreTaskRequest Housekeeping` | `app/Http/Requests`, denies by default until you authorize |
| `module:make-controller TaskController Housekeeping --plain` | `app/Http/Controllers` |
| `module:make-service TaskScheduler Housekeeping` | `app/Services` |
| `module:make-listener … / module:make-job … / module:make-policy …` | the matching folder |
| `module:make-test TaskTest Housekeeping [--feature]` | a Pest `todo()` test in `tests/Unit` or `tests/Feature` |

There is no DTO generator. Create DTOs by hand:

```php
namespace Modules\Housekeeping\DTOs;

use App\Support\DTOs\Data;

final readonly class AssignTaskData extends Data
{
    public function __construct(
        public int $roomId,
        public int $attendantId,
        public ?string $notes = null,
    ) {}
}

// AssignTaskData::from($request->validated()) — keys must match the constructor parameters.
```

Run `composer fix` after generating anything, then the quality gate (§6).

## 3. Shared building blocks (`app/Support`)

| Class | Use |
|---|---|
| `App\Support\Actions\Action` | Base for actions. `StartCleaning::make()->handle(...)` resolves it from the container. `$this->transaction(fn () => ...)` wraps multi-row writes. |
| `App\Support\DTOs\Data` | Base for `readonly` DTOs: `from(array)` and `toArray()`. |
| `App\Support\Enums\HasLabelAndColor` | Interface for every status or type enum: `label()` (translated) and `color()` (a Bootstrap contextual colour for badges). |
| `App\Support\Enums\EnumHelpers` | Trait for those enums: `values()` (for `in:` rules) and `options()` (value ⇒ label, for selects). |

## 4. Boundaries between modules

- A module owns its tables. No other module writes to them.
- Another module may use only this module's **Contracts, DTOs, Enums and Events**. Models, Actions, Services, Controllers and everything else stay private.
- Queries or commands across modules go through a **Contract** bound in the owning module's service provider. Side effects go through **Events** that the other module listens to.
- Dependencies point one way, following the graph in ARCHITECTURE §4.3. A downstream module reacts to an upstream module's events. An upstream module never calls a downstream one, and there are no cycles.

## 5. Registering things

- **Service provider** (`app/Providers/<Module>ServiceProvider.php`): extends `Nwidart\Modules\Support\ModuleServiceProvider`, which loads the module's config (`config('housekeeping.…')`), views (`view('housekeeping::…')`) and migrations. Translations in `lang/` load into the **shared** namespace, not a module namespace. Use JSON-style strings (`__('Room cleaned')`), or name PHP translation files after the module (`lang/en/housekeeping.php` → `__('housekeeping.key')`) to avoid collisions. Bind contracts here. Later steps also register permissions, menu items, settings and schedules here.
- **Routes:** `routes/web.php` is loaded with the `web` middleware, under the prefix `/housekeeping`, with route names `housekeeping.resource.action`. `routes/api.php` is loaded under `/api/v1/housekeeping`, with names `api.v1.housekeeping.…`. Every route needs authentication and permission middleware (Standard Step Rule R8).
- **Enable / disable:** `php artisan module:disable Housekeeping` / `module:enable`. This state is stored in `modules_statuses.json` (committed). Per-tenant module entitlements come later with the Platform module.

## 6. Tests and quality gate

- Module tests live in `Modules/<Module>/tests/{Feature,Unit}` and run with the rest of the suite. Feature tests automatically extend `Tests\TestCase`.
- The **architecture tests** in `tests/Architecture` discover every module automatically and enforce these rules:
  - no `dd`, `ddd`, `dump`, `ray` or `var_dump`;
  - controllers do not use `DB` (facade, alias or connection);
  - modules use each other only through Contracts, DTOs, Enums and Events;
  - Actions extend `Action`, DTOs are readonly and extend `Data`, Enums implement `HasLabelAndColor`, and Events implement `ShouldDispatchAfterCommit`.
- Before committing: `composer fix`, then `composer lint`, `composer analyse` and `composer test`.

## 7. Delete a module

```sh
php artisan module:delete Housekeeping --force
composer dump-autoload
```
