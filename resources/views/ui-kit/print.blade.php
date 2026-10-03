<x-layouts::print :title="__('Invoice INV-2026-00108')">
    <div class="d-flex justify-content-between align-items-start mb-4 avoid-break">
        <div>
            <h4 class="mb-1">{{ __('Rodela Eco Resort') }}</h4>
            <div class="text-body-secondary">{{ __('Marine Drive, Cox\'s Bazar, Bangladesh') }}</div>
        </div>
        <div class="text-end">
            <h2 class="mb-1">{{ __('Invoice') }}</h2>
            <div>INV-2026-00108</div>
            <div class="text-body-secondary">27 Sep 2026</div>
        </div>
    </div>

    <div class="mb-4 avoid-break">
        <div class="text-body-secondary small">{{ __('Bill to') }}</div>
        <strong>Rahim Uddin</strong><br>
        {{ __('Reservation') }} RSV-2026-00042 · 12–15 Oct 2026
    </div>

    <table class="table table-sm">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="text-end">{{ __('Qty') }}</th>
                <th class="text-end">{{ __('Rate') }}</th>
                <th class="text-end">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['description'] }}</td>
                    <td class="text-end">{{ $line['quantity'] }}</td>
                    <td class="text-end">{{ $line['rate'] }}</td>
                    <td class="text-end">{{ $line['amount'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><th colspan="3" class="text-end">{{ __('Subtotal') }}</th><td class="text-end">{{ $subtotal }}</td></tr>
            <tr><th colspan="3" class="text-end">{{ __('VAT 15%') }}</th><td class="text-end">{{ $vat }}</td></tr>
            <tr><th colspan="3" class="text-end">{{ __('Total (BDT)') }}</th><td class="text-end fw-bold">{{ $total }}</td></tr>
        </tfoot>
    </table>

    <p class="small text-body-secondary mt-5 avoid-break">{{ __('Thank you for staying with us.') }}</p>
</x-layouts::print>
