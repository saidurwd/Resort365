<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;

/**
 * Central reference data (ReferenceDataSeeder). Keyed by its ISO code / identifier.
 */
#[Unguarded]
#[Table(name: 'currencies', key: 'code', keyType: 'string')]
#[WithoutIncrementing]
class Currency extends Model {}
