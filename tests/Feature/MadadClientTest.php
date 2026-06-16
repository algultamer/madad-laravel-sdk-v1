<?php

use Illuminate\Support\Facades\Http;
use Madad\Sdk\Exceptions\MadadException;
use Madad\Sdk\MadadClient;

function client(): MadadClient
{
    return new MadadClient('test-key');
}

it('sends ping with the bearer token', function () {
    Http::fake(['*/partner/ping' => Http::response(['ok' => true], 200)]);

    expect(client()->ping())->toBe(['ok' => true]);

    Http::assertSent(fn ($r) => $r->method() === 'GET'
        && str_ends_with($r->url(), '/partner/ping')
        && $r->hasHeader('Authorization', 'Bearer test-key'));
});

it('passes pagination as query params to listProducts', function () {
    Http::fake(['*/partner/products*' => Http::response(['data' => [], 'meta' => []], 200)]);

    client()->listProducts(25, 3);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'per_page=25')
        && str_contains($r->url(), 'page=3'));
});

it('creates a product with a JSON body', function () {
    Http::fake(['*/partner/products' => Http::response(['status' => 'created'], 201)]);

    client()->createProduct(['external_id' => 'SKU-1', 'name' => 'X']);

    Http::assertSent(fn ($r) => $r->method() === 'POST'
        && $r['external_id'] === 'SKU-1'
        && $r['name'] === 'X');
});

it('url-encodes the external_id and strips it from the update body', function () {
    Http::fake(['*/partner/products/*' => Http::response(['status' => 'updated'], 200)]);

    client()->updateProduct('SKU/9 A', ['external_id' => 'SKU/9 A', 'price' => 5]);

    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && str_ends_with($r->url(), '/partner/products/SKU%2F9%20A')
        && ! isset($r['external_id'])
        && $r['price'] === 5);
});

it('upsert creates when there is no conflict', function () {
    Http::fake(['*/partner/products' => Http::response(['status' => 'created'], 201)]);

    expect(client()->upsertProduct('SKU-1', ['external_id' => 'SKU-1', 'name' => 'X']))
        ->toBe(['status' => 'created']);

    Http::assertSentCount(1);
});

it('upsert falls back to PATCH on a 409 conflict', function () {
    Http::fake([
        '*/partner/products' => Http::response(
            ['error' => ['code' => 'CONFLICT', 'message' => 'exists']], 409
        ),
        '*/partner/products/*' => Http::response(['status' => 'updated'], 200),
    ]);

    expect(client()->upsertProduct('SKU-1', ['external_id' => 'SKU-1', 'name' => 'X']))
        ->toBe(['status' => 'updated']);

    Http::assertSent(fn ($r) => $r->method() === 'POST');
    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/partner/products/SKU-1'));
});

it('retries after a 429 then succeeds', function () {
    Http::fakeSequence('*/partner/ping')
        ->push(['error' => ['message' => 'slow down']], 429, ['Retry-After' => '1'])
        ->push(['ok' => true], 200);

    expect(client()->ping())->toBe(['ok' => true]);

    Http::assertSentCount(2);
});

it('throws a MadadException carrying the API error code and status', function () {
    Http::fake(['*/partner/products/*' => Http::response(
        ['error' => ['code' => 'NOT_FOUND', 'message' => 'Product not found for this partner']], 404
    )]);

    try {
        client()->updateProduct('NOPE', ['price' => 1]);
        $this->fail('Expected a MadadException to be thrown.');
    } catch (MadadException $e) {
        expect($e->status)->toBe(404)
            ->and($e->errorCode)->toBe('NOT_FOUND')
            ->and($e->getMessage())->toBe('Product not found for this partner')
            ->and($e->isNotFound())->toBeTrue();
    }
});

it('deletes a product by external_id', function () {
    Http::fake(['*/partner/products/*' => Http::response(['status' => 'deleted'], 200)]);

    client()->deleteProduct('SKU-1');

    Http::assertSent(fn ($r) => $r->method() === 'DELETE'
        && str_ends_with($r->url(), '/partner/products/SKU-1'));
});
