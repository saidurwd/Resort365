@props([
    'id',
    'title',
    'size' => null,
    'static' => false,
    'scrollable' => false,
])

{{--
    <x-modal id="add-room" :title="__('Add room')" size="lg">…<x-slot:footer>…</x-slot:footer></x-modal>
    Open with a button: data-bs-toggle="modal" data-bs-target="#add-room".
--}}
<div {{ $attributes->class(['modal', 'fade']) }} id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}-title" aria-hidden="true" @if ($static) data-bs-backdrop="static" data-bs-keyboard="false" @endif>
    <div @class(['modal-dialog', 'modal-dialog-centered', 'modal-'.$size => $size, 'modal-dialog-scrollable' => $scrollable])>
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="{{ $id }}-title">{{ $title }}</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                {{ $slot }}
            </div>
            @isset($footer)
                <div class="modal-footer">{{ $footer }}</div>
            @endisset
        </div>
    </div>
</div>
