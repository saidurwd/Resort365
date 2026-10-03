@props([
    'subject' => null,
    'items' => [],
    'uploadUrl' => null,
    'title' => null,
])

{{--
    Photo gallery of a record. Pass `:subject="$model"` (a model using App\Support\Attachments\HasPhotos):
    thumbnails, upload and delete go through Core's authorized attachment routes, following the
    record's policy (`view` to see, `update` to upload/delete).
    Presentational mode (no subject): items = list of ['name', 'url', 'thumb_url'], optional 'delete_url'.
--}}
@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;

    if ($subject instanceof \Spatie\MediaLibrary\HasMedia) {
        $canUpdate = auth()->user()?->can('update', $subject) ?? false;
        $items = $subject->getMedia(\App\Support\Attachments\Attachments::PHOTOS)->map(fn ($media) => [
            'name' => $media->file_name,
            'url' => route('core.attachments.show', $media),
            'thumb_url' => route('core.attachments.show', ['media' => $media, 'conversion' => \App\Support\Attachments\Attachments::THUMB]),
            'delete_url' => $canUpdate ? route('core.attachments.destroy', $media) : null,
        ])->all();
        $uploadUrl = $canUpdate ? route('core.attachments.store', ['type' => $subject->getMorphClass(), 'id' => $subject->getKey()]) : null;
    }
@endphp

<x-card :title="$title ?? __('Photos')" icon="bi-images" {{ $attributes }}>
    @if ($items === [])
        <x-empty-state icon="bi-images" :title="__('No photos yet')" class="py-3" />
    @else
        <div class="photo-gallery" data-photo-gallery>
            @foreach ($items as $item)
                <div class="photo-gallery-item" data-photo>
                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener">
                        <img src="{{ $item['thumb_url'] }}" alt="{{ $item['name'] }}" loading="lazy" class="border">
                    </a>
                    @if (! empty($item['delete_url']))
                        <x-confirm-delete :action="$item['delete_url']" icon-only :title="__('Delete this photo?')" class="btn-light" />
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($uploadUrl)
        <x-slot:footer>
            <form method="POST" action="{{ $uploadUrl }}" enctype="multipart/form-data" class="d-flex gap-2">
                @csrf
                <input type="hidden" name="collection" value="{{ \App\Support\Attachments\Attachments::PHOTOS }}">
                <input type="file" name="file" accept="image/jpeg,image/png,image/webp" @class(['form-control form-control-sm', 'is-invalid' => $errors->has('file')]) required aria-label="{{ __('Choose photo') }}">
                <button type="submit" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-upload"></i> {{ __('Add photo') }}</button>
            </form>
            @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </x-slot:footer>
    @endif
</x-card>
