<?php

namespace Modules\Core\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\DocumentSequenceFactory;
use Modules\Core\Enums\SequenceReset;

/**
 * Numbering for one document type (optionally per property). Numbers are taken by
 * Core's DocumentNumbers service under a row lock.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $property_id
 * @property string $document_type
 * @property string $prefix
 * @property string $format
 * @property int $next_number
 * @property SequenceReset $reset
 * @property int|null $period
 */
#[UseFactory(DocumentSequenceFactory::class)]
#[Fillable(['property_id', 'document_type', 'prefix', 'format', 'next_number', 'reset', 'period'])]
class DocumentSequence extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DocumentSequenceFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
            'period' => 'integer',
            'reset' => SequenceReset::class,
        ];
    }
}
