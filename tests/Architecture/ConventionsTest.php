<?php

use App\Support\Actions\Action;
use App\Support\DTOs\Data;
use App\Support\Enums\HasLabelAndColor;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Modules\Platform\Models\PlatformAdmin;

require_once __DIR__.'/helpers.php';

/*
| Module models are tenant-owned unless listed here (central tables, ARCHITECTURE §4.2).
*/
$centralModels = [
    PlatformAdmin::class,
];

foreach (moduleNames() as $module) {
    arch("{$module} models are tenant-owned", function () use ($module, $centralModels): void {
        expect("Modules\\{$module}\\Models")->classes()->toUseTrait(BelongsToTenant::class)->ignoring($centralModels);
    });

    arch("{$module} actions extend the base Action", function () use ($module): void {
        expect("Modules\\{$module}\\Actions")->classes()->toExtend(Action::class);
    });

    arch("{$module} DTOs are readonly and extend the base Data class", function () use ($module): void {
        expect("Modules\\{$module}\\DTOs")->classes()->toBeReadonly()->toExtend(Data::class);
    });

    arch("{$module} enums have a label and a badge colour", function () use ($module): void {
        expect("Modules\\{$module}\\Enums")->toBeEnums()->toImplement(HasLabelAndColor::class);
    });

    arch("{$module} domain events are dispatched after commit", function () use ($module): void {
        expect("Modules\\{$module}\\Events")->classes()->toImplement(ShouldDispatchAfterCommit::class);
    });
}
