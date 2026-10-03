{{-- Night-by-night breakdown of a price quote. --}}
<details class="small mt-1" data-nights>
    <summary>{{ trans_choice(':count night|:count nights', count($quote->nights)) }} · {{ __('avg :amount', ['amount' => number_format((float) $quote->averagePerNight(), 2)]) }}</summary>
    <div class="table-responsive">
    <table class="table table-sm mb-0 mt-1">
        <thead>
            <tr><th>{{ __('Night') }}</th><th class="text-end">{{ __('Rate') }}</th><th class="text-end">{{ __('Extras') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('Tax') }}</th><th class="text-end">{{ __('Total') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($quote->nights as $night)
                <tr data-night="{{ $night->date }}">
                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($night->date)->format('D d M') }}
                        @if ($night->seasonName)<span class="text-body-secondary">· {{ $night->seasonName }}</span>@endif
                        @if ($night->source === 'override')<span class="badge text-bg-danger">{{ __('date price') }}</span>@endif
                        @if ($night->source === 'rooms')<span class="badge text-bg-info">{{ __('sum of rooms') }}</span>@endif
                    </td>
                    <td class="text-end font-monospace">{{ number_format((float) $night->base, 2) }}</td>
                    <td class="text-end font-monospace">{{ (float) $night->extras ? number_format((float) $night->extras, 2) : '—' }}</td>
                    <td class="text-end font-monospace">{{ (float) $night->discount ? '−'.number_format((float) $night->discount, 2) : '—' }}</td>
                    <td class="text-end font-monospace">{{ number_format((float) $night->tax, 2) }}</td>
                    <td class="text-end font-monospace">{{ number_format((float) $night->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    @if ((float) $quote->mealComponent > 0)
        <div class="text-body-secondary">{{ __('Includes meals worth :amount.', ['amount' => number_format((float) $quote->mealComponent, 2)]) }}</div>
    @endif
</details>
