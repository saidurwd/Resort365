<?php

use App\Support\Ui\SidebarMenu;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

describe('in the local environment', function (): void {
    beforeEach(function (): void {
        app()['env'] = 'local';
    });

    it('shows the UI kit with every component', function (): void {
        get('/ui-kit')->assertOk()->assertSeeHtml('app-sidebar')->assertSeeHtml('data-datatable')->assertSeeHtml('data-tom-select')->assertSeeHtml('data-flatpickr')->assertSeeHtml('small-box')->assertSeeHtml('data-confirm')->assertSeeHtml('id="demo-modal"')
            ->assertSee(__('Attachments'))
            ->assertSee(__('History'))
            ->assertSee(__('The phone number is already used by another guest.'));
    });

    it('shows the print layout', function (): void {
        get('/ui-kit/print')->assertOk()->assertSeeHtml('print-sheet')->assertSee('40020.00')->assertDontSeeHtml('app-sidebar');
    });

    it('serves server-side DataTables JSON', function (): void {
        getJson('/ui-kit/datatable?draw=3&start=10&length=5&columns[0][data]=code&columns[0][searchable]=true&search[value]=')
            ->assertOk()
            ->assertJsonPath('draw', 3)
            ->assertJsonPath('recordsTotal', 60)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.code', 'RSV-2026-00011')
            ->assertJsonStructure(['data' => [['code', 'guest', 'arrival', 'nights', 'status', 'total']]]);
    });

    it('filters DataTables results on the server', function (): void {
        getJson('/ui-kit/datatable?draw=1&start=0&length=100&columns[0][data]=guest&columns[0][searchable]=true&search[value]=Kenji')
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 5);
    });
});

it('is hidden outside the local environment', function (string $path): void {
    app()['env'] = 'production';

    get($path)->assertNotFound();
})->with(['/ui-kit', '/ui-kit/print', '/ui-kit/datatable']);

it('is not linked from the sidebar outside the local environment', function (): void {
    expect(SidebarMenu::items())->toHaveCount(1)
        ->and(SidebarMenu::items()[0]['label'])->toBe(__('Home'));
});
