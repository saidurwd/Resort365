<?php

use Modules\Inventory\Services\ItemCsv;
use Tests\TestCase;

uses(TestCase::class);

it('reads items with optional columns and yes/no flags', function (): void {
    $read = (new ItemCsv)->parse("SKU,Name,Category,Base unit,Barcode,Reorder level,Standard cost,Expiry,Stockable\nRICE-5,Basmati rice,Food > Dry goods,kg,8801,\"1,000\",120.5,no,yes\nMILK,Milk,Food > Dairy,l,,20,,yes,n\n");

    expect($read['errors'])->toBe([])
        ->and($read['rows'][0])->toMatchArray(['sku' => 'RICE-5', 'name' => 'Basmati rice', 'category' => 'Food > Dry goods', 'base_unit' => 'kg', 'barcode' => '8801', 'reorder_level' => '1000.0000', 'standard_cost' => '120.5000', 'expiry' => false, 'stockable' => true])
        ->and($read['rows'][1])->toMatchArray(['barcode' => null, 'standard_cost' => null, 'expiry' => true, 'stockable' => false, 'reorder_level' => '20.0000']);
});

it('reads a semicolon-separated file', function (): void {
    $read = (new ItemCsv)->parse("sku;name;category;base_unit\nA1;Thing;Misc;pcs\n");

    expect($read['errors'])->toBe([])->and($read['rows'][0]['sku'])->toBe('A1');
});

it('names the missing columns and the problem lines', function (): void {
    expect((new ItemCsv)->parse("sku,name\nA,B\n")['errors'][0])->toContain('category')->toContain('base_unit');

    $read = (new ItemCsv)->parse("sku,name,category,base_unit,reorder_level\nA1,One,Misc,pcs,x\n,Two,Misc,pcs,\nA3,Three,Misc,pcs,5\nA3,Dup,Misc,pcs,5\n");

    expect($read['errors'])->toHaveCount(3)->and($read['errors'][0])->toContain('Line 2')->and($read['errors'][1])->toContain('Line 3')->and($read['errors'][2])->toContain('twice')->and($read['rows'])->toHaveCount(1);
});

it('refuses an empty file', function (): void {
    expect((new ItemCsv)->parse('')['errors'])->not->toBe([])->and((new ItemCsv)->parse("sku,name,category,base_unit\n")['errors'])->not->toBe([]);
});
