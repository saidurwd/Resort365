{{--
    Manager PIN approval (ARCHITECTURE §3.3 rule 6), inside a form with x-data="managerApproval(...)":
    the manager picks their name and enters their PIN; the approval id is put in the form's
    approval_id field and the form is sent again. The server checks everything again.
--}}
<div class="pos-approval" x-show="asking" x-cloak data-approval-panel>
    <h2 class="h5"><i class="bi bi-shield-lock"></i> {{ __('Manager approval') }}</h2>
    <p class="small text-body-secondary mb-2" x-text="why"></p>
    @if ($approvers === [])
        <p class="text-danger mb-0">{{ __('No manager with a POS PIN works in this outlet.') }}</p>
    @else
        <div class="d-flex flex-wrap gap-2 mb-2">
            @foreach ($approvers as $id => $name)
                <button type="button" class="btn pos-btn" :class="managerId == {{ $id }} ? 'btn-warning' : 'btn-outline-warning'" @click="managerId = {{ $id }}" data-approver="{{ $id }}">{{ $name }}</button>
            @endforeach
        </div>
        <div class="d-flex gap-2 align-items-center">
            <label class="visually-hidden" for="approval-pin">{{ __('Manager PIN') }}</label>
            <input type="password" inputmode="numeric" maxlength="6" id="approval-pin" class="form-control form-control-lg pos-approval__pin" x-model="pin" placeholder="{{ __('PIN') }}" autocomplete="off">
            <button type="button" class="btn btn-warning pos-btn" @click="approve()" :disabled="! managerId || pin.length < 4 || busy" data-approve>{{ __('Approve') }}</button>
            <button type="button" class="btn btn-outline-secondary pos-btn" @click="asking = false">{{ __('Cancel') }}</button>
        </div>
        <p class="text-danger small mt-2 mb-0" x-show="error" x-text="error" data-approval-error></p>
    @endif
</div>
