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

    // Format data for vetting
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
        if ($app->plot_size) {
            $plotDetails .= ($plotDetails ? ', ' : '') . 'Size: ' . $app->plot_size;
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
            'file_number' => $app->file_number ?? 'N/A',
            'applicant_name' => $applicantName,
            'applicant_type' => $app->applicant_type ?? 'N/A',
            'plot_details' => $plotDetails,
            'lga_name' => $app->lga_name ?? 'N/A',
            'created_at' => $app->created_at ? date('d M Y', strtotime($app->created_at)) : 'N/A',
            'vetting_status' => $app->vetting_status ?? 'pending',
        ];
    });

    // Calculate statistics
    $total = $data->count();
    $vetted = $data->where('vetting_status', 'vetted')->count();
    $pending = $total - $vetted;
    $thisMonth = $data->filter(function($app) {
        if ($app['created_at'] === 'N/A') return false;
        try {
            $createdDate = \DateTime::createFromFormat('d M Y', $app['created_at']);
            if (!$createdDate) return false;
            $startOfMonth = new \DateTime('first day of this month');
            return $createdDate >= $startOfMonth;
        } catch (\Exception $e) {
            return false;
        }
    })->count();

    $statistics = [
        'total' => $total,
        'vetted' => $vetted,
        'pending' => $pending,
        'thisMonth' => $thisMonth
    ];

    echo json_encode([
        'success' => true,
        'data' => $data,
        'statistics' => $statistics
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'data' => [],
        'statistics' => ['total' => 0, 'vetted' => 0, 'pending' => 0, 'thisMonth' => 0],
        'error' => $e->getMessage()
    ]);
}