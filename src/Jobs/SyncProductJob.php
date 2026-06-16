<?php

namespace Madad\Sdk\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Madad\Sdk\MadadClient;

/**
 * Pushes a single product to Madad (upsert by external_id). Re-fetches the model
 * at run time so the payload reflects the latest state, not the dispatch-time copy.
 */
class SyncProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @param class-string<Model> $modelClass */
    public function __construct(
        public string $modelClass,
        public int|string $modelKey,
    ) {}

    /** Dispatch respecting the configured queue settings (or inline). */
    public static function dispatchFor(string $modelClass, int|string $key): void
    {
        $job = new self($modelClass, $key);

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
        /** @var Model|null $model */
        $model = $this->modelClass::query()->find($this->modelKey);

        if ($model === null) {
            return; // deleted before the job ran — nothing to push
        }

        $externalId = $model->madadExternalId();
        $client->upsertProduct($externalId, $model->toMadadPayload());
    }
}
