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
#[Table(name: 'countries', key: 'code', keyType: 'string')]
#[WithoutIncrementing]
class Country extends Model {}
