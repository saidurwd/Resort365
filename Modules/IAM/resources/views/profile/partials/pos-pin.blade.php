<x-card :title="__('POS PIN')" icon="bi-grid-3x3" id="pos-pin" data-pos-pin>
    <p class="text-body-secondary small">
        {{ __('Signs you in on the restaurant\'s POS tablets, and approves actions as a manager. 4 to 6 digits; keep it to yourself.') }}
        @if ($user->pos_pin_set_at)<br>{{ __('Set :time.', ['time' => $user->pos_pin_set_at->diffForHumans()]) }}@endif
    </p>
    <form method="POST" action="{{ route('iam.profile.pos-pin.update') }}">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-sm-6"><x-form.input name="pin" type="password" inputmode="numeric" autocomplete="off" :label="__('New PIN')" error-bag="posPin" /></div>
            <div class="col-sm-6"><x-form.input name="pin_confirmation" type="password" inputmode="numeric" autocomplete="off" :label="__('PIN again')" error-bag="posPin" /></div>
        </div>
        <x-form.input name="current_password" type="password" autocomplete="current-password" :label="__('Your password')" error-bag="posPin" required />
        <button type="submit" class="btn btn-primary">{{ __('Save PIN') }}</button>
        @if ($user->pos_pin_set_at)
            <button type="submit" name="remove" value="1" class="btn btn-outline-danger">{{ __('Remove PIN') }}</button>
        @endif
    </form>
</x-card>
