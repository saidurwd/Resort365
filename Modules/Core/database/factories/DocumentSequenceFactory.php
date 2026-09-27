<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Enums\SequenceReset;
use Modules\Core\Models\DocumentSequence;

/**
 * @extends Factory<DocumentSequence>
 */
class DocumentSequenceFactory extends Factory
{
    protected $model = DocumentSequence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type' => fake()->unique()->slug(2),
            'prefix' => 'DOC',
            'format' => '{PREFIX}-{YYYY}-{SEQ:5}',
            'next_number' => 1,
            'reset' => SequenceReset::Yearly,
        ];
    }
}
