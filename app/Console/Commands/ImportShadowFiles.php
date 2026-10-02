<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportShadowFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shadow:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Physical Planning Registry folder names into shadow_files table';

    public function handle()
    {
        $path = 'F:\\storage\\app\\public\\EDMS\\UPLOAD\\Physical_Planning_Registry';

        if (!File::exists($path)) {
            $this->error("Directory does not exist: {$path}");
            return Command::FAILURE;
        }

        $directories = File::directories($path);

        if (empty($directories)) {
            $this->warn('No folders found.');
            return Command::SUCCESS;
        }

        $count = 0;

        foreach ($directories as $directory) {

            $folderName = basename($directory);

            DB::connection('sqlsrv')
                ->table('shadow_files')
                ->updateOrInsert(
                    [
                        'file_number' => $folderName
                    ],
                    []
                );

            $count++;

            $this->line($folderName);
        }

        $this->info("");
        $this->info("Imported {$count} folders successfully.");

        return Command::SUCCESS;
    }
}