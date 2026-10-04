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
                            <a href="{{ route('reservation.bookings.show', $row->id) }}" class="fw-semibold">{{ $row->groupName ?? $row->guestName }}</a>
                            @if ($row->groupName)<span class="badge text-bg-info"><i class="bi bi-people"></i> {{ __('Group') }}</span>@endif
                            @if ($row->itemsTotal > 1 && $row->itemsCheckedIn > 0 && $row->itemsCheckedIn < $row->itemsTotal)<span class="badge text-bg-warning" data-progress>{{ __(':in of :total rooms in', ['in' => $row->itemsCheckedIn, 'total' => $row->itemsTotal]) }}</span>@endif
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
                            @elseif ($action === 'in-house')
                                @if ($row->itemsCheckedIn < $row->itemsTotal)
                                    @can('frontoffice.checkin.perform')
                                        <a href="{{ route('frontoffice.check-in.show', $row->id) }}" class="btn btn-sm btn-success" data-check-in="{{ $row->code }}"><i class="bi bi-box-arrow-in-right"></i> {{ __('Check in the rest') }}</a>
                                    @endcan
                                @endif
                                @can('frontoffice.stay.change')
                                    <a href="{{ route('frontoffice.stay.show', $row->id) }}" class="btn btn-sm btn-outline-secondary" data-stay-change="{{ $row->code }}"><i class="bi bi-arrow-left-right"></i> {{ __('Stay changes') }}</a>
                                @endcan
                            @elseif ($action === 'check-out')
                                @can('frontoffice.checkout.perform')
                                    <a href="{{ route('frontoffice.check-out.show', $row->id) }}" class="btn btn-sm btn-warning" data-check-out="{{ $row->code }}"><i class="bi bi-box-arrow-right"></i> {{ __('Check out') }}</a>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
