@php
    $money = fn (string $amount): string => number_format((float) $amount, 2);
    $closed = in_array($order->status, [\Modules\Housekeeping\Enums\WorkOrderStatus::Done, \Modules\Housekeeping\Enums\WorkOrderStatus::Cancelled], true);
@endphp
<x-layouts::app :title="'WO-'.$order->id.' · '.$order->title" :subtitle="$property->name"
    :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Work orders') => auth()->user()?->can('housekeeping.work-order.view') ? route('housekeeping.work-orders.index') : null, 'WO-'.$order->id => null]">
    <div class="row">
        <div class="col-xl-5">
            <x-card icon="bi-tools" :title="__('Fault')">
                <dl class="row mb-0" data-work-order="{{ $order->id }}">
                    <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7"><x-status-badge :status="$order->status" /></dd>
                    <dt class="col-5">{{ __('Priority') }}</dt><dd class="col-7"><x-status-badge :status="$order->priority" /></dd>
                    <dt class="col-5">{{ __('Where') }}</dt><dd class="col-7">{{ $order->room_id ? ($rooms[$order->room_id] ?? '') : $order->location }}@if ($order->room_id && $order->location) · {{ $order->location }}@endif</dd>
                    <dt class="col-5">{{ __('Category') }}</dt><dd class="col-7">{{ $order->category->label() }}</dd>
                    <dt class="col-5">{{ __('Reported') }}</dt><dd class="col-7">{{ $order->created_at?->format('d M Y H:i') }} · {{ $order->reported_by ? ($names[$order->reported_by] ?? '') : __('Preventive schedule') }}</dd>
                    @if ($order->due_on)<dt class="col-5">{{ __('Due') }}</dt><dd class="col-7">{{ $order->due_on->format('d M Y') }}</dd>@endif
                    <dt class="col-5">{{ __('Technician') }}</dt><dd class="col-7">{{ $order->assigned_to ? ($names[$order->assigned_to] ?? '') : '—' }}</dd>
                    @if ($order->completed_at)<dt class="col-5">{{ __('Closed') }}</dt><dd class="col-7">{{ $order->completed_at->format('d M Y H:i') }}</dd>@endif
                    <dt class="col-5">{{ __('Cost') }}</dt><dd class="col-7">{{ __('Labour') }} {{ $money($order->labour_cost) }} · {{ __('Parts') }} {{ $money($order->parts_cost) }}</dd>
                </dl>
                @if ($order->description)<p class="mt-3 mb-0">{{ $order->description }}</p>@endif
                @if ($order->resolution)<div class="alert alert-success mt-3 mb-0">{{ $order->resolution }}</div>@endif
            </x-card>
        </div>
        @if ($canUpdate && ! $closed)
            <div class="col-xl-7">
                <x-card :title="__('Work on it')" icon="bi-wrench-adjustable">
                    <form method="POST" action="{{ route('housekeeping.work-orders.update', $order) }}" data-update-work-order>
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-4"><x-form.select name="status" :label="__('Status')" :options="$statuses" :value="old('status', $order->status->value)" :search="false" required /></div>
                            <div class="col-md-4"><x-form.select name="priority" :label="__('Priority')" :options="$priorities" :value="old('priority', $order->priority->value)" :search="false" required /></div>
                            <div class="col-md-4"><x-form.select name="assigned_to" :label="__('Technician')" :options="$technicians" :value="old('assigned_to', $order->assigned_to)" :placeholder="__('Nobody yet')" /></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6"><x-form.money name="labour_cost" :label="__('Labour cost')" :currency="$property->currencyCode" :value="old('labour_cost', $order->labour_cost)" required /></div>
                            <div class="col-md-6"><x-form.money name="parts_cost" :label="__('Parts cost')" :currency="$property->currencyCode" :value="old('parts_cost', $order->parts_cost)" required /></div>
                        </div>
                        <x-form.field name="resolution" :label="__('What was done')" :help="__('Needed to close the work order as done.')">
                            <textarea name="resolution" id="field-resolution" rows="3" @class(['form-control', 'is-invalid' => $errors->has('resolution')])>{{ old('resolution', $order->resolution) }}</textarea>
                        </x-form.field>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save') }}</button>
                    </form>
                </x-card>
            </div>
        @endif
    </div>
</x-layouts::app>
