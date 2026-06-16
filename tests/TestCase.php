<?php

namespace Madad\Sdk\Tests;

use Illuminate\Support\Facades\Schema;
use Madad\Sdk\MadadServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
    }

    protected function getPackageProviders($app): array
    {
        return [MadadServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');

        $app['config']->set('madad.api_key', 'test-key');
        $app['config']->set('madad.enabled', true);
        $app['config']->set('madad.queue.enabled', true);
    }

    /** A small product/spec/image schema for the fixture models. */
    protected function createTestSchema(): void
    {
        Schema::create('products', function ($table) {
            $table->id();
            $table->string('sku')->nullable();
            $table->string('title')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('branch')->nullable();
            $table->string('cat_code')->nullable();
            $table->string('cat_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('specs', function ($table) {
            $table->id();
            $table->foreignId('product_id');
            $table->string('label');
            $table->string('val')->nullable();
        });

        Schema::create('photos', function ($table) {
            $table->id();
            $table->foreignId('product_id');
            $table->string('src');
            $table->integer('position')->default(0);
        });
    }
}
