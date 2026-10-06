@props([
    'steps' => [],
    'approveUrl' => null,
    'rejectUrl' => null,
    'title' => null,
])

{{--
    Approval chain for a document. Presentational until the approval workflow engine (TODO(step-5.1)).
    steps: list of ['level', 'role', 'approver' (?string), 'status' (HasLabelAndColor), 'acted_at' (?Carbon), 'comment' (?string)].
    Pass approveUrl / rejectUrl when the current user may act on the pending step.
--}}
<x-card :title="$title ?? __('Approvals')" icon="bi-check2-square" {{ $attributes }}>
    <ol class="list-unstyled mb-0">
        @foreach ($steps as $step)
            <li @class(['d-flex gap-3', 'mb-3' => ! $loop->last])>
                <span class="badge rounded-pill text-bg-light border align-self-start">{{ $step['level'] }}</span>
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <strong>{{ $step['role'] }}</strong>
                        <x-status-badge :status="$step['status']" />
                    </div>
                    <div class="small text-body-secondary">
                        @if ($step['acted_at'])
                            {{ $step['approver'] }} · {{ $step['acted_at']->inPropertyTime()->format('d M Y H:i') }}
                        @else
                            {{ __('Awaiting decision') }}
                        @endif
                    </div>
                    @if ($step['comment'])
                        <div class="small fst-italic mt-1">“{{ $step['comment'] }}”</div>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    @if ($approveUrl || $rejectUrl)
        <x-slot:footer>
            <form method="POST" class="d-flex flex-column gap-2">
                @csrf
                <textarea name="comment" rows="2" class="form-control form-control-sm" placeholder="{{ __('Comment (optional)') }}" aria-label="{{ __('Comment') }}"></textarea>
                <div class="d-flex gap-2 justify-content-end">
                    @if ($rejectUrl)
                        <button type="submit" formaction="{{ $rejectUrl }}" class="btn btn-sm btn-outline-danger"
                                data-confirm="{{ __('Reject this document?') }}" data-confirm-variant="danger" data-confirm-button="{{ __('Reject') }}">
                            <i class="bi bi-x-lg"></i> {{ __('Reject') }}
                        </button>
                    @endif
                    @if ($approveUrl)
                        <button type="submit" formaction="{{ $approveUrl }}" class="btn btn-sm btn-success">
                            <i class="bi bi-check-lg"></i> {{ __('Approve') }}
                        </button>
                    @endif
                </div>
            </form>
        </x-slot:footer>
    @endif
</x-card>
