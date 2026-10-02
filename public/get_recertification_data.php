<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

require_once '../vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once '../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Get applications data
    $applications = DB::connection('sqlsrv')->table('recertification_applications')
        ->orderBy('created_at', 'desc')
        ->get();

    // Format data
    $data = $applications->map(function($app) {
        // Determine applicant name based on type
        $applicantName = '';
        if ($app->applicant_type === 'Corporate') {
            $applicantName = $app->organisation_name ?? 'N/A';
        } else {
            $applicantName = trim(($app->surname ?? '') . ' ' . ($app->first_name ?? ''));
            if (empty($applicantName)) {
                $applicantName = 'N/A';
            }
        }

        // Format plot details
        $plotDetails = '';
        if ($app->plot_number) {
            $plotDetails .= 'Plot: ' . $app->plot_number;
        }
        if ($app->layout_district) {
            $plotDetails .= ($plotDetails ? ', ' : '') . $app->layout_district;
        }
        if (empty($plotDetails)) {
            $plotDetails = 'N/A';
        }

        return [
            'id' => $app->id,
            'cofO_serialNo' => $app->cofo_number ?? 'N/A',
            'NewKANGISFileno' => $app->NewKANGISFileno ?? 'N/A',
            'kangisFileNo' => $app->kangisFileNo ?? 'N/A',
            'mlsfNo' => $app->mlsfNo ?? 'N/A',
            'reg_no' => $app->reg_no ?? 'N/A',
            'application_reference' => $app->application_reference ?? 'N/A',
            'file_number' => $app->file_number ?? 'N/A',
            'applicant_name' => $applicantName,
            'applicant_type' => $app->applicant_type ?? 'N/A',
            'plot_details' => $plotDetails,
            'lga_name' => $app->lga_name ?? 'N/A',
            'current_land_use' => $app->current_land_use ?? 'N/A',
            'created_at' => $app->created_at ? date('d M Y', strtotime($app->created_at)) : 'N/A',
            'cofo_number' => $app->cofo_number ?? 'N/A',
            'acknowledgement' => $app->acknowledgement ?? null,
            'cofo_exists' => false,
            'verification' => $app->verification ?? null,
        ];
    });

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'data' => [],
        'error' => $e->getMessage()
    ]);
}