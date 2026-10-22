<?php

/*
| Every table a tenant owns can be read by tenant cheaply (ARCHITECTURE §9.4, rule R3, Step 7.5): it has an index whose first column is
| `tenant_id`. Checked on the migrated schema, so a new module's table is covered without listing it.
*/
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('gives every table with a tenant_id an index that starts with tenant_id', function (): void {
    $tables = Schema::getTableListing(Schema::getCurrentSchemaListing(), false);
    $missing = [];

    foreach ($tables as $table) {
        if (! Schema::hasColumn($table, 'tenant_id')) {
            continue;
        }

        $leading = collect(Schema::getIndexes($table))->contains(fn (array $index): bool => ($index['columns'][0] ?? null) === 'tenant_id');

        if (! $leading) {
            $missing[] = $table;
        }
    }

    expect($missing)->toBe([]);
});
