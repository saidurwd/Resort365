<x-layouts::app :title="__('Discount limits')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Discount limits') => null]">
    <form method="POST" action="{{ route('restaurant.discount-limits.update') }}" data-discount-limits>
        @csrf
        @method('PUT')

        <p class="text-body-secondary">{{ __('The largest discount each role may give on the POS on its own. Above it, a manager approves with their PIN. A person with several roles gets the highest limit; managers may give any discount.') }}</p>
        <x-card body-class="p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th class="ps-3">{{ __('Role') }}</th><th class="pe-3">{{ __('Largest discount %') }}</th></tr></thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr data-role="{{ $role->name }}">
                                <td class="ps-3"><input type="hidden" name="roles[]" value="{{ $role->id }}">{{ $role->name }}</td>
                                <td class="pe-3">
                                    <label class="visually-hidden" for="limit-{{ $role->id }}">{{ __('Largest discount % for :role', ['role' => $role->name]) }}</label>
                                    <input type="number" step="0.01" min="0" max="100" id="limit-{{ $role->id }}" name="limits[{{ $role->id }}]" class="form-control form-control-sm limit-input"
                                        value="{{ old('limits.'.$role->id, $limits[$role->id] ?? '') }}" placeholder="0">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-slot:footer>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save limits') }}</button>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
