<dl class="row mb-0 small" data-figures>
    <dt class="col-8">{{ __('Statement balance') }}</dt><dd class="col-4 text-end font-monospace">{{ number_format((float) $figures['statement_balance'], 2) }}</dd>
    <dt class="col-8 fw-normal">+ {{ __('Deposits in transit') }}</dt><dd class="col-4 text-end font-monospace">{{ number_format((float) $figures['deposits_in_transit'], 2) }}</dd>
    <dt class="col-8 fw-normal">− {{ __('Payments outstanding') }}</dt><dd class="col-4 text-end font-monospace">{{ number_format((float) $figures['outstanding_payments'], 2) }}</dd>
    <dt class="col-8 border-top pt-1">{{ __('Adjusted bank balance') }}</dt><dd class="col-4 text-end font-monospace border-top pt-1" data-adjusted-bank>{{ number_format((float) $figures['adjusted_bank'], 2) }}</dd>
    <dt class="col-8 mt-2">{{ __('Ledger balance') }}</dt><dd class="col-4 text-end font-monospace mt-2">{{ number_format((float) $figures['book_balance'], 2) }}</dd>
    <dt class="col-8 fw-normal">+ {{ __('Bank items not in the books') }}</dt><dd class="col-4 text-end font-monospace">{{ number_format((float) $figures['bank_items_not_in_books'], 2) }}</dd>
    <dt class="col-8 border-top pt-1">{{ __('Adjusted book balance') }}</dt><dd class="col-4 text-end font-monospace border-top pt-1" data-adjusted-book>{{ number_format((float) $figures['adjusted_book'], 2) }}</dd>
    <dt class="col-8 mt-2">{{ __('Difference') }}</dt><dd class="col-4 text-end font-monospace mt-2 fw-bold {{ $figures['difference'] === '0.00' ? 'text-success' : 'text-danger' }}" data-difference>{{ number_format((float) $figures['difference'], 2) }}</dd>
</dl>
