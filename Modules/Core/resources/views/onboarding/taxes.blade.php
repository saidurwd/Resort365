<x-layouts::app :title="__('Taxes')" :breadcrumbs="[__('Setup checklist') => route('core.onboarding.index'), __('Taxes') => null]">
    <div class="row">
        <div class="col-xl-7">
            <form method="POST" action="{{ route('core.onboarding.taxes.store') }}">
                @csrf
                <x-card :title="__('Taxes on rooms and food')" icon="bi-percent">
                    <p class="text-body-secondary">{{ __('Enter the rates you charge. A rate of 0 leaves that tax out. You can add other taxes later in Setup → Taxes.') }}</p>
                    <div class="row">
                        <div class="col-md-6"><x-form.input name="service_charge_rate" :label="__('Service charge %')" value="10" required inputmode="decimal" /></div>
                        <div class="col-md-6"><x-form.input name="vat_rate" :label="__('VAT %')" value="15" required inputmode="decimal" /></div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="vat_on_service_charge" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="vat_on_service_charge" id="field-vat_on_service_charge" value="1" @checked(old('vat_on_service_charge', true))>
                        <label class="form-check-label" for="field-vat_on_service_charge">{{ __('Charge VAT on the price plus the service charge') }}</label>
                    </div>
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('core.onboarding.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Set up taxes') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
