<?php

/*
| Zero-downtime deployment (Step 7.5, docs/OPERATIONS.md §Deploy) with Deployer: each release goes to its own directory under
| {{deploy_path}}/releases, shares .env and storage, and goes live by switching the `current` symlink at once. Assets are built
| where the deploy runs (GitHub Actions or your machine) and uploaded, so servers need no Node. After the switch, PHP-FPM reloads and
| Horizon, Pulse and Reverb restart. `dep rollback <stage>` goes back to the previous release (migrations are not reversed).
|
| Hosts come from the environment: DEPLOY_PRODUCTION_HOST, DEPLOY_STAGING_HOST (and optionally DEPLOY_USER, DEPLOY_PATH,
| DEPLOY_PHP_FPM). Run: vendor/bin/dep deploy staging
*/

namespace Deployer;

require 'recipe/laravel.php';

set('application', 'resort365');
set('repository', getenv('DEPLOY_REPOSITORY') ?: 'git@github.com:resort365/resort365.git');
set('keep_releases', 5);
set('writable_mode', 'chmod');
set('php_fpm_service', getenv('DEPLOY_PHP_FPM') ?: 'php8.4-fpm');

foreach (['production' => 'main', 'staging' => 'main'] as $stage => $branch) {
    $hostname = getenv('DEPLOY_'.strtoupper($stage).'_HOST');

    if ($hostname !== false && $hostname !== '') {
        host($stage)
            ->set('hostname', $hostname)
            ->set('remote_user', getenv('DEPLOY_USER') ?: 'deploy')
            ->set('deploy_path', getenv('DEPLOY_PATH') ?: '/var/www/resort365')
            ->set('branch', getenv('DEPLOY_BRANCH') ?: $branch)
            ->set('labels', ['stage' => $stage]);
    }
}

desc('Builds the front-end assets locally');
task('build:assets', function (): void {
    runLocally('npm ci && npm run build', ['timeout' => 600]);
})->once();

desc('Uploads the built assets to the release');
task('upload:assets', function (): void {
    upload('public/build/', '{{release_path}}/public/build/');
});

desc('Reloads PHP-FPM so the new release is served without dropping requests');
task('php-fpm:reload', function (): void {
    run('sudo systemctl reload {{php_fpm_service}}');
});

desc('Restarts the long-running processes on the new code (Supervisor starts them again)');
task('processes:restart', function (): void {
    run('{{bin/php}} {{current_path}}/artisan horizon:terminate');
    run('{{bin/php}} {{current_path}}/artisan pulse:restart');
    run('{{bin/php}} {{current_path}}/artisan reverb:restart');
    run('{{bin/php}} {{current_path}}/artisan schedule:interrupt');
});

task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'upload:assets',
    'artisan:storage:link',
    'artisan:optimize',
    'artisan:migrate',
    'deploy:publish',
    'php-fpm:reload',
    'processes:restart',
]);

before('deploy', 'build:assets');
after('rollback', 'php-fpm:reload');
after('rollback', 'processes:restart');
after('deploy:failed', 'deploy:unlock');
