<x-layouts::print :title="__('Registration card :code', ['code' => $reservation->code])">
    <div class="d-flex justify-content-between align-items-start mb-4" data-registration-card>
        <div>
            <h1 class="h4 mb-1">{{ $property?->name }}</h1>
            <div class="text-body-secondary small">{{ $property?->address }}</div>
            <div class="text-body-secondary small">{{ collect([$property?->phone, $property?->email])->filter()->implode(' · ') }}</div>
        </div>
        <div class="text-end">
            <div class="h5 mb-0">{{ __('Registration card') }}</div>
            <div class="fw-semibold">{{ $reservation->code }}</div>
        </div>
    </div>

    <table class="table table-bordered">
        <tr><th class="w-25">{{ __('Guest') }}</th><td>{{ $reservation->guestName }}</td><th class="w-25">{{ __('Nationality') }}</th><td>{{ $guest?->nationalityCode }}</td></tr>
        <tr><th>{{ __('Phone') }}</th><td>{{ $guest?->phone }}</td><th>{{ __('Email') }}</th><td>{{ $guest?->email }}</td></tr>
        <tr><th>{{ __('ID document') }}</th><td colspan="3">{{ $guest?->idType ? \Modules\Guest\Enums\IdType::from($guest->idType)->label() : '' }} ________________________</td></tr>
        <tr><th>{{ __('Arrival') }}</th><td>{{ \Carbon\Carbon::parse($reservation->checkIn)->format('D d M Y') }}</td><th>{{ __('Departure') }}</th><td>{{ \Carbon\Carbon::parse($reservation->checkOut)->format('D d M Y') }}</td></tr>
        <tr><th>{{ __('Rooms') }}</th><td colspan="3">{{ implode(', ', $reservation->units) }}</td></tr>
        <tr><th>{{ __('Guests') }}</th><td>{{ trans_choice(':count adult|:count adults', $reservation->adults) }}@if ($reservation->children), {{ trans_choice(':count child|:count children', $reservation->children) }}@endif</td>
            <th>{{ __('Total') }}</th><td>{{ $reservation->currencyCode }} {{ number_format((float) $reservation->grandTotal, 2) }}</td></tr>
        <tr><th>{{ __('Address') }}</th><td colspan="3">&nbsp;</td></tr>
        <tr><th>{{ __('Coming from / going to') }}</th><td colspan="3">&nbsp;</td></tr>
    </table>

    <p class="small">{{ __('Check-out is by :time. I agree to pay all charges of my stay and accept the resort\'s house rules. The resort is not responsible for valuables not left in the safe.', ['time' => $property?->checkOutTime]) }}</p>

    <div class="row mt-5">
        <div class="col-6"><div class="border-top pt-1 small">{{ __('Guest signature') }}</div></div>
        <div class="col-6"><div class="border-top pt-1 small">{{ __('Front desk') }}</div></div>
    </div>
</x-layouts::print>
