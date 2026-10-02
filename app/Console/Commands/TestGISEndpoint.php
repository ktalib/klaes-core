<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestGISEndpoint extends Command
{
    protected $signature = 'test:gis-endpoint';
    protected $description = 'Test GIS endpoint directly';

    public function handle()
    {
        try {
            $this->info('Testing GIS endpoint...');
            
            // Make a request to the GIS endpoint
            $response = Http::get('http://localhost/kangi.com.ng/recertification/gis-data');
            
            $this->info('Response Status: ' . $response->status());
            $this->info('Response Body: ' . $response->body());
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}