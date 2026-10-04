@if ($item->status === \Modules\Housekeeping\Enums\LostItemStatus::Stored)
    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#close-item"
        data-modal-action="{{ route('housekeeping.lost-found.close', $item) }}"><i class="bi bi-box-arrow-right"></i> {{ __('Return / dispose') }}</button>
@endif
