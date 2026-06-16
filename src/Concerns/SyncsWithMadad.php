<?php

namespace Madad\Sdk\Concerns;

use Madad\Sdk\Jobs\DeleteProductJob;
use Madad\Sdk\Jobs\SyncProductJob;
use Madad\Sdk\Support\PayloadBuilder;

/**
 * Add to your product model. On create/update it pushes to Madad; on delete it
 * removes the product. That's it — the config map does the field translation.
 */
trait SyncsWithMadad
{
    public static function bootSyncsWithMadad(): void
    {
        static::created(fn ($model) => $model->madadSync());
        static::updated(fn ($model) => $model->madadSync());
        static::deleted(fn ($model) => $model->madadDelete());

        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($model) => $model->madadSync());
        }
    }

    /** Your stable id → Madad's external_id. */
    public function madadExternalId(): string
    {
        return (string) data_get($this, config('madad.product.external_id', 'id'));
    }

    /** The full payload sent to Madad. Override to fully customize. */
    public function toMadadPayload(): array
    {
        return PayloadBuilder::build($this, config('madad.product', []));
    }

    public function madadSync(): void
    {
        if (config('madad.enabled', true)) {
            SyncProductJob::dispatchFor(static::class, $this->getKey());
        }
    }

    public function madadDelete(): void
    {
        if (config('madad.enabled', true)) {
            DeleteProductJob::dispatchExternal($this->madadExternalId());
        }
    }
}
