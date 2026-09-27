<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the Laravel application. Unit tests stay framework-free
| unless a test file opts in with `uses(TestCase::class)`.
|
*/

pest()->extend(TestCase::class)->in('Feature');
