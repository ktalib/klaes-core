<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UserImportController extends Controller
{
    /**
     * Show the import form
     */
    public function showImportForm()
    {
        $user = Auth::user();

        if ($user->type !== 'super admin' && !$user->can('create user')) {
            return response()->json([
                'success' => false,
                'message' => __('Permission Denied.')
            ], 403);
        }

        return view('user.import-modal');
    }

    /**
     * Download CSV template for user import
     */
    public function downloadTemplate()
    {
        $user = Auth::user();

        if ($user->type !== 'super admin' && !$user->can('create user')) {
            abort(403, __('Permission Denied.'));
        }

        $filename = 'user-import-template.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = static function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['first_name', 'last_name', 'username', 'type', 'department_id', 'user_level', 'assign_role']);
            fputcsv($handle, ['Jane', 'Smith', 'jane.smith', 'User', '1', 'High', 'Dashboard, GIS - Records']);
            fputcsv($handle, ['John', 'Doe', 'john.doe', 'User', '5', 'Low', 'ST - Overview, ST - Applications']);
            fputcsv($handle, ['Bob', 'Johnson', 'bob.johnson', 'User', '2', 'High', 'Dashboard']);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Download department lookup as PDF
     */
    public function departmentLookupPdf()
    {
        $user = Auth::user();

        if ($user->type !== 'super admin' && !$user->can('create user')) {
            abort(403, __('Permission Denied.'));
        }

        // Get all departments
        $departments = DB::connection('sqlsrv')
            ->table('departments')
            ->select('id', 'name', 'code', 'description')
            ->orderBy('name')
            ->get();

        $filename = 'department-lookup-' . now()->format('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = static function () use ($departments) {
            $handle = fopen('php://output', 'w');
            
            // Write header
            fputcsv($handle, ['ID', 'Department Name', 'Code', 'Description']);
            
            // Write department data
            foreach ($departments as $dept) {
                fputcsv($handle, [
                    $dept->id,
                    $dept->name,
                    $dept->code,
                    $dept->description ?? ''
                ]);
            }
            
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
    public function importUsers(Request $request)
    {
        try {
            // Check permissions
            $user = Auth::user();

            if ($user->type !== 'super admin' && !$user->can('create user')) {
                return response()->json([
                    'success' => false,
                    'message' => __('Permission Denied.')
                ], 403);
            }

            // Check if this is JSON payload (new preview-based import)
            $isJsonPayload = $request->has('records') && is_array($request->input('records'));

            if ($isJsonPayload) {
                // New format: JSON payload from preview component
                $request->validate([
                    'records' => 'required|array',
                    'records.*.first_name' => 'required|string',
                    'records.*.last_name' => 'required|string',
                    'records.*.username' => 'required|string',
                    'records.*.type' => 'required|string',
                    'environment' => 'required|in:TEST,PRO'
                ], [
                    'records.required' => __('No records to import'),
                    'environment.required' => __('Environment is required'),
                    'environment.in' => __('Environment must be TEST or PRO')
                ]);

                $records = $request->input('records');
                $environment = $request->input('environment');

                // Process JSON payload
                $result = $this->processImportRecords($records, $environment);
            } else {
                // Old format: CSV file upload (for backward compatibility)
                $request->validate([
                    'csv_file' => 'required|file|mimes:csv,txt|max:1024', // 1MB limit
                    'environment' => 'required|in:TEST,PRO'
                ], [
                    'csv_file.required' => __('CSV file is required'),
                    'csv_file.mimes' => __('File must be CSV format'),
                    'csv_file.max' => __('File size must not exceed 1MB'),
                    'environment.required' => __('Environment is required'),
                    'environment.in' => __('Environment must be TEST or PRO')
                ]);

                $csvFile = $request->file('csv_file');
                $environment = $request->input('environment');

                // Read and parse CSV
                $result = $this->parseAndImportCSV($csvFile, $environment);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('User import error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Error processing import: ') . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process import records from JSON payload (new preview-based import)
     */
    private function processImportRecords($records, $environment)
    {
        $imported = 0;
        $failed = 0;
        $errors = [];
        $rowNumber = 0;
        $maxUsers = 50;

        // Track emails to prevent duplicates in import batch
        $csvEmails = [];
        $csvUsernames = [];

        foreach ($records as $record) {
            $rowNumber++;

            // Stop if we've reached max users
            if ($imported >= $maxUsers) {
                break;
            }

            try {
                // Extract and validate data
                $userData = [
                    'first_name' => trim($record['first_name'] ?? ''),
                    'last_name' => trim($record['last_name'] ?? ''),
                    'username' => trim($record['username'] ?? ''),
                    'type' => trim($record['type'] ?? ''),
                    'department_id' => trim($record['department_id'] ?? ''),
                    'user_level' => trim($record['user_level'] ?? ''),
                    'assign_role' => trim($record['assign_role'] ?? ''),
                ];

                // Validate required fields
                if (empty($userData['first_name'])) {
                    $errors[] = __('Row') . " $rowNumber: " . __('First name is required');
                    $failed++;
                    continue;
                }

                if (empty($userData['last_name'])) {
                    $errors[] = __('Row') . " $rowNumber: " . __('Last name is required');
                    $failed++;
                    continue;
                }

                if (empty($userData['username'])) {
                    $errors[] = __('Row') . " $rowNumber: " . __('Username is required');
                    $failed++;
                    continue;
                }

                if (empty($userData['type'])) {
                    $errors[] = __('Row') . " $rowNumber: " . __('User type is required');
                    $failed++;
                    continue;
                }

                // Validate username format (alphanumeric and separators)
                if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $userData['username'])) {
                    $errors[] = __('Row') . " $rowNumber: " . __('Username must be 3-50 characters and contain only letters, numbers, dots, underscores, or hyphens');
                    $failed++;
                    continue;
                }

                // Check for duplicate usernames in import batch
                if (in_array(strtolower($userData['username']), $csvUsernames)) {
                    $errors[] = __('Row') . " $rowNumber: " . __('Duplicate username in import');
                    $failed++;
                    continue;
                }

                $csvUsernames[] = strtolower($userData['username']);

                // Check if username already exists in database
                if (User::where('username', $userData['username'])->exists()) {
                    $errors[] = __('Row') . " $rowNumber: " . __('Username already exists');
                    $failed++;
                    continue;
                }

                // Validate department_id if provided
                if (!empty($userData['department_id'])) {
                    $departmentExists = DB::connection('sqlsrv')
                        ->table('departments')
                        ->where('id', $userData['department_id'])
                        ->exists();
                    
                    if (!$departmentExists) {
                        $errors[] = __('Row') . " $rowNumber: " . __('Invalid department ID: ') . $userData['department_id'];
                        $failed++;
                        continue;
                    }
                }

                // Create user
                $user = new User();
                $user->first_name = $userData['first_name'];
                $user->last_name = $userData['last_name'];
                $user->email = $userData['username'] . '@klaes.ng'; // Generate system email
                $user->username = $userData['username'];
                $user->password = Hash::make('password'); // Default password
                $user->type = $userData['type'];
                $user->is_active = 1; // Active by default
                $user->test_control = $environment; // Store environment
                $user->lang = 'english';
                $authUser = Auth::user();
                $user->parent_id = $authUser ? ($authUser->parent_id ?? $authUser->id) : null;
                if (function_exists('parentId')) {
                    $user->parent_id = parentId();
                }
                $user->assign_role = !empty($userData['assign_role']) ? $userData['assign_role'] : null;
                $user->department_id = !empty($userData['department_id']) ? $userData['department_id'] : null;
                $user->user_level = !empty($userData['user_level']) ? $userData['user_level'] : null;
                $user->subscription = null;
                $user->subscription_expire_date = null;
                $user->profile = 'avatar.png';
                $user->email_verified_at = now();

                $user->save();

                $imported++;

            } catch (\Exception $e) {
                $errors[] = __('Row') . " $rowNumber: " . $e->getMessage();
                $failed++;
            }
        }

        // Prepare response
        $response = [
            'success' => $imported > 0,
            'imported' => $imported,
            'failed' => $failed,
            'total_processed' => $imported + $failed,
            'message' => $imported > 0
                ? __('Import completed successfully') . ": $imported " . __('users imported')
                : __('No users were imported. Please review the data for errors.'),
        ];

        if (!empty($errors)) {
            $response['errors'] = array_slice($errors, 0, 10); // Return first 10 errors
            $response['error_count'] = count($errors);
            if (count($errors) > 10) {
                $response['message'] .= " " . __('(showing first 10 of') . " " . count($errors) . " " . __('errors)');
            }
        }

        return $response;
    }

    /**
     * Parse CSV and import users
     */
    private function parseAndImportCSV($file, $environment)
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            throw new \Exception(__('Unable to open CSV file'));
        }

        $imported = 0;
        $failed = 0;
        $errors = [];
        $rowNumber = 0;
        $maxUsers = 50;

        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new \Exception(__('CSV file is empty'));
        }

        // Expected required columns
        $requiredColumns = ['first_name', 'last_name', 'username', 'type'];
        // Optional columns
        $optionalColumns = ['department_id', 'user_level', 'assign_role'];
        $allExpectedColumns = array_merge($requiredColumns, $optionalColumns);
        
        $header = array_map('strtolower', array_map('trim', $header));

        // Validate required columns exist
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $header)) {
                fclose($handle);
                throw new \Exception(__('CSV must contain required columns: ') . implode(', ', $requiredColumns));
            }
        }

        // Get column indices
        $columnMap = array_flip($header);

        // Track emails to prevent duplicates in CSV
        $csvEmails = [];
        $csvUsernames = [];

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Stop if we've reached max users
            if ($imported >= $maxUsers) {
                break;
            }

            try {
                // Map CSV values to array
                $userData = [];
                foreach ($allExpectedColumns as $column) {
                    $index = $columnMap[$column] ?? null;
                    $userData[$column] = $index !== null ? trim($row[$index] ?? '') : '';
                }

                // Validate required fields
                if (empty($userData['first_name'])) {
                    $errors[] = __('Row') . " $rowNumber: " . __('First name is required');
                    $failed++;
                    continue;
                }

                if (empty($userData['last_name'])) {
                    $errors[] = __('Row') . " $rowNumber: " . __('Last name is required');
                    $failed++;
                    continue;
                }

                if (empty($userData['username'])) {
                    $errors[] = __('Row') . " $rowNumber: " . __('Username is required');
                    $failed++;
                    continue;
                }

                if (empty($userData['type'])) {
                    $errors[] = __('Row') . " $rowNumber: " . __('User type is required');
                    $failed++;
                    continue;
                }

                // Validate username format (alphanumeric and separators)
                if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $userData['username'])) {
                    $errors[] = __('Row') . " $rowNumber: " . __('Username must be 3-50 characters and contain only letters, numbers, dots, underscores, or hyphens');
                    $failed++;
                    continue;
                }

                $csvEmails[] = strtolower($userData['username']);

                // Check for duplicate usernames in CSV
                if (in_array(strtolower($userData['username']), $csvUsernames)) {
                    $errors[] = __('Row') . " $rowNumber: " . __('Duplicate username in CSV');
                    $failed++;
                    continue;
                }

                $csvUsernames[] = strtolower($userData['username']);

                // Check if username already exists in database
                if (User::where('username', $userData['username'])->exists()) {
                    $errors[] = __('Row') . " $rowNumber: " . __('Username already exists');
                    $failed++;
                    continue;
                }

                // Validate department_id if provided
                if (!empty($userData['department_id'])) {
                    $departmentExists = DB::connection('sqlsrv')
                        ->table('departments')
                        ->where('id', $userData['department_id'])
                        ->exists();
                    
                    if (!$departmentExists) {
                        $errors[] = __('Row') . " $rowNumber: " . __('Invalid department ID: ') . $userData['department_id'];
                        $failed++;
                        continue;
                    }
                }

                // Create user
                $user = new User();
                $user->first_name = $userData['first_name'];
                $user->last_name = $userData['last_name'];
                $user->email = $userData['username'] . '@klaes.ng'; // Generate system email
                $user->username = $userData['username'];
                $user->password = Hash::make('password'); // Default password
                $user->type = $userData['type'];
                $user->is_active = 1; // Active by default
                $user->test_control = $environment; // Store environment
                $user->lang = 'english';
                $authUser = Auth::user();
                $user->parent_id = $authUser ? ($authUser->parent_id ?? $authUser->id) : null;
                if (function_exists('parentId')) {
                    $user->parent_id = parentId();
                }
                $user->assign_role = !empty($userData['assign_role']) ? $userData['assign_role'] : null;
                $user->department_id = !empty($userData['department_id']) ? $userData['department_id'] : null;
                $user->user_level = !empty($userData['user_level']) ? $userData['user_level'] : null;
                $user->subscription = null;
                $user->subscription_expire_date = null;
                $user->profile = 'avatar.png';
                $user->email_verified_at = now();

                $user->save();

                $imported++;

            } catch (\Exception $e) {
                $errors[] = __('Row') . " $rowNumber: " . $e->getMessage();
                $failed++;
            }
        }

        fclose($handle);

        // Prepare response
        $response = [
            'success' => $imported > 0,
            'imported' => $imported,
            'failed' => $failed,
            'total_processed' => $imported + $failed,
            'message' => $imported > 0
                ? __('Import completed successfully') . ": $imported " . __('users imported')
                : __('No users were imported. Please review the CSV for errors.'),
        ];

        if (!empty($errors)) {
            $response['errors'] = array_slice($errors, 0, 10); // Return first 10 errors
            $response['error_count'] = count($errors);
            if (count($errors) > 10) {
                $response['message'] .= " " . __('(showing first 10 of') . " " . count($errors) . " " . __('errors)');
            }
        }

        return $response;
    }

    /**
     * Clear test data
     */
    public function clearTestData()
    {
        try {
            // Check permissions
            $user = Auth::user();

            if ($user->type !== 'super admin' && !$user->can('delete user')) {
                return response()->json([
                    'success' => false,
                    'message' => __('Permission Denied.')
                ], 403);
            }

            // Count users to be deleted
            $count = User::where('test_control', 'TEST')->count();

            if ($count === 0) {
                return response()->json([
                    'success' => false,
                    'message' => __('No test data found to clear')
                ]);
            }

            // Delete test users
            User::where('test_control', 'TEST')->delete();

            Log::info('Test data cleared', [
                'user_id' => Auth::id(),
                'users_deleted' => $count,
                'timestamp' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => __('Test data cleared successfully') . ": $count " . __('users deleted'),
                'deleted_count' => $count
            ]);

        } catch (\Exception $e) {
            Log::error('Clear test data error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Error clearing test data: ') . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get import statistics
     */
    public function getImportStats()
    {
        try {
            $user = Auth::user();

            if ($user->type !== 'super admin' && !$user->can('manage user')) {
                return response()->json([
                    'success' => false,
                    'message' => __('Permission Denied.')
                ], 403);
            }

            $stats = [
                'total_users' => User::count(),
                'test_users' => User::where('test_control', 'TEST')->count(),
                'pro_users' => User::where('test_control', 'PRO')->count(),
                'no_environment' => User::whereNull('test_control')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Import stats error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Error fetching statistics')
            ], 500);
        }
    }

}
