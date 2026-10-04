@forelse ($outlet->areas as $area)
    <x-card :title="$area->name" icon="bi-grid-3x3-gap" data-area="{{ $area->name }}">
        <div x-data="floorPlan(@js(['url' => route('restaurant.outlets.areas.positions', [$outlet, $area]), 'grid' => $canvas['grid'], 'width' => $canvas['width'], 'height' => $canvas['height'], 'editable' => $canEditFloor]))">
            <svg viewBox="0 0 {{ $canvas['width'] }} {{ $canvas['height'] }}" class="floor-plan" role="img" aria-label="{{ __('Floor plan of :area', ['area' => $area->name]) }}" x-ref="canvas"
                @pointermove="move($event)" @pointerup="drop()" @pointerleave="drop()">
                <defs>
                    <pattern id="grid-{{ $area->id }}" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M 20 0 L 0 0 0 20" class="floor-plan__grid" /></pattern>
                </defs>
                <rect width="{{ $canvas['width'] }}" height="{{ $canvas['height'] }}" fill="url(#grid-{{ $area->id }})" class="floor-plan__floor" />
                @foreach ($area->tables as $table)
                    @php([$w, $h] = $table->shape->size($table->seats))
                    <g data-table data-id="{{ $table->id }}" data-number="{{ $table->number }}" data-x="{{ $table->pos_x }}" data-y="{{ $table->pos_y }}" data-w="{{ $w }}" data-h="{{ $h }}"
                        transform="translate({{ $table->pos_x }} {{ $table->pos_y }})" @class(['floor-table', 'floor-table--inactive' => ! $table->is_active, 'floor-table--movable' => $canEditFloor])
                        @pointerdown="grab($event)">
                        @if ($table->shape === \Modules\Restaurant\Enums\TableShape::Round)
                            <ellipse cx="{{ $w / 2 }}" cy="{{ $h / 2 }}" rx="{{ $w / 2 }}" ry="{{ $h / 2 }}" class="floor-table__top" />
                        @else
                            <rect width="{{ $w }}" height="{{ $h }}" rx="8" class="floor-table__top" />
                        @endif
                        <text x="{{ $w / 2 }}" y="{{ $h / 2 - 2 }}" text-anchor="middle" class="floor-table__number">{{ $table->number }}</text>
                        <text x="{{ $w / 2 }}" y="{{ $h / 2 + 16 }}" text-anchor="middle" class="floor-table__seats">{{ trans_choice(':count seat|:count seats', $table->seats) }}</text>
                    </g>
                @endforeach
            </svg>
            @if ($canEditFloor)
                <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                    <button type="button" class="btn btn-primary" @click="save()" :disabled="busy || ! dirty" data-save-floor><i class="bi bi-save"></i> {{ __('Save floor plan') }}</button>
                    <span class="small" :class="ok ? 'text-success' : 'text-danger'" x-text="message" data-floor-message></span>
                    <span class="small text-body-secondary ms-auto">{{ __('Drag the tables into place, then save.') }}</span>
                </div>
            @endif
        </div>

        @if ($canEditFloor)
            <details class="mt-3">
                <summary class="small">{{ __('Tables and area') }}</summary>
                <div class="table-responsive mt-2">
                    <table class="table table-sm align-middle mb-2">
                        @foreach ($area->tables as $table)
                            <tr>
                                <td colspan="6">
                                    <form method="POST" action="{{ route('restaurant.outlets.tables.update', [$outlet, $table]) }}" class="d-flex flex-wrap gap-2 align-items-end" data-table-form="{{ $table->number }}">
                                        @csrf @method('PUT')
                                        <x-form.input name="number" :id="'table-number-'.$table->id" :label="__('Table')" :value="$table->number" wrapper-class="mb-0" required />
                                        <x-form.input name="seats" type="number" min="1" max="30" :id="'table-seats-'.$table->id" :label="__('Seats')" :value="$table->seats" wrapper-class="mb-0" required />
                                        <x-form.select name="shape" :id="'table-shape-'.$table->id" :label="__('Shape')" :options="$shapes" :value="$table->shape->value" :search="false" wrapper-class="mb-0" required />
                                        <x-form.select name="dining_area_id" :id="'table-area-'.$table->id" :label="__('Area')" :options="$outlet->areas->pluck('name', 'id')->all()" :value="$area->id" :search="false" wrapper-class="mb-0" required />
                                        <div class="form-check mb-2">
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" class="form-check-input" name="is_active" value="1" id="table-active-{{ $table->id }}" @checked($table->is_active)>
                                            <label class="form-check-label" for="table-active-{{ $table->id }}">{{ __('In use') }}</label>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-outline-primary mb-1"><i class="bi bi-save"></i></button>
                                        <button type="submit" form="delete-table-{{ $table->id }}" class="btn btn-sm btn-outline-danger mb-1" aria-label="{{ __('Remove table') }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                    <form method="POST" action="{{ route('restaurant.outlets.tables.destroy', [$outlet, $table]) }}" id="delete-table-{{ $table->id }}" data-confirm="{{ __('Remove table :number?', ['number' => $table->number]) }}" data-confirm-variant="danger">@csrf @method('DELETE')</form>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('restaurant.outlets.areas.update', [$outlet, $area]) }}" class="d-flex gap-2 align-items-end">
                        @csrf @method('PUT')
                        <x-form.input name="name" :id="'area-name-'.$area->id" :label="__('Area name')" :value="$area->name" wrapper-class="mb-0" required />
                        <button type="submit" class="btn btn-outline-primary">{{ __('Rename') }}</button>
                    </form>
                    <form method="POST" action="{{ route('restaurant.outlets.areas.destroy', [$outlet, $area]) }}" class="align-self-end" data-confirm="{{ __('Remove area :name?', ['name' => $area->name]) }}" data-confirm-variant="danger">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">{{ __('Remove area') }}</button>
                    </form>
                </div>
            </details>
        @endif
    </x-card>
@empty
    <x-card><x-empty-state icon="bi-grid-3x3-gap" :title="__('No dining areas yet')" :message="__('Room service and mini-bar outlets need no tables.')" /></x-card>
@endforelse

@if ($canEditFloor)
    <div class="row">
        @if ($outlet->areas->isNotEmpty())
            <div class="col-xl-8">
                <x-card :title="__('Add a table')" icon="bi-plus-lg">
                    <form method="POST" action="{{ route('restaurant.outlets.tables.store', $outlet) }}" class="d-flex flex-wrap gap-2 align-items-end" data-add-table>
                        @csrf
                        <x-form.select name="dining_area_id" id="new-table-area" :label="__('Area')" :options="$outlet->areas->pluck('name', 'id')->all()" :search="false" wrapper-class="mb-0" required />
                        <x-form.input name="number" id="new-table-number" :label="__('Table')" wrapper-class="mb-0" required />
                        <x-form.input name="seats" id="new-table-seats" type="number" min="1" max="30" :label="__('Seats')" value="4" wrapper-class="mb-0" required />
                        <x-form.select name="shape" id="new-table-shape" :label="__('Shape')" :options="$shapes" value="square" :search="false" wrapper-class="mb-0" required />
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add table') }}</button>
                    </form>
                </x-card>
            </div>
        @endif
        <div class="col-xl-4">
            <x-card :title="__('Add an area')" icon="bi-plus-lg">
                <form method="POST" action="{{ route('restaurant.outlets.areas.store', $outlet) }}" class="d-flex gap-2 align-items-end" data-add-area>
                    @csrf
                    <x-form.input name="name" id="new-area-name" :label="__('Area')" :help="__('E.g. Indoor, Terrace, Pool deck.')" wrapper-class="mb-0" required />
                    <button type="submit" class="btn btn-primary mb-4">{{ __('Add') }}</button>
                </form>
            </x-card>
        </div>
    </div>
@endif
