<?php

namespace Modules\Accounting\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Accounting\Models\JournalEntry;

/**
 * Journal entries: accounting.journal.view to look; .create to write and discard drafts; .post to post;
 * .reverse to reverse a posted entry.
 */
class JournalEntryPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accounting.journal.view');
    }

    public function view(Authenticatable&Authorizable $user, JournalEntry $entry): bool
    {
        return $user->can('accounting.journal.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accounting.journal.create');
    }

    public function update(Authenticatable&Authorizable $user, JournalEntry $entry): bool
    {
        return $user->can('accounting.journal.create');
    }

    public function post(Authenticatable&Authorizable $user, JournalEntry $entry): bool
    {
        return $user->can('accounting.journal.post');
    }

    public function reverse(Authenticatable&Authorizable $user, JournalEntry $entry): bool
    {
        return $user->can('accounting.journal.reverse');
    }
}
