<?php

use Illuminate\Support\Facades\Bus;
use Madad\Sdk\Jobs\DeleteProductJob;
use Madad\Sdk\Jobs\SyncProductJob;
use Madad\Sdk\Tests\Fixtures\Product;
use Madad\Sdk\Tests\Fixtures\ScopedProduct;

beforeEach(function () {
    config()->set('madad.product.external_id', 'sku');
});

it('dispatches a sync job on create and update', function () {
    Bus::fake();

    $p = Product::create(['sku' => 'SKU-1', 'title' => 'X']);
    Bus::assertDispatched(SyncProductJob::class, 1);

    $p->update(['title' => 'Y']);
    Bus::assertDispatched(SyncProductJob::class, 2);
});

it('dispatches a delete job with the external_id on delete', function () {
    Bus::fake();

    $p = Product::create(['sku' => 'SKU-7', 'title' => 'X']);
    $p->delete();

    Bus::assertDispatched(DeleteProductJob::class, fn ($job) => $job->externalId === 'SKU-7');
});

it('re-syncs on restore of a soft-deleted record', function () {
    Bus::fake();

    $p = Product::create(['sku' => 'SKU-8', 'title' => 'X']);
    $p->delete();
    $p->restore();

    Bus::assertDispatched(SyncProductJob::class, fn ($job) => $job->modelKey === $p->id);
});

it('does nothing when syncing is globally disabled', function () {
    config()->set('madad.enabled', false);
    Bus::fake();

    $p = Product::create(['sku' => 'SKU-1', 'title' => 'X']);
    $p->delete();

    Bus::assertNothingDispatched();
});

it('honours shouldSyncToMadad — out-of-scope rows are not pushed', function () {
    Bus::fake();

    ScopedProduct::create(['sku' => 'IN', 'title' => 'X', 'branch' => 'building']);
    ScopedProduct::create(['sku' => 'OUT', 'title' => 'Y', 'branch' => 'cleaning']);

    Bus::assertDispatched(SyncProductJob::class, 1);
});

it('builds the external_id from the configured column', function () {
    config()->set('madad.enabled', false);
    $p = Product::create(['sku' => 'ABC', 'title' => 'X']);

    expect($p->madadExternalId())->toBe('ABC');
});
