<?php

use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestView;
use Illuminate\View\FileViewFinder;
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

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| Function versions of Laravel's view-testing helpers, so tests stay
| readable and fully typed for Larastan (Pest closures don't expose $this).
|
*/

/**
 * Render a Blade string (same as Laravel's InteractsWithViews::blade()).
 *
 * @param  array<string, mixed>  $data
 */
function blade(string $template, array $data = []): TestView
{
    $directory = sys_get_temp_dir();
    $finder = View::getFinder();

    if ($finder instanceof FileViewFinder && ! in_array($directory, $finder->getPaths(), true)) {
        $finder->addLocation($directory);
    }

    $name = pathinfo((string) tempnam($directory, 'laravel-blade'), PATHINFO_FILENAME);
    file_put_contents($directory.'/'.$name.'.blade.php', $template);

    return new TestView(view($name, $data));
}

/**
 * Share validation errors with every view (same as InteractsWithViews::withViewErrors()).
 *
 * @param  array<string, string|list<string>>  $errors
 */
function withViewErrors(array $errors, string $bag = 'default'): void
{
    View::share('errors', (new ViewErrorBag)->put($bag, new MessageBag($errors)));
}
