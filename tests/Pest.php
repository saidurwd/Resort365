<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests (application and modules) boot the Laravel application.
| Unit and architecture tests stay framework-free unless a test file opts
| in with `uses(TestCase::class)`.
|
*/

pest()->extend(TestCase::class)->in('Feature', '../Modules/*/tests/Feature');
