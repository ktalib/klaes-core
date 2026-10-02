<?php

namespace App\Console\Commands;

use App\Services\DigitalFileAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredDigitalAccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dfr:cleanup-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove expired digital file access copies and update their status to expired';

    public function __construct(protected DigitalFileAccessService $service)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting DFR expired digital access cleanup…');

        try {
            $count = $this->service->cleanupExpired();

            if ($count > 0) {
                $this->info("✓ Cleaned up {$count} expired digital access record(s). Temp folders deleted.");
                Log::info("dfr:cleanup-expired: {$count} access record(s) cleaned up.");
            } else {
                $this->info('✓ No expired digital access records found. Nothing to do.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Cleanup failed: ' . $e->getMessage());
            Log::error('dfr:cleanup-expired error: ' . $e->getMessage(), ['exception' => $e]);

            return self::FAILURE;
        }
    }
}
