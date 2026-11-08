<?php

namespace App\Console\Commands;

use App\Services\CadastralDept\CadastralCaseTimer;
use Illuminate\Console\Command;

class CadastralTimelineEvaluate extends Command
{
    protected $signature = 'cadastral:timeline-evaluate {--dry-run : Show how many open cases would be evaluated without saving changes}';

    protected $description = 'Evaluate active Cadastral files against their reminder, warning, default and penalty timeline.';

    public function handle(): int
    {
        $timer = app(CadastralCaseTimer::class);
        $count = $timer->evaluateAll();

        $this->info("Cadastral timeline check completed: {$count} case(s) updated.");

        return self::SUCCESS;
    }
}
