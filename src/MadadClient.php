<?php

namespace Madad\Sdk;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Madad\Sdk\Exceptions\MadadException;

/**
 * Thin HTTP client for the Madad Partner API v1. Mirrors the real endpoints
 * exactly (see docs/partner-api/v1). Retries on 429 with Retry-After backoff.
 */
class MadadClient
{
    public function __construct(
        protected string $baseUrl,
        protected ?string $apiKey,
        protected int $timeout = 30,
        protected int $maxRetries = 3,
    ) {}

    public function ping(): array
    {
        return $this->send('get', '/partner/ping');
    }

    public function categories(): array
    {
        return $this->send('get', '/partner/categories');
    }

    /** @return array{data: array<int, array>, meta: array} */
    public function listProducts(int $perPage = 50, int $page = 1): array
    {
        return $this->send('get', '/partner/products', ['per_page' => $perPage, 'page' => $page]);
    }

    public function createProduct(array $payload): array
    {
        return $this->send('post', '/partner/products', $payload);
    }

    public function updateProduct(string $externalId, array $payload): array
    {
        unset($payload['external_id']); // forbidden in the body on update (comes from the URL)

        return $this->send('patch', '/partner/products/'.rawurlencode($externalId), $payload);
    }

    /** Create, or update if the external_id already exists (409). */
    public function upsertProduct(string $externalId, array $payload): array
    {
        try {
            return $this->createProduct($payload);
        } catch (MadadException $e) {
            if ($e->isConflict()) {
                return $this->updateProduct($externalId, $payload);
            }
            throw $e;
        }
    }

    public function updatePrice(string $externalId, array $data): array
    {
        return $this->send('patch', '/partner/products/'.rawurlencode($externalId).'/price', $data);
    }

    public function deleteProduct(string $externalId): array
    {
        return $this->send('delete', '/partner/products/'.rawurlencode($externalId));
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);
    }

    /**
     * @param  array<string, mixed>  $data  Query params for GET, JSON body otherwise.
     */
    protected function send(string $method, string $uri, array $data = []): array
    {
        $attempt = 0;

        while (true) {
            $attempt++;
            $response = $this->request()->{$method}($uri, $data);

            // Respect the rate limit: back off and retry on 429.
            if ($response->status() === 429 && $attempt <= $this->maxRetries) {
                $wait = (int) ($response->header('Retry-After') ?: $attempt * 2);
                sleep(max(1, $wait));
                continue;
            }

            if ($response->failed()) {
                $body = $response->json();
                throw new MadadException(
                    $body['error']['message'] ?? $body['message'] ?? 'Madad API request failed',
                    $response->status(),
                    $body['error']['code'] ?? null,
                    is_array($body) ? $body : null,
                );
            }

            return $response->json() ?? [];
        }
    }
}
