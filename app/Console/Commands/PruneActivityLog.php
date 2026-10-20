<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Support\Settings;
use Illuminate\Console\Command;

/** Deletes activity log rows older than Settings → Security → "Keep activity log for". */
class PruneActivityLog extends Command
{
    protected $signature = 'shop:prune-activity';

    protected $description = 'Delete old activity log entries';

    public function handle(): int
    {
        $days = (int) Settings::get('activity_log_days', 90);
        if ($days <= 0) {
            return self::SUCCESS;   // 0 = keep everything
        }
        $n = 0;
        do {
            $batch = ActivityLog::where('created_at', '<', now()->subDays($days))->limit(5000)->delete();
            $n += $batch;
        } while ($batch > 0);
        $this->info("Deleted {$n} old activity log entries.");

        return self::SUCCESS;
    }
}
