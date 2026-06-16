<?php

use Madad\Sdk\Exceptions\MadadException;

it('exposes status, code and body', function () {
    $e = new MadadException('boom', 409, 'CONFLICT', ['error' => ['code' => 'CONFLICT']]);

    expect($e->getMessage())->toBe('boom')
        ->and($e->status)->toBe(409)
        ->and($e->errorCode)->toBe('CONFLICT')
        ->and($e->body)->toBe(['error' => ['code' => 'CONFLICT']]);
});

it('recognises a conflict', function () {
    expect((new MadadException('x', 409))->isConflict())->toBeTrue()
        ->and((new MadadException('x', 404))->isConflict())->toBeFalse();
});

it('recognises a not-found', function () {
    expect((new MadadException('x', 404))->isNotFound())->toBeTrue()
        ->and((new MadadException('x', 409))->isNotFound())->toBeFalse();
});
