<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Madad\Sdk\Jobs\DeleteProductJob;
use Madad\Sdk\Jobs\SyncProductJob;
use Madad\Sdk\MadadClient;
use Madad\Sdk\Tests\Fixtures\Product;

beforeEach(function () {
    config()->set('madad.enabled', false); // create fixtures without firing the trait
    config()->set('madad.product.external_id', 'sku');
    config()->set('madad.product.map', ['name' => 'title', 'price' => 'amount']);
});

it('upserts the current model state on handle', function () {
    Http::fake(['*/partner/products' => Http::response(['status' => 'created'], 201)]);

    $p = Product::create(['sku' => 'SKU-1', 'title' => 'Cement', 'amount' => 100]);

    (new SyncProductJob(Product::class, $p->id))->handle(app(MadadClient::class));

    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && $r['external_id'] === 'SKU-1'
        && $r['name'] === 'Cement');
});

it('re-fetches the model so the latest state is pushed', function () {
    Http::fake(['*/partner/products' => Http::response(['status' => 'created'], 201)]);

    $p = Product::create(['sku' => 'SKU-1', 'title' => 'Old']);
    $p->update(['title' => 'New']); // changed after the (hypothetical) dispatch

    (new SyncProductJob(Product::class, $p->id))->handle(app(MadadClient::class));

    Http::assertSent(fn ($r) => $r['name'] === 'New');
});

it('no-ops when the model was deleted before the job ran', function () {
    Http::fake();

    (new SyncProductJob(Product::class, 99999))->handle(app(MadadClient::class));

    Http::assertNothingSent();
});

it('dispatches synchronously when the queue is disabled', function () {
    config()->set('madad.queue.enabled', false);
    Bus::fake();

    SyncProductJob::dispatchFor(Product::class, 1);

    Bus::assertDispatchedSync(SyncProductJob::class);
});

it('queues on the configured queue when enabled', function () {
    config()->set('madad.queue.enabled', true);
    config()->set('madad.queue.name', 'syncs');
    Bus::fake();

    SyncProductJob::dispatchFor(Product::class, 1);

    Bus::assertDispatched(SyncProductJob::class, fn ($job) => $job->queue === 'syncs');
    Bus::assertNotDispatchedSync(SyncProductJob::class);
});

it('swallows a 404 when deleting an already-gone product', function () {
    Http::fake(['*/partner/products/*' => Http::response(
        ['error' => ['code' => 'NOT_FOUND']], 404
    )]);

    (new DeleteProductJob('SKU-GONE'))->handle(app(MadadClient::class));

    Http::assertSentCount(1); // did not throw
});

it('rethrows non-404 errors on delete so the job retries', function () {
    Http::fake(['*/partner/products/*' => Http::response(
        ['error' => ['code' => 'SERVER']], 500
    )]);

    expect(fn () => (new DeleteProductJob('SKU-1'))->handle(app(MadadClient::class)))
        ->toThrow(Madad\Sdk\Exceptions\MadadException::class);
});
