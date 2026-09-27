{{-- Local-only showcase of every shared component. Keep it in sync when adding components. --}}
<x-layouts::app :title="__('UI Kit')" :breadcrumbs="[__('Home') => url('/'), __('UI Kit') => null]">
    <x-slot:actions>
        <a href="{{ route('ui-kit.print') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print layout') }}</a>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#demo-modal"><i class="bi bi-window"></i> {{ __('Open modal') }}</button>
    </x-slot:actions>

    <section class="ui-kit-section">
        <h5 class="mb-3">{{ __('Flash messages') }}</h5>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-start" role="alert">
            <i class="bi bi-check-circle me-2 mt-1"></i><div>{{ __('Reservation RSV-2026-00042 was confirmed.') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
        <div class="alert alert-warning d-flex align-items-start" role="alert">
            <i class="bi bi-exclamation-triangle me-2 mt-1"></i><div>{{ __('Deposit is due within 48 hours.') }}</div>
        </div>
        <p class="small text-body-secondary mb-0">{{ __('Real flash messages come from session keys success, status, error, warning and info through the flash-messages component, which every layout includes.') }}</p>
    </section>

    <section class="ui-kit-section">
        <h5 class="mb-3">{{ __('Stat boxes') }}</h5>
        <div class="row">
            <div class="col-6 col-lg-3"><x-stat-box value="78%" :label="__('Occupancy tonight')" icon="bi-house-check" color="primary" url="#" /></div>
            <div class="col-6 col-lg-3"><x-stat-box value="12" :label="__('Arrivals today')" icon="bi-box-arrow-in-right" color="success" url="#" /></div>
            <div class="col-6 col-lg-3"><x-stat-box value="9" :label="__('Departures today')" icon="bi-box-arrow-right" color="warning" /></div>
            <div class="col-6 col-lg-3"><x-stat-box value="BDT 4,85,200" :label="__('Revenue this month')" icon="bi-cash-stack" color="danger" /></div>
        </div>
    </section>

    <section class="ui-kit-section">
        <h5 class="mb-3">{{ __('Status badges') }}</h5>
        <x-card>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($statuses as $status)
                    <x-status-badge :status="$status" />
                @endforeach
            </div>
        </x-card>
    </section>

    <section class="ui-kit-section">
        <h5 class="mb-3">{{ __('Forms') }}</h5>
        <x-card :title="__('New booking')" icon="bi-calendar-plus" variant="primary">
            <form method="POST" action="#" onsubmit="return false">
                <div class="row">
                    <div class="col-md-6"><x-form.input name="guest_name" :label="__('Guest name')" required :help="__('As shown on the passport or national ID.')" /></div>
                    <div class="col-md-6"><x-form.input name="email" type="email" :label="__('Email')" prepend="@" /></div>
                    <div class="col-md-6"><x-form.date name="arrival_date" :label="__('Arrival')" required value="2026-10-12" :min="now()->toDateString()" /></div>
                    <div class="col-md-6"><x-form.date name="stay" :label="__('Stay (range)')" range /></div>
                    <div class="col-md-6"><x-form.select name="cottage_type" :label="__('Cottage type')" :options="$cottageOptions" value="lake-view" required /></div>
                    <div class="col-md-6"><x-form.select name="amenities" :label="__('Extras')" :options="$amenityOptions" :value="['wifi', 'breakfast']" multiple /></div>
                    <div class="col-md-6"><x-form.money name="deposit_amount" :label="__('Deposit')" currency="BDT" value="7500.00" required :help="__('30–50% of the stay total.')" /></div>
                    <div class="col-md-6"><x-form.input name="adults" type="number" :label="__('Adults')" value="2" min="1" /></div>
                </div>
                <div class="d-flex gap-2 justify-content-end">
                    <button type="reset" class="btn btn-outline-secondary">{{ __('Reset') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save booking') }}</button>
                </div>
            </form>
        </x-card>

        <x-card :title="__('Validation state')" icon="bi-exclamation-circle" collapsible>
            @include('ui-kit.partials.validation-demo')
        </x-card>
    </section>

    <section class="ui-kit-section">
        <h5 class="mb-3">{{ __('Server-side DataTable') }}</h5>
        <x-card body-class="p-3">
            <x-datatable id="demo-reservations" :url="route('ui-kit.datatable')" :columns="$datatableColumns" :order="[[2, 'asc']]" :page-length="10" />
        </x-card>
    </section>

    <section class="ui-kit-section">
        <h5 class="mb-3">{{ __('Buttons, dialogs and empty state') }}</h5>
        <div class="row">
            <div class="col-lg-6">
                <x-card :title="__('Confirm dialogs')">
                    <div class="d-flex flex-wrap gap-2">
                        <x-confirm-delete action="#" :text="__('Room 101 will be removed.')" />
                        <x-confirm-delete action="#" icon-only />
                        <button type="button" class="btn btn-sm btn-outline-primary" data-confirm="{{ __('Check in this guest?') }}" data-confirm-text="{{ __('Room 204 will be marked occupied.') }}">
                            <i class="bi bi-box-arrow-in-right"></i> {{ __('Check in') }}
                        </button>
                    </div>
                </x-card>
            </div>
            <div class="col-lg-6">
                <x-card :title="__('Empty state')">
                    <x-empty-state icon="bi-door-closed" :title="__('No rooms yet')" :message="__('Add a cottage with its rooms to get started.')">
                        <a href="#" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add cottage') }}</a>
                    </x-empty-state>
                </x-card>
            </div>
        </div>
    </section>

    <section class="ui-kit-section">
        <h5 class="mb-3">{{ __('Attachments, approvals and history') }}</h5>
        <div class="row">
            <div class="col-lg-4"><x-attachments :items="$attachments" upload-url="#" /></div>
            <div class="col-lg-4"><x-approval-panel :steps="$approvalSteps" approve-url="#" reject-url="#" :title="__('PO-2026-00017 approvals')" /></div>
            <div class="col-lg-4"><x-audit-trail :entries="$auditEntries" /></div>
        </div>
    </section>

    <x-modal id="demo-modal" :title="__('Add room')" size="lg">
        <div class="row">
            <div class="col-md-6"><x-form.input name="room_number" :label="__('Room number')" required /></div>
            <div class="col-md-6"><x-form.select name="room_cottage" :label="__('Cottage')" :options="$cottageOptions" required /></div>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">{{ __('Save') }}</button>
        </x-slot:footer>
    </x-modal>
</x-layouts::app>
