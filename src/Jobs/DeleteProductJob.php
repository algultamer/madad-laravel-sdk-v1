<?php

namespace Madad\Sdk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Madad\Sdk\Exceptions\MadadException;
use Madad\Sdk\MadadClient;

/**
 * Removes a product from Madad by external_id. The id is captured at dispatch
 * time (the local model may already be gone). A 404 is treated as success.
 */
class DeleteProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public string $externalId) {}

    public static function dispatchExternal(string $externalId): void
    {
        $job = new self($externalId);

        if (! config('madad.queue.enabled', true)) {
            dispatch_sync($job);

            return;
        }

        dispatch($job)
            ->onConnection(config('madad.queue.connection'))
            ->onQueue(config('madad.queue.name', 'default'));
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(MadadClient $client): void
    {
        try {
            $client->deleteProduct($this->externalId);
        } catch (MadadException $e) {
            if (! $e->isNotFound()) {
                throw $e; // already gone is fine; anything else should retry
            }
        }
    }
}
