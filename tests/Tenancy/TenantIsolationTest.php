<?php

/*
|--------------------------------------------------------------------------
| Tenant isolation harness (ARCHITECTURE §4.2, §13)
|--------------------------------------------------------------------------
|
| Runs for every tenant-owned model (Tests\Support\TenantModels discovers
| models using BelongsToTenant). Proves tenant A cannot list, find, bind,
| update, delete, create for, move to, or reference tenant B's records.
| A model needs a factory to be tested; the harness fails without one.
|
*/
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantContextMissing;
use App\Support\Tenancy\TenantMismatch;
use App\Support\Tenancy\TenantRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\Support\TenantModels;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

dataset('tenant models', TenantModels::all());

/**
 * @param  class-string<Model>  $model
 * @return Factory<Model>
 */
function factoryFor(string $model): Factory
{
    try {
        $factory = new ReflectionMethod($model, 'factory')->invoke(null);
    } catch (ReflectionException) {
        $factory = null;
    }

    if (! $factory instanceof Factory) {
        throw new RuntimeException("{$model} needs a factory (HasFactory) for the tenant-isolation harness.");
    }

    return $factory;
}

/**
 * @param  class-string<Model>  $model
 * @return array{Tenant, Tenant, Model, Model} [tenant A, tenant B, a record of A, a record of B]
 */
function twoTenantsWith(string $model): array
{
    [$a, $b] = [Tenant::factory()->create(['slug' => 'tenant-a']), Tenant::factory()->create(['slug' => 'tenant-b'])];
    $context = app(TenantContext::class);

    $recordA = $context->run($a, fn () => factoryFor($model)->create());
    $recordB = $context->run($b, fn () => factoryFor($model)->create());

    return [$a, $b, $recordA, $recordB];
}

it('lists only the current tenant\'s records', function (string $model): void {
    [$a, $b] = twoTenantsWith($model);
    app(TenantContext::class)->run($b, fn () => factoryFor($model)->count(2)->create());

    app(TenantContext::class)->run($a, function () use ($model, $a): void {
        $records = $model::query()->get();

        expect($records)->toHaveCount(1)
            ->and($records->pluck('tenant_id')->unique()->all())->toBe([$a->id]);
    });
})->with('tenant models');

it('cannot find another tenant\'s record', function (string $model): void {
    [$a, , $recordA, $recordB] = twoTenantsWith($model);

    app(TenantContext::class)->run($a, function () use ($model, $recordA, $recordB): void {
        expect($model::query()->find($recordB->getKey()))->toBeNull()
            ->and($model::query()->find($recordA->getKey()))->not->toBeNull()
            ->and(fn () => $model::query()->findOrFail($recordB->getKey()))->toThrow(ModelNotFoundException::class);
    });
})->with('tenant models');

it('returns 404 when a route binds another tenant\'s record', function (string $model): void {
    [$a, , $recordA, $recordB] = twoTenantsWith($model);
    app(TenantContext::class)->forget();

    Route::domain('{tenant}.'.config('tenancy.central_domain'))
        ->middleware(['web', 'tenant'])
        ->get('/_isolation/{record}', fn (Model $record) => $record->getKey());
    Route::model('record', $model);

    get($a->url('/_isolation/'.$recordA->getKey()))->assertOk()->assertSee((string) $recordA->getKey());
    get($a->url('/_isolation/'.$recordB->getKey()))->assertNotFound();
})->with('tenant models');

it('cannot update or delete another tenant\'s records through a query', function (string $model): void {
    [$a, $b, , $recordB] = twoTenantsWith($model);
    $instance = new $model;

    app(TenantContext::class)->run($a, function () use ($model, $instance, $recordB): void {
        expect($model::query()->whereKey($recordB->getKey())->update([$instance->getUpdatedAtColumn() => now()->addYear()]))->toBe(0)
            ->and($model::query()->whereKey($recordB->getKey())->delete())->toBe(0);
    });

    app(TenantContext::class)->run($b, fn () => expect($model::query()->find($recordB->getKey()))->not->toBeNull());
})->with('tenant models');

it('refuses to save or delete an instance of another tenant\'s record', function (string $model): void {
    [$a, , , $recordB] = twoTenantsWith($model);

    app(TenantContext::class)->run($a, function () use ($recordB): void {
        // Force a real change so save() issues an UPDATE.
        $recordB->setAttribute($recordB->getUpdatedAtColumn(), now()->addYear());

        expect(fn () => $recordB->save())->toThrow(TenantMismatch::class)
            ->and(fn () => $recordB->delete())->toThrow(TenantMismatch::class);
    });
})->with('tenant models');

it('refuses to create a record for another tenant', function (string $model): void {
    [$a, $b] = twoTenantsWith($model);

    app(TenantContext::class)->run($a, function () use ($model, $b): void {
        expect(fn () => factoryFor($model)->create(['tenant_id' => $b->id]))->toThrow(TenantMismatch::class);
    });
})->with('tenant models');

it('refuses to move a record to another tenant', function (string $model): void {
    [$a, $b, $recordA] = twoTenantsWith($model);

    app(TenantContext::class)->run($a, function () use ($recordA, $b): void {
        $recordA->setAttribute('tenant_id', $b->id);

        expect(fn () => $recordA->save())->toThrow(TenantMismatch::class);
    });
})->with('tenant models');

it('cannot reference another tenant\'s record in validation', function (string $model): void {
    [$a, , $recordA, $recordB] = twoTenantsWith($model);
    $table = (new $model)->getTable();

    app(TenantContext::class)->run($a, function () use ($table, $recordA, $recordB): void {
        $exists = fn (mixed $id): bool => Validator::make(['id' => $id], ['id' => TenantRule::exists($table)])->passes();
        $unique = fn (mixed $id): bool => Validator::make(['id' => $id], ['id' => TenantRule::unique($table, 'id')])->passes();

        expect($exists($recordA->getKey()))->toBeTrue()
            ->and($exists($recordB->getKey()))->toBeFalse()
            ->and($unique($recordA->getKey()))->toBeFalse()
            ->and($unique($recordB->getKey()))->toBeTrue();
    });
})->with('tenant models');

it('fails closed without a current tenant', function (string $model): void {
    twoTenantsWith($model);
    app(TenantContext::class)->forget();

    expect(fn () => $model::query()->get())->toThrow(TenantContextMissing::class)
        ->and(fn () => factoryFor($model)->create())->toThrow(TenantContextMissing::class);
})->with('tenant models');
