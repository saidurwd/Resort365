@if ($newToken)
    <div class="alert alert-warning" data-new-token>
        <i class="bi bi-key"></i> {{ __('Device token') }}: <span class="font-monospace fw-semibold">{{ $newToken['token'] }}</span>
        <div class="small">{{ __('Enter it on the tablet when the POS asks for it. It is shown only now; you can make a new one at any time.') }}</div>
    </div>
@endif
<x-card body-class="p-0">
    @forelse ($outlet->terminals as $terminal)
        <form method="POST" action="{{ route('restaurant.outlets.terminals.update', [$outlet, $terminal]) }}" class="d-flex flex-wrap gap-2 align-items-end px-3 py-2 border-bottom" data-terminal="{{ $terminal->name }}">
            @csrf @method('PUT')
            <x-form.input name="name" :id="'terminal-name-'.$terminal->id" :label="__('Terminal')" :value="$terminal->name" wrapper-class="mb-0" required />
            <x-form.select name="receipt_printer_id" :id="'terminal-printer-'.$terminal->id" :label="__('Receipt printer')" :options="$receiptPrinters" :value="$terminal->receipt_printer_id" :placeholder="__('None')" wrapper-class="mb-0" />
            <div class="form-check mb-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" class="form-check-input" name="is_active" value="1" id="terminal-active-{{ $terminal->id }}" @checked($terminal->is_active)>
                <label class="form-check-label" for="terminal-active-{{ $terminal->id }}">{{ __('Active') }}</label>
            </div>
            <span class="small text-body-secondary mb-2">{{ $terminal->last_seen_at ? __('last seen :time', ['time' => $terminal->last_seen_at->diffForHumans()]) : __('not signed in yet') }}</span>
            @if ($canManage)
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-save"></i> {{ __('Save') }}</button>
                <button type="submit" form="token-{{ $terminal->id }}" class="btn btn-outline-warning"><i class="bi bi-key"></i> {{ __('New token') }}</button>
            @endif
        </form>
        <form method="POST" action="{{ route('restaurant.outlets.terminals.token', [$outlet, $terminal]) }}" id="token-{{ $terminal->id }}" data-confirm="{{ __('Give :name a new device token? The tablet must sign in again.', ['name' => $terminal->name]) }}">@csrf</form>
    @empty
        <x-empty-state icon="bi-tablet" :title="__('No terminals yet')" :message="__('Register each tablet or till that takes orders here.')" />
    @endforelse
</x-card>
@if ($canManage)
    <x-card :title="__('Register a terminal')" icon="bi-plus-lg">
        <form method="POST" action="{{ route('restaurant.outlets.terminals.store', $outlet) }}" class="d-flex flex-wrap gap-2 align-items-end" data-add-terminal>
            @csrf
            <x-form.input name="name" id="new-terminal-name" :label="__('Terminal')" wrapper-class="mb-0" required />
            <x-form.select name="receipt_printer_id" id="new-terminal-printer" :label="__('Receipt printer')" :options="$receiptPrinters" :placeholder="__('None')" wrapper-class="mb-0" />
            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Register') }}</button>
        </form>
    </x-card>
@endif
