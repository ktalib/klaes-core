<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FileNumberReservationService;

class CleanupExpiredReservationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:cleanup 
                            {--force : Force cleanup without confirmation}
                            {--dry-run : Show what would be cleaned up without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired file number reservations to prevent gaps in serial numbers';

    protected $reservationService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FileNumberReservationService $reservationService)
    {
        parent::__construct();
        $this->reservationService = $reservationService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔍 Checking for expired reservations...');

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Get count of expired reservations
        $expiredCount = \App\Models\FileNumberReservation::expired()->count();

        if ($expiredCount === 0) {
            $this->info('✅ No expired reservations found.');
            return 0;
        }

        $this->warn("⚠️  Found {$expiredCount} expired reservation(s).");

        if ($dryRun) {
            $this->info('📋 DRY RUN - Listing expired reservations:');
            
            $expired = \App\Models\FileNumberReservation::expired()
                ->orderBy('expires_at')
                ->get();

            $this->table(
                ['UUID', 'Prefix', 'Year', 'Serial', 'Reserved By', 'Expired At'],
                $expired->map(function ($reservation) {
                    return [
                        substr($reservation->reservation_uuid, 0, 8) . '...',
                        $reservation->prefix,
                        $reservation->year,
                        $reservation->serial_number,
                        $reservation->reserved_by_name,
                        $reservation->expires_at->diffForHumans()
                    ];
                })
            );

            $this->info('📝 Use --force to actually clean up these reservations.');
            return 0;
        }

        // Confirm cleanup unless --force is specified
        if (!$force) {
            if (!$this->confirm("Do you want to release {$expiredCount} expired reservation(s)?", true)) {
                $this->info('❌ Cleanup cancelled.');
                return 0;
            }
        }

        // Perform cleanup
        $this->info('🧹 Cleaning up expired reservations...');
        
        $bar = $this->output->createProgressBar($expiredCount);
        $bar->start();

        $releasedCount = $this->reservationService->releaseExpiredReservations();

        $bar->finish();
        $this->newLine(2);

        if ($releasedCount > 0) {
            $this->info("✅ Successfully released {$releasedCount} expired reservation(s).");
            $this->info('💡 These serial numbers are now available for reuse, preventing gaps.');
        } else {
            $this->warn('⚠️  No reservations were released. They may have been already processed.');
        }

        return 0;
    }
}
