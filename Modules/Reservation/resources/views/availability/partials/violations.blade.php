@foreach ($violations as $violation)
    <span class="badge text-bg-{{ $violation->restriction->color() }}" data-violation="{{ $violation->restriction->value }}">{{ $violation->message() }}</span>
@endforeach
