<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateCofoTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cofo:update-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add cofo_no and application_id columns to CofO table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $this->info('Checking CofO table structure...');
            
            $connection = DB::connection('sqlsrv');
            
            // Check if the table exists
            $tables = $connection->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
            $cofoTableName = null;
            
            foreach ($tables as $table) {
                if (in_array(strtolower($table->TABLE_NAME), ['cofo', 'cofo'])) {
                    $cofoTableName = $table->TABLE_NAME;
                    break;
                }
            }
            
            if (!$cofoTableName) {
                $this->error('CofO table not found');
                return Command::FAILURE;
            }
            
            $this->info("Found CofO table: {$cofoTableName}");
            
            // Check existing columns
            $columns = $connection->select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$cofoTableName}'");
            $existingColumns = array_map(function($col) { return strtolower($col->COLUMN_NAME); }, $columns);
            
            // Add cofo_no column if it doesn't exist
            if (!in_array('cofo_no', $existingColumns)) {
                $connection->statement("ALTER TABLE [{$cofoTableName}] ADD cofo_no NVARCHAR(255) NULL");
                $this->info('✅ Added cofo_no column');
            } else {
                $this->warn('⚠️ cofo_no column already exists');
            }
            
            // Add application_id column if it doesn't exist
            if (!in_array('application_id', $existingColumns)) {
                $connection->statement("ALTER TABLE [{$cofoTableName}] ADD application_id INT NULL");
                $this->info('✅ Added application_id column');
            } else {
                $this->warn('⚠️ application_id column already exists');
            }
            
            $this->info('✅ CofO table update completed successfully!');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
