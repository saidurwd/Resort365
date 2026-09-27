<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Core\Models\Media;

/**
 * Database rows only (no file on disk); for tests of scoping and listing.
 *
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_type' => 'test.subject',
            'model_id' => fake()->numberBetween(1, 1_000_000),
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'attachments',
            'name' => 'document',
            'file_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'attachments',
            'size' => 1024,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ];
    }
}
