<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestGISData extends Command
{
    protected $signature = 'test:gis-data';
    protected $description = 'Test GIS data retrieval';

    public function handle()
    {
        try {
            $this->info('Testing database connection...');
            
            // Test connection
            $count = DB::connection('sqlsrv')->table('recertification_applications')->count();
            $this->info("Total recertification applications: {$count}");
            
            if ($count > 0) {
                $sample = DB::connection('sqlsrv')->table('recertification_applications')->first();
                $this->info('Sample record:');
                $this->info('ID: ' . ($sample->id ?? 'N/A'));
                $this->info('File Number: ' . ($sample->file_number ?? 'N/A'));
                $this->info('Applicant Type: ' . ($sample->applicant_type ?? 'N/A'));
                $this->info('Surname: ' . ($sample->surname ?? 'N/A'));
                $this->info('First Name: ' . ($sample->first_name ?? 'N/A'));
                $this->info('Organisation Name: ' . ($sample->organisation_name ?? 'N/A'));
                $this->info('Created At: ' . ($sample->created_at ?? 'N/A'));
            }
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}