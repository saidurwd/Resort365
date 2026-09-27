<x-layouts::app :title="__('Profile')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Profile') => null]">
    <div class="row">
        <div class="col-xl-6">
            @include('iam::profile.partials.details')
            @include('iam::profile.partials.preferences')
            @include('iam::profile.partials.password')
        </div>
        <div class="col-xl-6">
            @include('iam::profile.partials.two-factor')
            @include('iam::profile.partials.sign-ins')
        </div>
    </div>
</x-layouts::app>
