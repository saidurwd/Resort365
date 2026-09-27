@props([
    'items' => [],
    'uploadUrl' => null,
    'accept' => null,
    'title' => null,
])

{{--
    File list with an optional upload form. Presentational until the attachments service (TODO(step-0.7)).
    items: list of ['name', 'url', 'size' (bytes), 'uploaded_by', 'uploaded_at' (Carbon)], optional 'delete_url'.
--}}
@php
    $formatSize = function (int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return round($bytes / (1024 ** $power), $power ? 1 : 0).' '.$units[$power];
    };
@endphp

<x-card :title="$title ?? __('Attachments')" icon="bi-paperclip" body-class="p-0" {{ $attributes }}>
    @forelse ($items as $item)
        <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
            <i class="bi bi-file-earmark fs-4 text-body-secondary"></i>
            <div class="flex-grow-1 min-w-0">
                <a href="{{ $item['url'] }}" class="d-block text-truncate">{{ $item['name'] }}</a>
                <div class="small text-body-secondary">
                    {{ $formatSize((int) $item['size']) }} · {{ $item['uploaded_by'] }} · {{ $item['uploaded_at']->format('d M Y H:i') }}
                </div>
            </div>
            @isset($item['delete_url'])
                <x-confirm-delete :action="$item['delete_url']" icon-only :title="__('Delete this file?')" />
            @endisset
        </div>
    @empty
        <x-empty-state icon="bi-paperclip" :title="__('No files attached')" class="py-4" />
    @endforelse

    @if ($uploadUrl)
        <x-slot:footer>
            <form method="POST" action="{{ $uploadUrl }}" enctype="multipart/form-data" class="d-flex gap-2">
                @csrf
                <input type="file" name="file" class="form-control form-control-sm" @if ($accept) accept="{{ $accept }}" @endif required aria-label="{{ __('Choose file') }}">
                <button type="submit" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-upload"></i> {{ __('Upload') }}</button>
            </form>
        </x-slot:footer>
    @endif
</x-card>
