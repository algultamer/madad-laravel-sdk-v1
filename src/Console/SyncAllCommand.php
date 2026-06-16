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
            $total = $model::query()->count();

            $this->info("Syncing {$total} product(s) to Madad ".($queued ? '(queued)' : '(inline)').'...');
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $model::query()->chunkById((int) $this->option('chunk'), function ($rows) use ($queued, $sleep, $bar) {
                foreach ($rows as $row) {
                    SyncProductJob::dispatchFor($row::class, $row->getKey());
                    $bar->advance();

                    if (! $queued && $sleep > 0) {
                        usleep($sleep * 1000);
                    }
                }
            });

            $bar->finish();
            $this->newLine(2);
            $this->info($queued
                ? 'Done — jobs dispatched. Make sure a queue worker is running.'
                : 'Done — all products pushed.');

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
