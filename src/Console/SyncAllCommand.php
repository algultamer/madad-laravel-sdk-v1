<?php

namespace Madad\Sdk\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Madad\Sdk\Jobs\SyncProductJob;

/**
 * One-shot full catalog sync (initial load / reconcile). Explicit by design —
 * never fires as a hidden side effect. Lock-guarded against concurrent runs;
 * idempotent (upsert by external_id), so it is safe to re-run. Paces itself to
 * respect the partner rate limit when running inline.
 */
class SyncAllCommand extends Command
{
    protected $signature = 'madad:sync-all
        {--chunk=200 : Rows read per database chunk}
        {--sleep=0 : Milliseconds to wait between products (inline mode only)}';

    protected $description = 'Push your entire product catalog to Madad (initial load / reconcile).';

    public function handle(): int
    {
        $model = config('madad.product.model');

        if (! $model || ! class_exists($model)) {
            $this->error('Set madad.product.model in config/madad.php first.');

            return self::FAILURE;
        }

        $lock = Cache::lock('madad:sync-all', 600);

        if (! $lock->get()) {
            $this->error('Another madad:sync-all run is already in progress.');

            return self::FAILURE;
        }

        try {
            $queued = (bool) config('madad.queue.enabled', true);
            $sleep = (int) $this->option('sleep');

            // Optional query-level pre-filter for large catalogs; the per-row
            // shouldSyncToMadad() gate below is always applied regardless.
            $query = $model::query()
                ->when(method_exists($model, 'scopeMadadSyncable'), fn ($q) => $q->madadSyncable());

            $total = (clone $query)->count();
            $synced = 0;

            $this->info("Scanning {$total} product(s) ".($queued ? '(queued)' : '(inline)').'...');
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $query->chunkById((int) $this->option('chunk'), function ($rows) use ($queued, $sleep, $bar, &$synced) {
                foreach ($rows as $row) {
                    if ($row->shouldSyncToMadad()) {
                        SyncProductJob::dispatchFor($row::class, $row->getKey());
                        $synced++;

                        if (! $queued && $sleep > 0) {
                            usleep($sleep * 1000);
                        }
                    }
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine(2);
            $skipped = $total - $synced;
            $this->info("Synced {$synced} product(s)".($skipped > 0 ? ", skipped {$skipped} (shouldSyncToMadad)" : '').'.');
            if ($queued) {
                $this->line('Jobs dispatched — make sure a queue worker is running.');
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
