<?php

namespace Tests\Fixtures;

use App\Support\Actions\Action;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StoreCacheValues extends Action
{
    /**
     * Writes two rows in one transaction, optionally failing after the first.
     */
    public function handle(bool $failHalfway = false): int
    {
        return $this->transaction(function () use ($failHalfway): int {
            DB::table('cache')->insert(['key' => 'first', 'value' => '1', 'expiration' => 0]);

            throw_if($failHalfway, RuntimeException::class, 'Failed halfway');

            DB::table('cache')->insert(['key' => 'second', 'value' => '2', 'expiration' => 0]);

            return 2;
        });
    }
}
