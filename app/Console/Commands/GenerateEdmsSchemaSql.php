<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Services\EdmsSchemaChecker;

class GenerateEdmsSchemaSql extends Command
{
    protected $signature = 'edms:schema:sql {--output= : Optional output file path relative to storage/}';

    protected $description = 'Generate T-SQL script to create/alter EDMS database schema (SQL Server). Review and run manually.';

    public function handle(): int
    {
        $checker = new EdmsSchemaChecker();
        $sql = $checker->generateSql();

        $this->line('');
        $this->info('EDMS Schema T-SQL (preview below):');
        $this->line(str_repeat('-', 80));
        $this->line($sql);
        $this->line(str_repeat('-', 80));

        $output = $this->option('output') ?: 'edms_schema_update.sql';
        Storage::disk('local')->put($output, $sql);

        $this->info("Saved to storage/app/{$output}");
        $this->comment('Note: Do not run automatically. Review and execute on SQL Server manually.');
        return self::SUCCESS;
    }
}
