<?php

namespace Modules\Accounting\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Accounting\Models\Voucher;

/**
 * Vouchers: accounting.voucher.view to look and download attachments; .create to record one and attach files;
 * .void to void a posted voucher.
 */
class VoucherPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accounting.voucher.view');
    }

    public function view(Authenticatable&Authorizable $user, Voucher $voucher): bool
    {
        return $user->can('accounting.voucher.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accounting.voucher.create');
    }

    public function update(Authenticatable&Authorizable $user, Voucher $voucher): bool
    {
        return $user->can('accounting.voucher.create');
    }

    public function void(Authenticatable&Authorizable $user, Voucher $voucher): bool
    {
        return $user->can('accounting.voucher.void');
    }
}
