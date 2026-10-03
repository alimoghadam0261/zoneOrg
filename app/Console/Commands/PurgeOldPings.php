<?php

namespace App\Console\Commands;

use App\Models\LocationPing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeOldPings extends Command
{
    protected $signature = 'zone:purge-old-pings
        {--days= : Override the retention window in days}
        {--dry-run : Report how many rows would be deleted without deleting}';

    protected $description = 'Delete raw GPS pings older than the 7-day rolling retention window';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('zone.retention_days')));
        $cutoff = now()->subDays($days);

        $this->info("Retention window: {$days} days · cutoff: {$cutoff->toDateTimeString()}");

        if ($this->option('dry-run')) {
            $count = LocationPing::query()->where('captured_at', '<', $cutoff)->count();
            $this->line("[dry-run] {$count} rows would be deleted.");

            return self::SUCCESS;
        }

        $deleted = 0;
        $batch = 5000;

        do {
            $ids = LocationPing::query()
                ->where('captured_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batch)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += LocationPing::query()->whereIn('id', $ids)->delete();

            if ($ids->count() === $batch) {
                $this->line("  … {$deleted} rows purged so far");
            }
        } while ($ids->count() === $batch);

        // Reclaim space occasionally so the index stays compact on WAMP/MySQL 8.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('OPTIMIZE TABLE location_pings');
        }

        $remaining = LocationPing::query()->count();

        $this->info("Purged {$deleted} pings · {$remaining} rows remain in the buffer.");

        return self::SUCCESS;
    }
}
