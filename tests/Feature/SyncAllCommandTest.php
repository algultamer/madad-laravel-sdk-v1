<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Madad\Sdk\Jobs\SyncProductJob;
use Madad\Sdk\Tests\Fixtures\Product;
use Madad\Sdk\Tests\Fixtures\ScopedProduct;

beforeEach(function () {
    // Disabled so seeding fixtures doesn't fire the trait — we only want to
    // observe what the command itself dispatches (the command ignores this flag).
    config()->set('madad.enabled', false);
    config()->set('madad.product.external_id', 'sku');
});

it('fails when no model is configured', function () {
    config()->set('madad.product.model', null);

    $this->artisan('madad:sync-all')
        ->expectsOutputToContain('Set madad.product.model')
        ->assertExitCode(1);
});

it('dispatches one sync job per product', function () {
    config()->set('madad.product.model', Product::class);
    Bus::fake();

    Product::create(['sku' => 'A', 'title' => 'X']);
    Product::create(['sku' => 'B', 'title' => 'Y']);

    $this->artisan('madad:sync-all')->assertExitCode(0);

    Bus::assertDispatched(SyncProductJob::class, 2);
});

it('applies the scope pre-filter and the per-row gate', function () {
    config()->set('madad.product.model', ScopedProduct::class);
    Bus::fake();

    ScopedProduct::create(['sku' => 'IN', 'title' => 'X', 'branch' => 'building']);
    ScopedProduct::create(['sku' => 'OUT', 'title' => 'Y', 'branch' => 'cleaning']);

    $this->artisan('madad:sync-all')
        ->expectsOutputToContain('Synced 1')
        ->assertExitCode(0);

    Bus::assertDispatched(SyncProductJob::class, 1);
});

it('refuses to run while another run holds the lock', function () {
    config()->set('madad.product.model', Product::class);

    $lock = Cache::lock('madad:sync-all', 600);
    $lock->get();

    try {
        $this->artisan('madad:sync-all')
            ->expectsOutputToContain('already in progress')
            ->assertExitCode(1);
    } finally {
        $lock->release();
    }
});
