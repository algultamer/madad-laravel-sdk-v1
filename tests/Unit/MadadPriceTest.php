<?php

use Madad\Sdk\Values\MadadPrice;

it('builds a fixed price', function () {
    expect(MadadPrice::fixed(75000)->toArray())
        ->toBe(['type' => 'fixed', 'price' => 75000.0, 'max_price' => null]);
});

it('builds a from price', function () {
    expect(MadadPrice::from(50)->toArray())
        ->toBe(['type' => 'from', 'price' => 50.0, 'max_price' => null]);
});

it('builds a range price', function () {
    expect(MadadPrice::range(40, 70)->toArray())
        ->toBe(['type' => 'range', 'price' => 40.0, 'max_price' => 70.0]);
});

it('rejects a range whose max is below the min', function () {
    MadadPrice::range(70, 40);
})->throws(InvalidArgumentException::class);

it('rejects a negative price', function () {
    MadadPrice::fixed(-1);
})->throws(InvalidArgumentException::class);

it('json-encodes to the wire shape', function () {
    expect(json_encode(MadadPrice::from(50)))
        ->toBe('{"type":"from","price":50,"max_price":null}');
});
