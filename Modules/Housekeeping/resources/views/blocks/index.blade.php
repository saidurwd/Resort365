<x-layouts::app :title="__('Out of order')" :subtitle="$property->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Housekeeping') => null, __('Out of order') => null]">
    <div class="row">
        <div class="col-xl-4">
            <x-card :title="__('Block a room')" icon="bi-cone-striped">
                <form method="POST" action="{{ route('housekeeping.blocks.store') }}" data-block-form>
                    @csrf
                    <x-form.select name="room_id" :label="__('Room')" :options="$rooms" required />
                    <x-form.select name="type" :label="__('Type')" :options="$types" :search="false" :value="old('type', 'out_of_order')" required
                        :help="__('Out of order takes the room off sale; out of service only flags it.')" />
                    <div class="row">
                        <div class="col-6"><x-form.date name="from_date" :label="__('From')" :value="old('from_date', $property->businessDate)" required /></div>
                        <div class="col-6"><x-form.date name="to_date" :label="__('Back on')" :value="old('to_date')" required /></div>
                    </div>
                    <x-form.input name="reason" :label="__('Reason')" required />
                    <button type="submit" class="btn btn-danger"><i class="bi bi-cone-striped"></i> {{ __('Block room') }}</button>
                </form>
            </x-card>
        </div>
        <div class="col-xl-8">
            <x-card :title="__('Current and coming blocks')" icon="bi-calendar-x" body-class="p-0">
                @forelse ($active as $block)
                    <div class="d-flex flex-wrap gap-2 align-items-center px-3 py-2 border-bottom" data-block="{{ $block->id }}">
                        <span class="fw-semibold">{{ $rooms[$block->room_id] ?? '' }}</span>
                        <x-status-badge :status="$block->type" />
                        <span>{{ $block->from_date->format('d M') }} → {{ $block->to_date->format('d M Y') }}</span>
                        <span class="text-body-secondary">{{ $block->reason }}</span>
                        <form method="POST" action="{{ route('housekeeping.blocks.end', $block) }}" class="ms-auto" data-confirm="{{ __('Put the room back in service now?') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-check2-circle"></i> {{ __('Back in service') }}</button>
                        </form>
                    </div>
                @empty
                    <x-empty-state icon="bi-check2-circle" :title="__('Every room is in service')" />
                @endforelse
            </x-card>
            @if ($recent->isNotEmpty())
                <x-card :title="__('Recently ended')" icon="bi-clock-history" body-class="p-0">
                    @foreach ($recent as $block)
                        <div class="d-flex flex-wrap gap-2 px-3 py-2 border-bottom small">
                            <span class="fw-semibold">{{ $rooms[$block->room_id] ?? '' }}</span> <span>{{ $block->type->label() }}</span>
                            <span>{{ $block->from_date->format('d M') }} → {{ $block->to_date->format('d M Y') }}</span> <span class="text-body-secondary">{{ $block->reason }}</span>
                        </div>
                    @endforeach
                </x-card>
            @endif
        </div>
    </div>
</x-layouts::app>
