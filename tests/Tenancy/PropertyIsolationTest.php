<?php

/*
|--------------------------------------------------------------------------
| Property access harness (ARCHITECTURE §4.2 "Property", Step 0.8)
|--------------------------------------------------------------------------
|
| Runs for every property-level model (BelongsToProperty, discovered automatically).
| A user who may only work in property A cannot list, find, bind, update, delete,
| create for, or move records to property B. Unrestricted contexts see both.
|
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\PropertyAccessDenied;
use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Property\Models\Property;
use Tests\Support\TenantModels;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

dataset('property models', TenantModels::propertyLevel());

/**
 * @param  class-string<Model>  $model
 * @return Factory<Model>
 */
function propertyFactoryFor(string $model): Factory
{
    try {
        $factory = new ReflectionMethod($model, 'factory')->invoke(null);
    } catch (ReflectionException) {
        $factory = null;
    }

    if (! $factory instanceof Factory) {
        throw new RuntimeException("{$model} needs a factory for the property harness.");
    }

    return $factory;
}

/**
 * A tenant with properties A and B, one record in each, run as a user restricted to A.
 *
 * @param  class-string<Model>  $model
 * @return array{Tenant, Property, Property, Model, Model}
 */
function twoPropertiesWith(string $model): array
{
    $tenant = Tenant::factory()->create(['slug' => 'sunrise']);
    app(TenantContext::class)->set($tenant);

    [$a, $b] = [Property::factory()->create(['name' => 'Property A']), Property::factory()->create(['name' => 'Property B'])];
    $recordA = propertyFactoryFor($model)->create(['property_id' => $a->id]);
    $recordB = propertyFactoryFor($model)->create(['property_id' => $b->id]);

    app(PropertyContext::class)->restrictTo([$a->id => $a->name], $a->id);

    return [$tenant, $a, $b, $recordA, $recordB];
}

it('lists only records of the user\'s properties', function (string $model): void {
    [, $a] = twoPropertiesWith($model);

    expect($model::query()->pluck('property_id')->unique()->all())->toBe([$a->id]);

    app(PropertyContext::class)->clear();
    expect($model::query()->count())->toBe(2);
})->with('property models');

it('cannot find a record of another property', function (string $model): void {
    [, , , $recordA, $recordB] = twoPropertiesWith($model);

    expect($model::query()->find($recordB->getKey()))->toBeNull()
        ->and($model::query()->find($recordA->getKey()))->not->toBeNull();
})->with('property models');

it('returns 404 when a route binds a record of another property', function (string $model): void {
    [$tenant, $a, , $recordA, $recordB] = twoPropertiesWith($model);
    withDefaultRoles($tenant);
    $user = tenantUserAs($tenant, DefaultRole::FrontDeskAgent);
    DB::table('property_user')->insert(['tenant_id' => $tenant->id, 'property_id' => $a->id, 'user_id' => $user->id]);
    app(PropertyContext::class)->clear();
    app(TenantContext::class)->forget();

    Route::domain('{tenant}.'.config('tenancy.central_domain'))->middleware(['web', 'tenant', 'auth'])
        ->get('/_property-probe/{record}', fn (Model $record) => $record->getKey());
    Route::model('record', $model);
    actingAs($user);

    get(tenantUrl('sunrise', '/_property-probe/'.$recordA->getKey()))->assertOk();
    get(tenantUrl('sunrise', '/_property-probe/'.$recordB->getKey()))->assertNotFound();
})->with('property models');

it('cannot update or delete another property\'s records through a query', function (string $model): void {
    [, , , , $recordB] = twoPropertiesWith($model);

    expect($model::query()->whereKey($recordB->getKey())->update(['updated_at' => now()->addYear()]))->toBe(0)
        ->and($model::query()->whereKey($recordB->getKey())->delete())->toBe(0);
})->with('property models');

it('refuses to save or delete an instance of another property\'s record', function (string $model): void {
    [, , , , $recordB] = twoPropertiesWith($model);
    $recordB->setAttribute('updated_at', now()->addYear());

    expect(fn () => $recordB->save())->toThrow(PropertyAccessDenied::class)
        ->and(fn () => $recordB->delete())->toThrow(PropertyAccessDenied::class);
})->with('property models');

it('creates records in the current property and refuses other properties', function (string $model): void {
    [, $a, $b] = twoPropertiesWith($model);

    expect(propertyFactoryFor($model)->create()->getAttribute('property_id'))->toBe($a->id)
        ->and(fn () => propertyFactoryFor($model)->create(['property_id' => $b->id]))->toThrow(PropertyAccessDenied::class);
})->with('property models');

it('refuses to move a record to another property', function (string $model): void {
    [, , $b, $recordA] = twoPropertiesWith($model);
    $recordA->setAttribute('property_id', $b->id);

    expect(fn () => $recordA->save())->toThrow(PropertyAccessDenied::class);
})->with('property models');
