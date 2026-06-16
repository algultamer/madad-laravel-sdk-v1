<?php

namespace Madad\Sdk\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;

/**
 * Variant that syncs only its "building" branch — exercising both the per-row
 * gate (shouldSyncToMadad) and the query-level pre-filter (scopeMadadSyncable)
 * that madad:sync-all uses on large catalogs.
 */
class ScopedProduct extends Product
{
    protected $table = 'products';

    public function shouldSyncToMadad(): bool
    {
        return $this->branch === 'building';
    }

    public function scopeMadadSyncable(Builder $query): Builder
    {
        return $query->where('branch', 'building');
    }
}
