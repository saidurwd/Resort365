{{--
    Permissions grouped by module. $groups from PermissionRegistry::grouped(), $granted = names held.
    Editable when $editable (checkboxes named permissions[]), read-only otherwise.
--}}
<div class="row g-3">
    @foreach ($groups as $module => $group)
        <div class="col-md-6" x-data>
            <div class="border rounded p-3 h-100" data-permission-group="{{ $module }}">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">{{ __($group['label']) }}</h6>
                    @if ($editable)
                        <button type="button" class="btn btn-link btn-sm p-0"
                                x-on:click="const boxes = $el.closest('[data-permission-group]').querySelectorAll('input[type=checkbox]'); const all = [...boxes].every(b => b.checked); boxes.forEach(b => b.checked = ! all)">
                            {{ __('Select all') }}
                        </button>
                    @endif
                </div>
                @foreach ($group['permissions'] as $permission)
                    @php($held = in_array($permission->name, $granted, true))
                    <div class="form-check">
                        @if ($editable)
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->name }}" id="perm-{{ $permission->name }}" @checked(in_array($permission->name, old('permissions', $granted), true))>
                            <label class="form-check-label" for="perm-{{ $permission->name }}">{{ __($permission->label) }}</label>
                        @else
                            <i @class(['bi me-1', 'bi-check-circle-fill text-success' => $held, 'bi-dash-circle text-body-tertiary' => ! $held])></i>
                            <span @class(['text-body-secondary' => ! $held])>{{ __($permission->label) }}</span>
                        @endif
                        <code class="small text-body-tertiary ms-1">{{ $permission->name }}</code>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
