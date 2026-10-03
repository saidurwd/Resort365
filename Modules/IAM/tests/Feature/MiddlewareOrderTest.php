<?php

use App\Http\Middleware\EnsureUserBelongsToTenant;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\SetCurrentProperty;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Modules\IAM\Http\Middleware\EnsureTwoFactorEnabled;
use Modules\IAM\Http\Middleware\EnsureUserIsActive;
use Modules\IAM\Http\Middleware\SetUserLocale;

it('resolves the tenant and checks the user before authentication and route binding', function (): void {
    $priority = array_flip(app(Kernel::class)->getMiddlewarePriority());
    $expected = [StartSession::class, IdentifyTenant::class, EnsureUserBelongsToTenant::class, SetCurrentProperty::class, EnsureUserIsActive::class, SetUserLocale::class, EnsureTwoFactorEnabled::class, AuthenticatesRequests::class, SubstituteBindings::class];

    $positions = array_map(fn (string $middleware): int => $priority[$middleware] ?? PHP_INT_MAX, $expected);

    expect($positions)->toBe(array_values(array_unique($positions)))
        ->and($positions)->toEqual(collect($positions)->sort()->values()->all())
        ->and(max($positions))->toBeLessThan(PHP_INT_MAX);
});

it('adds the IAM checks to the tenant middleware group', function (): void {
    expect(app('router')->getMiddlewareGroups()['tenant'])->toBe([
        IdentifyTenant::class,
        EnsureUserBelongsToTenant::class,
        SetCurrentProperty::class,
        EnsureUserIsActive::class,
        SetUserLocale::class,
        EnsureTwoFactorEnabled::class,
    ]);
});
