{{-- The buttons for a task's next steps the user may take. --}}
@php
    $labels = ['start' => [__('Start'), 'bi-play', 'outline-primary'], 'finish' => [__('Done'), 'bi-check2', 'success'],
        'pass' => [__('Pass'), 'bi-patch-check', 'primary'], 'fail' => [__('Fail'), 'bi-x-circle', 'outline-danger'], 'skip' => [__('Skip'), 'bi-skip-forward', 'outline-secondary']];
@endphp
<div class="d-flex gap-1 justify-content-end">
    @foreach ($transitions->available($task->status) as $step)
        @can('step', [$task, $step])
            <form method="POST" action="{{ route('housekeeping.tasks.step', [$task, $step]) }}" @if ($step === 'fail') data-confirm="{{ __('Fail the inspection?') }}" @endif>
                @csrf
                <button type="submit" class="btn btn-sm btn-{{ $labels[$step][2] }}" data-step="{{ $step }}"><i class="bi {{ $labels[$step][1] }}"></i> {{ $labels[$step][0] }}</button>
            </form>
        @endcan
    @endforeach
</div>
