<x-layouts::app :title="__('Ask for leave')" :breadcrumbs="[__('HR') => null, __('Leave') => route('hr.leave.index'), __('Ask for leave') => null]">
    <div class="row"><div class="col-xl-6"><x-card :title="__('Leave request')" icon="bi-calendar2-plus">@include('hr::leave._form')</x-card></div></div>
</x-layouts::app>
