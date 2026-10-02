<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\CertificationController;
use Illuminate\Http\Request;

class TestGISController extends Command
{
    protected $signature = 'test:gis-controller';
    protected $description = 'Test GIS controller method directly';

    public function handle()
    {
        try {
            $this->info('Testing GIS controller method...');
            
            $controller = new CertificationController();
            $request = new Request();
            
            $response = $controller->getGISData($request);
            
            $this->info('Response: ' . $response->getContent());
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());
        }
    }
}