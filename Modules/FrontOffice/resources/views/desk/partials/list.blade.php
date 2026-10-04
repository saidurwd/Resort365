{{-- One front-desk list: $rows (ReservationSummary), $empty, $action ('check-in' | null), $vipLevels --}}
@if ($rows === [])
    <div class="p-3 text-body-secondary small">{{ $empty }}</div>
@else
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" data-list="{{ $list }}">
            <tbody>
                @foreach ($rows as $row)
                    <tr data-booking="{{ $row->code }}">
                        <td class="ps-3">
                            <a href="{{ route('reservation.bookings.show', $row->id) }}" class="fw-semibold">{{ $row->guestName }}</a>
                            @if (isset($vipLevels[$row->id]))<span class="badge text-bg-warning">{{ __('VIP') }} · {{ ucfirst($vipLevels[$row->id]) }}</span>@endif
                            <div class="small text-body-secondary">{{ $row->code }} · {{ implode(', ', $row->units) }} · {{ trans_choice(':count adult|:count adults', $row->adults) }}</div>
                        </td>
                        <td class="small text-nowrap">{{ \Carbon\Carbon::parse($row->checkIn)->format('d M') }} → {{ \Carbon\Carbon::parse($row->checkOut)->format('d M') }}</td>
                        <td><x-status-badge :status="$row->status" /> <x-status-badge :status="$row->paymentStatus" /></td>
                        <td class="text-end pe-3 text-nowrap">
                            @if ($action === 'check-in')
                                @can('frontoffice.checkin.perform')
                                    <a href="{{ route('frontoffice.check-in.show', $row->id) }}" class="btn btn-sm btn-success" data-check-in="{{ $row->code }}"><i class="bi bi-box-arrow-in-right"></i> {{ __('Check in') }}</a>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
