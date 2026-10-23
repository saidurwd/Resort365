<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use Modules\IAM\Models\ApiToken;

/**
 * Revokes an API token at once (Step 8.1): the next request with it is a 401. The audit log keeps the deletion.
 */
class RevokeApiToken extends Action
{
    public function handle(ApiToken $token): void
    {
        $token->delete();
    }
}
