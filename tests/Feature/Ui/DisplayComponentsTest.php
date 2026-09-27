<?php

use App\Support\UiKit\DemoStatus;
use Illuminate\Support\Carbon;

it('renders a status badge from an enum', function (): void {
    blade('<x-status-badge :status="$status" />', ['status' => DemoStatus::Cancelled])
        ->assertSee('<span class="badge text-bg-danger">Cancelled</span>', false);
});

it('renders a card with header, tools and footer', function (): void {
    blade('<x-card title="Rooms" icon="bi-door-open" variant="primary"><x-slot:tools>T</x-slot:tools> Body <x-slot:footer>F</x-slot:footer></x-card>')
        ->assertSee('card mb-4 card-outline card-primary', false)
        ->assertSeeInOrder(['card-title', 'Rooms', 'card-tools', 'T', 'card-body', 'Body', 'card-footer', 'F'], false);
});

it('renders a stat box with a link', function (): void {
    blade('<x-stat-box value="78%" label="Occupancy" icon="bi-house" color="success" url="/reports" />')
        ->assertSee('small-box text-bg-success', false)
        ->assertSee('78%')
        ->assertSee('href="/reports"', false);
});

it('renders an empty state with an action', function (): void {
    blade('<x-empty-state title="No rooms" message="Add one."><a href="/new">Add</a></x-empty-state>')
        ->assertSeeInOrder(['No rooms', 'Add one.', '<a href="/new">Add</a>'], false);
});

it('renders a modal wired to its title', function (): void {
    blade('<x-modal id="add-room" title="Add room" size="lg">Form</x-modal>')
        ->assertSee('id="add-room"', false)
        ->assertSee('aria-labelledby="add-room-title"', false)
        ->assertSee('modal-lg', false)
        ->assertSee('Form');
});

it('renders a confirm-delete form with CSRF, DELETE method and confirmation', function (): void {
    blade('<x-confirm-delete action="/rooms/5" text="Room 101 will be removed." />')
        ->assertSee('action="/rooms/5"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('name="_method" value="DELETE"', false)
        ->assertSee('data-confirm="Delete this record?"', false)
        ->assertSee('data-confirm-variant="danger"', false)
        ->assertSee('Room 101 will be removed.');
});

it('shows session flash messages', function (string $key, string $class): void {
    session()->flash($key, 'Saved it.');

    blade('<x-flash-messages />')
        ->assertSee("alert alert-{$class}", false)
        ->assertSee('Saved it.');
})->with([
    ['success', 'success'],
    ['status', 'success'],
    ['error', 'danger'],
    ['warning', 'warning'],
    ['info', 'info'],
]);

it('summarises validation errors', function (): void {
    withViewErrors(['a' => 'x', 'b' => 'y']);

    blade('<x-flash-messages />')->assertSee('Please correct the 2 errors below.');
});

it('renders a server-side datatable definition', function (): void {
    $columns = [
        ['data' => 'code', 'title' => 'Code'],
        ['data' => 'total', 'title' => 'Total', 'className' => 'text-end', 'searchable' => false],
    ];

    blade('<x-datatable id="t" url="/data" :columns="$columns" />', ['columns' => $columns])
        ->assertSee('id="t"', false)
        ->assertSee('data-datatable=', false)
        ->assertSee('&quot;ajax&quot;:&quot;\/data&quot;', false)
        ->assertSee('&quot;searchable&quot;:false', false)
        ->assertSee('<th class="text-end">Total</th>', false);
});

it('renders attachments, approvals and the audit trail from plain data', function (): void {
    $at = Carbon::parse('2026-09-24 11:05');

    blade('<x-attachments :items="$files" upload-url="/files" />', ['files' => [['name' => 'passport.pdf', 'url' => '/f/1', 'size' => 2048, 'uploaded_by' => 'Front Desk', 'uploaded_at' => $at]]])
        ->assertSee('passport.pdf')
        ->assertSee('2 KB')
        ->assertSee('enctype="multipart/form-data"', false);

    blade('<x-approval-panel :steps="$steps" approve-url="/approve" />', ['steps' => [['level' => 1, 'role' => 'GM', 'approver' => null, 'status' => DemoStatus::Tentative, 'acted_at' => null, 'comment' => null]]])
        ->assertSee('GM')
        ->assertSee('Awaiting decision')
        ->assertSee('formaction="/approve"', false);

    blade('<x-audit-trail :entries="$entries" />', ['entries' => [['description' => 'Reservation confirmed', 'causer' => 'Nusrat', 'at' => $at, 'changes' => ['status' => ['tentative', 'confirmed']]]]])
        ->assertSeeInOrder(['Reservation confirmed', 'Nusrat', '24 Sep 2026 11:05', 'Status', 'tentative', 'confirmed']);

    blade('<x-audit-trail :entries="[]" />')->assertSee('No changes recorded');
});
