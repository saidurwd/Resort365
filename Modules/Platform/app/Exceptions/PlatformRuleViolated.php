<?php

namespace Modules\Platform\Exceptions;

use RuntimeException;

/**
 * A platform console rule was broken (Step 7.3): a plan in use deleted, a tenant suspended twice, a user who cannot be signed in as.
 * The message says which, for the admin.
 */
class PlatformRuleViolated extends RuntimeException {}
