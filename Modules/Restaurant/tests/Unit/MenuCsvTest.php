<?php

/*
| MenuCsv: reading and checking a menu CSV, without the database.
*/

use Modules\Restaurant\Services\MenuCsv;
use Tests\TestCase;

uses(TestCase::class); // error messages are translated

it('reads items with categories, tags, variants and prices per outlet', function (): void {
    $csv = "code,name,name_bn,category,course,kind,tax_category,dietary_tags,allergens,variants,price:MR,price:PB\n"
        ."bd01,Chicken curry,মুরগির তরকারি,Food > Bangladeshi,main,,fnb,halal|SPICY,,Half|Full,380|650,\n"
        ."\"SD02\",\"Coca-Cola, can\",,Drinks > Soft drinks,drink,direct_stock,,,,,80,90\n";

    $result = (new MenuCsv)->parse($csv, ['MR', 'PB'], ['en', 'bn']);

    expect($result['errors'])->toBe([])
        ->and($result['rows'][0])->toMatchArray([
            'line' => 2, 'code' => 'BD01', 'name' => ['en' => 'Chicken curry', 'bn' => 'মুরগির তরকারি'], 'category' => ['Food', 'Bangladeshi'], 'course' => 'main',
            'kind' => 'dish', 'tax_category' => 'FNB', 'dietary_tags' => ['halal', 'spicy'], 'variants' => ['Half', 'Full'], 'prices' => ['MR' => ['380', '650']],
        ])
        ->and($result['rows'][1])->toMatchArray(['code' => 'SD02', 'name' => ['en' => 'Coca-Cola, can', 'bn' => ''], 'kind' => 'direct_stock', 'prices' => ['MR' => ['80'], 'PB' => ['90']]]);
});

it('reports every problem with its line and returns no rows', function (): void {
    $csv = "code,name,category,course,kind,dietary_tags,variants,price:MR\n"
        ."A1,Soup,Food,starter,,,Half|Full,250\n"
        ."A 2,,Food,lunch,meal,crunchy,,abc\n"
        ."a1,Copy,Food,main,,,,100\n";

    $result = (new MenuCsv)->parse($csv, ['MR'], ['en']);

    expect($result['rows'])->toBe([])
        ->and($result['errors'])->toContain('Line 2: MR needs 2 price(s), one per variant.')
        ->and($result['errors'])->toContain('Line 3: the code must be 1–20 letters, digits, - or _.')
        ->and($result['errors'])->toContain('Line 3: the name is missing.')
        ->and($result['errors'])->toContain('Line 3: dietary tag "crunchy" is unknown.')
        ->and($result['errors'])->toContain('Line 3: MR price "abc" is not an amount.')
        ->and($result['errors'])->toContain('Line 4: code A1 appears twice.')
        ->and(count($result['errors']))->toBe(8); // also course "lunch" and kind "meal" on line 3
});

it('refuses files without the required columns or with unknown outlets', function (): void {
    expect((new MenuCsv)->parse("code,name,price:XX\n", ['MR'], ['en'])['errors'])
        ->toBe(['Column "category" is missing.', 'Column "course" is missing.', 'Column "price:xx": no outlet has that code.'])
        ->and((new MenuCsv)->parse('', ['MR'], ['en'])['errors'])->toBe(['The file is empty.']);
});
