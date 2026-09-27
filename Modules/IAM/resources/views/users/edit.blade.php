<x-layouts::app :title="$user->name" :breadcrumbs="[__('Users') => route('iam.users.index'), $user->name => null]">
    <div class="row">
        <div class="col-lg-6">
            <x-card :title="__('Roles')" icon="bi-shield-check">
                <p class="small text-body-secondary">{{ $user->email }} · <x-status-badge :status="$user->status" /></p>
                <form method="POST" action="{{ route('iam.users.roles.update', $user) }}">
                    @csrf
                    @method('PUT')
                    <x-form.select name="roles" :label="__('Roles')" :options="$roles" :value="$assigned" multiple required />
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('iam.users.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('Save roles') }}</button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts::app>
