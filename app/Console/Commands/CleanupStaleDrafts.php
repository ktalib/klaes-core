<?php

namespace App\Console\Commands;

use App\Models\MotherApplicationDraft;
use App\Models\MotherApplicationDraftCollaborator;
use App\Models\MotherApplicationDraftVersion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupStaleDrafts extends Command
{
    protected $signature = 'drafts:cleanup {--force : Delete drafts without prompting}';

    protected $description = 'Remove stale primary application drafts and associated metadata';

    public function handle(): int
    {
        $threshold30 = Carbon::now()->subDays(30);
        $threshold3 = Carbon::now()->subDays(3);

        $query = MotherApplicationDraft::query()
            ->where(function ($outer) use ($threshold30, $threshold3) {
                $outer->where('last_saved_at', '<', $threshold30)
                    ->orWhere(function ($inner) use ($threshold3) {
                        $inner->where('last_saved_at', '<', $threshold3)
                            ->where(function ($q) {
                                $q->whereNull('progress_percent')
                                  ->orWhere('progress_percent', '<', 25);
                            });
                    });
            });

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No stale drafts found.');
            return self::SUCCESS;
        }

        $this->warn("{$total} stale draft(s) identified for cleanup.");

        if (!$this->option('force') && !$this->confirm('Proceed with deletion?')) {
            $this->info('Cleanup cancelled.');
            return self::INVALID;
        }

        DB::connection('sqlsrv')->beginTransaction();

        try {
            $drafts = $query->get(['draft_id']);
            $ids = $drafts->pluck('draft_id')->all();

            MotherApplicationDraftVersion::whereIn('draft_id', $ids)->delete();
            MotherApplicationDraftCollaborator::whereIn('draft_id', $ids)->delete();
            $deleted = MotherApplicationDraft::whereIn('draft_id', $ids)->delete();

            DB::connection('sqlsrv')->commit();

            $this->info("Deleted {$deleted} draft records and associated metadata.");

            Log::info('CleanupStaleDrafts executed', [
                'deleted' => $deleted,
                'draft_ids' => $ids,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $throwable) {
            DB::connection('sqlsrv')->rollBack();
            $this->error('Failed to clean up drafts: ' . $throwable->getMessage());
            Log::error('CleanupStaleDrafts failed', ['error' => $throwable->getMessage()]);

            return self::FAILURE;
        }
    }
}
