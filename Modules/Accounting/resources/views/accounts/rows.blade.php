@foreach ($byParent->get($parent, collect()) as $account)
    <tr data-account="{{ $account->code }}" @class(['table-light fw-semibold' => $account->is_group, 'text-body-tertiary' => ! $account->is_active])>
        <td class="ps-3 font-monospace">{{ $account->code }}</td>
        <td style="padding-left: {{ 0.75 + $depth * 1.5 }}rem">
            @if ($account->is_group)<i class="bi bi-folder2-open text-body-secondary"></i>@endif
            {{ $account->name }}
            @if ($account->system_key)<span class="badge text-bg-light border" title="{{ __('Automatic postings use this account') }}">{{ __('system') }}</span>@endif
        </td>
        <td><x-status-badge :status="$account->type" /></td>
        <td class="small text-body-secondary">{{ $account->usali_department }}</td>
        <td>{{ $account->is_active ? __('Active') : __('Inactive') }}</td>
        <td class="text-end pe-3 text-nowrap">
            @if ($canManage)
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#account-modal" aria-label="{{ __('Change :account', ['account' => $account->name]) }}"
                    @click="edit({ code: @js($account->code), name: @js($account->name), type: @js($account->type->value), parent_id: @js($account->parent_id ?? ''), is_group: @js($account->is_group), is_active: @js($account->is_active), usali_department: @js($account->usali_department ?? ''), description: @js($account->description ?? '') }, @js(route('accounting.accounts.update', $account)))"><i class="bi bi-pencil"></i></button>
                @if (! $account->system_key && ! $account->children_count && ! $account->lines_count)
                    <form method="POST" action="{{ route('accounting.accounts.destroy', $account) }}" class="d-inline" data-confirm="{{ __('Delete :account?', ['account' => $account->label()]) }}" data-confirm-variant="danger">@csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete :account', ['account' => $account->name]) }}"><i class="bi bi-trash"></i></button></form>
                @endif
            @endif
        </td>
    </tr>
    @include('accounting::accounts.rows', ['parent' => $account->id, 'depth' => $depth + 1])
@endforeach
