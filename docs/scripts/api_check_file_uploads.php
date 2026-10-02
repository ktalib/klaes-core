<?php

// Simple debug endpoint to check file upload results
require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: application/json');

try {
    $applicationId = $_GET['id'] ?? null;
    
    if (!$applicationId) {
        echo json_encode(['error' => 'Application ID required']);
        exit;
    }
    
    $record = DB::connection('sqlsrv')->select("
        SELECT id, applicant_type, first_name, surname, passport, id_document, created_at
        FROM mother_applications 
        WHERE id = ?
    ", [$applicationId]);
    
    if (empty($record)) {
        echo json_encode(['error' => 'Record not found']);
        exit;
    }
    
    $data = $record[0];
    
    echo json_encode([
        'id' => $data->id,
        'applicant_type' => $data->applicant_type,
        'first_name' => $data->first_name,
        'surname' => $data->surname,
        'passport' => $data->passport,
        'id_document' => $data->id_document,
        'created_at' => $data->created_at,
        'files_identical' => $data->passport === $data->id_document
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}