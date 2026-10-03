<?php

namespace Tests\Support;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use FilesystemIterator;
use Illuminate\Database\Eloquent\Model;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Fixtures\Tenancy\IsolationProbe;
use Tests\Fixtures\Tenancy\PropertyProbe;

/**
 * The tenant-isolation dataset: every model using BelongsToTenant in app/Models and
 * Modules/<Name>/app/Models (found automatically), plus test fixtures.
 */
class TenantModels
{
    /**
     * Fixture models that always run through the harness.
     *
     * @var list<class-string<Model>>
     */
    private const array FIXTURES = [IsolationProbe::class];

    /**
     * Property-level fixture models that always run through the property harness.
     *
     * @var list<class-string<Model>>
     */
    private const array PROPERTY_FIXTURES = [PropertyProbe::class];

    /**
     * The property-access dataset: every model using BelongsToProperty, plus fixtures.
     *
     * @return list<class-string<Model>>
     */
    public static function propertyLevel(): array
    {
        $models = array_filter(self::discover(), fn (string $model): bool => in_array(BelongsToProperty::class, class_uses_recursive($model), true));

        return array_values(array_unique([...$models, ...self::PROPERTY_FIXTURES]));
    }

    /**
     * @return list<class-string<Model>>
     */
    public static function all(): array
    {
        return array_values(array_unique([...self::discover(), ...self::FIXTURES]));
    }

    /**
     * @return list<class-string<Model>>
     */
    public static function discover(): array
    {
        $root = dirname(__DIR__, 2);
        $sources = ['App\\Models\\' => $root.'/app/Models'];

        foreach (glob($root.'/Modules/*/app/Models', GLOB_ONLYDIR) ?: [] as $directory) {
            $sources['Modules\\'.basename(dirname($directory, 2)).'\\Models\\'] = $directory;
        }

        $models = [];

        foreach ($sources as $namespace => $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = substr($file->getPathname(), strlen($directory) + 1, -4);
                $class = $namespace.str_replace('/', '\\', $relative);

                if (class_exists($class) && is_subclass_of($class, Model::class) && in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
                    $models[] = $class;
                }
            }
        }

        sort($models);

        return $models;
    }
}
