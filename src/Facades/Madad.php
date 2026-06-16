<?php

namespace Madad\Sdk\Facades;

use Illuminate\Support\Facades\Facade;
use Madad\Sdk\MadadClient;

/**
 * @method static array ping()
 * @method static array categories()
 * @method static array listProducts(int $perPage = 50, int $page = 1)
 * @method static array createProduct(array $payload)
 * @method static array updateProduct(string $externalId, array $payload)
 * @method static array upsertProduct(string $externalId, array $payload)
 * @method static array updatePrice(string $externalId, array $data)
 * @method static array deleteProduct(string $externalId)
 *
 * @see MadadClient
 */
class Madad extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'madad';
    }
}
