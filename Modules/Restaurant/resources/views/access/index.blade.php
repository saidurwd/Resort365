<x-layouts::app :title="__('Outlet access')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Outlet access') => null]">
    <form method="POST" action="{{ route('restaurant.access.update') }}">
        @csrf
        @method('PUT')

        <p class="text-body-secondary">{{ __('Staff work only in the outlets ticked here (POS, kitchen display).') }}</p>
        <x-card body-class="p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-access-grid>
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('User') }}</th>
                            @foreach ($outlets as $outlet)
                                <th class="text-center">{{ $outlet->name }}<div class="small text-body-secondary fw-normal">{{ $outlet->code }}</div></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="ps-3">
                                    <input type="hidden" name="users[]" value="{{ $user->id }}">
                                    <div class="fw-semibold">{{ $user->name }}</div>
                                    <div class="small text-body-secondary">{{ $user->email }}</div>
                                </td>
                                @foreach ($outlets as $outlet)
                                    <td class="text-center">
                                        @if ($seesAll[$user->id])
                                            <i class="bi bi-check-circle-fill text-success" title="{{ __('Works in every outlet (role permission)') }}"></i>
                                        @else
                                            <input class="form-check-input" type="checkbox" name="access[{{ $outlet->id }}][]" value="{{ $user->id }}"
                                                   aria-label="{{ $user->name }} — {{ $outlet->name }}" @checked(isset($assigned[$outlet->id.':'.$user->id]))>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-slot:footer>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small text-body-secondary"><i class="bi bi-check-circle-fill text-success"></i> {{ __('works in every outlet through their role (e.g. Tenant Owner, General Manager).') }}</span>
                    <button type="submit" class="btn btn-primary">{{ __('Save access') }}</button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::app>
