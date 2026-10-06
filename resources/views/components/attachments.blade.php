@props([
    'subject' => null,
    'items' => [],
    'uploadUrl' => null,
    'accept' => null,
    'title' => null,
])

{{--
    Files attached to a record. Pass `:subject="$model"` (a model using App\Support\Attachments\HasAttachments):
    the list, upload and delete go through Core's authorized routes, following the record's policy
    (`view` to download, `update` to upload/delete).
    Presentational mode (no subject): items = list of ['name', 'url', 'size', 'uploaded_by', 'uploaded_at'], optional 'delete_url'.
--}}
@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $formatSize = function (int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return round($bytes / (1024 ** $power), $power ? 1 : 0).' '.$units[$power];
    };

    if ($subject instanceof \Spatie\MediaLibrary\HasMedia) {
        $canUpdate = auth()->user()?->can('update', $subject) ?? false;
        $items = $subject->getMedia(\App\Support\Attachments\Attachments::COLLECTION)->map(fn ($media) => [
            'name' => $media->file_name,
            'url' => route('core.attachments.show', $media),
            'size' => $media->size,
            'uploaded_by' => $media->getCustomProperty('uploaded_by_name') ?? '',
            'uploaded_at' => $media->created_at,
            'delete_url' => $canUpdate ? route('core.attachments.destroy', $media) : null,
        ])->all();
        $uploadUrl = $canUpdate ? route('core.attachments.store', ['type' => $subject->getMorphClass(), 'id' => $subject->getKey()]) : null;
        $accept ??= '.'.implode(',.', (array) config('attachments.extensions'));
    }
@endphp

<x-card :title="$title ?? __('Attachments')" icon="bi-paperclip" body-class="p-0" {{ $attributes }}>
    @forelse ($items as $item)
        <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom" data-attachment>
            <i class="bi bi-file-earmark fs-4 text-body-secondary"></i>
            <div class="flex-grow-1 min-w-0">
                <a href="{{ $item['url'] }}" class="d-block text-truncate" target="_blank" rel="noopener">{{ $item['name'] }}</a>
                <div class="small text-body-secondary">
                    {{ $formatSize((int) $item['size']) }}@if ($item['uploaded_by']) · {{ $item['uploaded_by'] }}@endif · {{ $item['uploaded_at']?->inPropertyTime()->format('d M Y H:i') }}
                </div>
            </div>
            @if (! empty($item['delete_url']))
                <x-confirm-delete :action="$item['delete_url']" icon-only :title="__('Delete this file?')" />
            @endif
        </div>
    @empty
        <x-empty-state icon="bi-paperclip" :title="__('No files attached')" class="py-4" />
    @endforelse

    @if ($uploadUrl)
        <x-slot:footer>
            <form method="POST" action="{{ $uploadUrl }}" enctype="multipart/form-data" class="d-flex gap-2">
                @csrf
                <input type="file" name="file" @class(['form-control form-control-sm', 'is-invalid' => $errors->has('file')]) @if ($accept) accept="{{ $accept }}" @endif required aria-label="{{ __('Choose file') }}">
                <button type="submit" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-upload"></i> {{ __('Upload') }}</button>
            </form>
            @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </x-slot:footer>
    @endif
</x-card>
