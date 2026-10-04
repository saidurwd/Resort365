<x-layouts::app :title="__('Report a fault')" :subtitle="$property->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Housekeeping') => null, __('Report a fault') => null]">
    <div class="row">
        <div class="col-xl-7">
            <x-card>
                <form method="POST" action="{{ route('housekeeping.work-orders.store') }}" data-report-fault>
                    @csrf
                    <x-form.input name="title" :label="__('What is wrong?')" required />
                    <div class="row">
                        <div class="col-md-6"><x-form.select name="room_id" :label="__('Room')" :options="$rooms" :placeholder="__('Not in a room')" /></div>
                        <div class="col-md-6"><x-form.input name="location" :label="__('Or where')" :help="__('E.g. lobby, pool, kitchen.')" /></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><x-form.select name="category" :label="__('Category')" :options="$categories" :search="false" required /></div>
                        <div class="col-md-6"><x-form.select name="priority" :label="__('Priority')" :options="$priorities" :value="old('priority', 'normal')" :search="false" required /></div>
                    </div>
                    <x-form.field name="description" :label="__('Details')">
                        <textarea name="description" id="field-description" rows="3" @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description') }}</textarea>
                    </x-form.field>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> {{ __('Report') }}</button>
                </form>
            </x-card>
        </div>
        <div class="col-xl-5">
            <x-card :title="__('My reports')" icon="bi-clock-history" body-class="p-0">
                @forelse ($mine as $order)
                    <a href="{{ route('housekeeping.work-orders.show', $order) }}" class="d-flex justify-content-between px-3 py-2 border-bottom text-decoration-none text-body">
                        <span>WO-{{ $order->id }} · {{ $order->title }}</span><x-status-badge :status="$order->status" />
                    </a>
                @empty
                    <p class="text-body-secondary px-3 py-2 mb-0">{{ __('Nothing reported yet.') }}</p>
                @endforelse
            </x-card>
        </div>
    </div>
</x-layouts::app>
