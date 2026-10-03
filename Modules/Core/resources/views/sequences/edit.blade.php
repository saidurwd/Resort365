@php($title = __($type->label).($propertyName ? ' — '.$propertyName : ''))

<x-layouts::app :title="$title" :breadcrumbs="[__('Document numbering') => route('core.sequences.index', array_filter(['property' => $propertyId])), $title => null]">
    <div class="row">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('core.sequences.update', array_filter(['type' => $type->key, 'property' => $propertyId])) }}">
                @csrf
                @method('PUT')
                <x-card :title="__('Numbering')" icon="bi-123">
                    <div class="row">
                        <div class="col-sm-4"><x-form.input name="prefix" :label="__('Prefix')" :value="$prefix" required /></div>
                        <div class="col-sm-8"><x-form.input name="format" :label="__('Format')" :value="$format" required :help="__('Tokens: {PREFIX} {YYYY} {YY} {MM} {DD} {SEQ} {SEQ:n}')" /></div>
                        <div class="col-sm-6"><x-form.input name="next_number" type="number" min="1" :label="__('Next number')" :value="$nextNumber" required /></div>
                        <div class="col-sm-6"><x-form.select name="reset" :label="__('Restart numbering')" :options="\Modules\Core\Enums\SequenceReset::options()" :value="$reset->value" required :search="false" /></div>
                    </div>
                    <x-slot:footer>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('core.sequences.index', array_filter(['property' => $propertyId])) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('Save numbering') }}</button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
</x-layouts::app>
