<x-layouts::pos :title="__('Sign in')" :terminal="$terminal">
    <div x-data="pinPad({ userId: @js(old('user_id')) })" class="pos-lock" data-lock-screen>
        <section>
            <h1 class="h4 mb-3">{{ __('Who is working?') }}</h1>
            <div class="pos-staff">
                @forelse ($staff as $person)
                    <button type="button" class="btn pos-staff__tile" :class="userId == {{ $person->id }} ? 'btn-primary' : 'btn-outline-primary'"
                        @click="choose({{ $person->id }})" data-staff="{{ $person->email }}">
                        <i class="bi bi-person-circle fs-2"></i>
                        <span>{{ $person->name }}</span>
                    </button>
                @empty
                    <p class="text-body-secondary">{{ __('Nobody can work here yet: staff need outlet access, the POS permission and a POS PIN (set on their profile).') }}</p>
                @endforelse
            </div>
        </section>
        <section class="pos-keypad" x-show="userId" x-cloak>
            <form method="POST" action="{{ route('pos.sign-in') }}" x-ref="form" data-pin-form>
                @csrf
                <input type="hidden" name="user_id" :value="userId">
                <input type="hidden" name="pin" :value="pin">
                <div class="pos-keypad__display" aria-live="polite"><span x-text="'●'.repeat(pin.length) || '{{ __('PIN') }}'"></span></div>
                @error('pin')<div class="alert alert-danger py-2" data-pin-error>{{ $message }}</div>@enderror
                <div class="pos-keypad__keys">
                    @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $digit)
                        <button type="button" class="btn btn-outline-secondary pos-keypad__key" @click="press('{{ $digit }}')" data-key="{{ $digit }}">{{ $digit }}</button>
                    @endforeach
                    <button type="button" class="btn btn-outline-secondary pos-keypad__key" @click="pin = ''" aria-label="{{ __('Clear') }}"><i class="bi bi-x-lg"></i></button>
                    <button type="button" class="btn btn-outline-secondary pos-keypad__key" @click="press('0')" data-key="0">0</button>
                    <button type="submit" class="btn btn-primary pos-keypad__key" :disabled="pin.length < 4" data-key="enter" aria-label="{{ __('Sign in') }}"><i class="bi bi-arrow-right"></i></button>
                </div>
            </form>
        </section>
    </div>
</x-layouts::pos>
