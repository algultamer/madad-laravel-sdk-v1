<?php

use Madad\Sdk\Support\PayloadBuilder;
use Madad\Sdk\Tests\Fixtures\Photo;
use Madad\Sdk\Tests\Fixtures\Product;
use Madad\Sdk\Tests\Fixtures\Spec;

beforeEach(function () {
    config()->set('madad.enabled', false); // don't auto-sync while seeding fixtures
});

/** The mapping a typical partner would configure. */
function fullMap(): array
{
    return [
        'external_id' => 'sku',
        'version' => 'updated_at',
        'map' => [
            'name' => 'title',
            'price' => 'amount',
        ],
        'category' => [
            'external_id' => 'cat_code',
            'path' => ['cat_name'],
        ],
        'specifications' => ['relation' => 'specs', 'key' => 'label', 'value' => 'val'],
        'images' => ['relation' => 'photos', 'url' => 'src', 'sort' => 'position'],
    ];
}

it('maps flat fields and uses external_id', function () {
    $p = Product::create(['sku' => 'SKU-1', 'title' => 'Cement', 'amount' => 75000]);

    $payload = PayloadBuilder::build($p, fullMap());

    expect($payload['external_id'])->toBe('SKU-1')
        ->and($payload['name'])->toBe('Cement')
        ->and($payload['price'])->toBe(75000.0);
});

it('omits fields whose mapped value is null', function () {
    $p = Product::create(['sku' => 'SKU-2', 'title' => null, 'amount' => null]);

    $payload = PayloadBuilder::build($p, fullMap());

    expect($payload)->not->toHaveKey('name')
        ->and($payload)->not->toHaveKey('price');
});

it('converts a date version column to a unix timestamp', function () {
    $p = Product::create(['sku' => 'SKU-3', 'title' => 'X']);

    $payload = PayloadBuilder::build($p, fullMap());

    expect($payload['version'])->toBe($p->updated_at->getTimestamp());
});

it('builds the category from external_id and path', function () {
    $p = Product::create(['sku' => 'SKU-4', 'title' => 'X', 'cat_code' => 'CEM', 'cat_name' => 'Cement']);

    $payload = PayloadBuilder::build($p, fullMap());

    expect($payload['category'])->toBe(['external_id' => 'CEM', 'path' => ['Cement']]);
});

it('omits the category entirely when no parts resolve', function () {
    $p = Product::create(['sku' => 'SKU-5', 'title' => 'X']);

    $payload = PayloadBuilder::build($p, fullMap());

    expect($payload)->not->toHaveKey('category');
});

it('builds specifications from a relation', function () {
    $p = Product::create(['sku' => 'SKU-6', 'title' => 'X']);
    Spec::create(['product_id' => $p->id, 'label' => 'Strength', 'val' => '42.5']);
    Spec::create(['product_id' => $p->id, 'label' => '', 'val' => 'skipme']); // empty key dropped

    $payload = PayloadBuilder::build($p->fresh(), fullMap());

    expect($payload['specifications'])->toBe([
        ['key' => 'Strength', 'value' => '42.5'],
    ]);
});

it('builds images with sort_order from a relation', function () {
    $p = Product::create(['sku' => 'SKU-7', 'title' => 'X']);
    Photo::create(['product_id' => $p->id, 'src' => 'https://x/1.jpg', 'position' => 2]);

    $payload = PayloadBuilder::build($p->fresh(), fullMap());

    expect($payload['images'])->toBe([
        ['url' => 'https://x/1.jpg', 'sort_order' => 2],
    ]);
});

it('prefers a madad{Field}() override on the model over the map', function () {
    $p = new class extends Product
    {
        protected $table = 'products';

        public function madadPrice(): float
        {
            return 1.23;
        }
    };
    $p->forceFill(['sku' => 'SKU-8', 'title' => 'X', 'amount' => 999])->save();

    $payload = PayloadBuilder::build($p, fullMap());

    expect($payload['price'])->toBe(1.23);
});

it('serializes a MadadPrice returned from madadPrice() to the wire shape', function () {
    $p = new class extends Product
    {
        protected $table = 'products';

        public function madadPrice(): \Madad\Sdk\Values\MadadPrice
        {
            return \Madad\Sdk\Values\MadadPrice::range(40, 70);
        }
    };
    $p->forceFill(['sku' => 'SKU-9', 'title' => 'X', 'amount' => 999])->save();

    $payload = PayloadBuilder::build($p, fullMap());

    expect($payload['price'])->toBe(['type' => 'range', 'price' => 40.0, 'max_price' => 70.0]);
});
