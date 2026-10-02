<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * File Number API Controller
 * Handles API requests for the Global File Number Modal
 */
class FileNumberApiController extends Controller
{
    /**
     * Get MLS file numbers for smart selector
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMlsFiles(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $limit = min($request->get('limit', 500), 1000); // Max 1000 records
            $excludeMatched = $request->get('exclude_matched', '');

            // Cache key based on search term and exclusion
            $normalizedSearch = $this->normalizeSearch($search);
            $cacheKey = 'mls_files_' . md5($normalizedSearch . $limit . $excludeMatched);

            $files = Cache::remember($cacheKey, 300, function () use ($search, $normalizedSearch, $limit, $excludeMatched) {
                $query = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->select([
                        'id',
                        'mlsfNo as mls_file_no',
                        'kangisFileNo as kangis_file_no',
                        'NewKANGISFileNo as new_kangis_file_no',
                        'created_at',
                        'pp_lands_matching',
                        'pp_st_matching',
                        'pp_sltr_matching'
                    ])
                    ->whereNotNull('mlsfNo')
                    ->where('mlsfNo', '!=', '');

                // Exclude matches if requested
                if (!empty($excludeMatched)) {
                    switch (strtolower($excludeMatched)) {
                        case 'lands':
                            $query->where(function($q) {
                                $q->whereNull('pp_lands_matching')
                                  ->orWhere(function ($q2) {
                                        $q2->where('pp_lands_matching', '!=', 1)
                                           ->where('pp_lands_matching', '!=', '1');
                                  });
                            });
                            break;
                        case 'st':
                            $query->where(function($q) {
                                $q->whereNull('pp_st_matching')
                                  ->orWhere(function ($q2) {
                                        $q2->where('pp_st_matching', '!=', 1)
                                           ->where('pp_st_matching', '!=', '1');
                                  });
                            });
                            break;
                        case 'sltr':
                            $query->where(function($q) {
                                $q->whereNull('pp_sltr_matching')
                                  ->orWhere(function ($q2) {
                                        $q2->where('pp_sltr_matching', '!=', 1)
                                           ->where('pp_sltr_matching', '!=', '1');
                                  });
                            });
                            break;
                    }
                }

                if (!empty($search)) {
                    $upperSearch = strtoupper($search);
                    $searchWildcard = '%' . $upperSearch . '%';
                    $normalizedWildcard = '%' . $normalizedSearch . '%';

                    $query->where(function ($q) use ($searchWildcard, $normalizedWildcard) {
                        $q->whereRaw('UPPER(mlsfNo) LIKE ?', [$searchWildcard])
                          ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(UPPER(mlsfNo), '-', ''), '/', ''), ' ', ''), '.', '') LIKE ?", [$normalizedWildcard]);
                    });

                    $query->orderByRaw(
                        "CASE \
                            WHEN UPPER(mlsfNo) LIKE ? THEN 0 \
                            WHEN REPLACE(REPLACE(REPLACE(REPLACE(UPPER(mlsfNo), '-', ''), '/', ''), ' ', ''), '.', '') LIKE ? THEN 1 \
                            ELSE 2 \
                        END",
                        [$upperSearch . '%', $normalizedWildcard]
                    );
                }
                
                return $query->orderBy('id', 'DESC')
                    ->limit($limit)
                    ->get()
                    ->toArray();
            });
            
            return response()->json([
                'success' => true,
                'files' => $files,
                'count' => count($files)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching MLS files: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch MLPP files',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get KANGIS file numbers for smart selector
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getKangisFiles(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $limit = min($request->get('limit', 500), 1000);
            
            $excludeMatched = $request->get('exclude_matched', '');
            $cacheKey = 'kangis_files_' . md5($this->normalizeSearch($search) . $limit . $excludeMatched);
            
            $normalizedSearch = $this->normalizeSearch($search);

            $files = Cache::remember($cacheKey, 300, function () use ($search, $normalizedSearch, $limit, $excludeMatched) {
                $query = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->select([
                        'id',
                        'kangisFileNo as kangis_file_no',
                        'mlsfNo as mls_file_no',
                        'NewKANGISFileNo as new_kangis_file_no',
                        'created_at',
                        'pp_lands_matching',
                        'pp_st_matching',
                        'pp_sltr_matching'
                    ])
                    ->whereNotNull('kangisFileNo')
                    ->where('kangisFileNo', '!=', '');
                
                // Exclude matches if requested
                if (!empty($excludeMatched)) {
                    switch (strtolower($excludeMatched)) {
                        case 'lands':
                            $query->where(function($q) {
                                $q->whereNull('pp_lands_matching')
                                  ->orWhere(function ($q2) {
                                        $q2->where('pp_lands_matching', '!=', 1)
                                           ->where('pp_lands_matching', '!=', '1');
                                  });
                            });
                            break;
                        case 'st':
                            $query->where(function($q) {
                                $q->whereNull('pp_st_matching')
                                  ->orWhere(function ($q2) {
                                        $q2->where('pp_st_matching', '!=', 1)
                                           ->where('pp_st_matching', '!=', '1');
                                  });
                            });
                            break;
                        case 'sltr':
                            $query->where(function($q) {
                                $q->whereNull('pp_sltr_matching')
                                  ->orWhere(function ($q2) {
                                        $q2->where('pp_sltr_matching', '!=', 1)
                                           ->where('pp_sltr_matching', '!=', '1');
                                  });
                            });
                            break;
                    }
                }

                if (!empty($search)) {
                    $upperSearch = strtoupper($search);
                    $normalizedWildcard = '%' . $normalizedSearch . '%';

                    $query->where(function ($q) use ($upperSearch, $normalizedWildcard) {
                        $q->whereRaw('UPPER(kangisFileNo) LIKE ?', ['%' . $upperSearch . '%'])
                          ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(UPPER(kangisFileNo), '-', ''), '/', ''), ' ', ''), '.', '') LIKE ?", [$normalizedWildcard]);
                    });

                    $query->orderByRaw(
                        "CASE \
                            WHEN UPPER(kangisFileNo) LIKE ? THEN 0 \
                            WHEN REPLACE(REPLACE(REPLACE(REPLACE(UPPER(kangisFileNo), '-', ''), '/', ''), ' ', ''), '.', '') LIKE ? THEN 1 \
                            ELSE 2 \
                        END",
                        [$upperSearch . '%', $normalizedWildcard]
                    );
                }
                
                return $query->orderBy('id', 'DESC')
                    ->limit($limit)
                    ->get()
                    ->toArray();
            });
            
            return response()->json([
                'success' => true,
                'files' => $files,
                'count' => count($files)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching KANGIS files: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch KANGIS files',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get New KANGIS file numbers for smart selector
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNewKangisFiles(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $limit = min($request->get('limit', 500), 1000);
            
            $cacheKey = 'newkangis_files_' . md5($this->normalizeSearch($search) . $limit);
            
            $normalizedSearch = $this->normalizeSearch($search);

            $files = Cache::remember($cacheKey, 300, function () use ($search, $normalizedSearch, $limit) {
                $query = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->select([
                        'id',
                        'NewKANGISFileNo as new_kangis_file_no',
                        'kangisFileNo as kangis_file_no',
                        'mlsfNo as mls_file_no',
                        'created_at'
                    ])
                    ->whereNotNull('NewKANGISFileNo')
                    ->where('NewKANGISFileNo', '!=', '');
                
                if (!empty($search)) {
                    $upperSearch = strtoupper($search);
                    $normalizedWildcard = '%' . $normalizedSearch . '%';

                    $query->where(function ($q) use ($upperSearch, $normalizedWildcard) {
                        $q->whereRaw('UPPER(NewKANGISFileNo) LIKE ?', ['%' . $upperSearch . '%'])
                          ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(UPPER(NewKANGISFileNo), '-', ''), '/', ''), ' ', ''), '.', '') LIKE ?", [$normalizedWildcard]);
                    });

                    $query->orderByRaw(
                        "CASE \
                            WHEN UPPER(NewKANGISFileNo) LIKE ? THEN 0 \
                            WHEN REPLACE(REPLACE(REPLACE(REPLACE(UPPER(NewKANGISFileNo), '-', ''), '/', ''), ' ', ''), '.', '') LIKE ? THEN 1 \
                            ELSE 2 \
                        END",
                        [$upperSearch . '%', $normalizedWildcard]
                    );
                }
                
                return $query->orderBy('id', 'DESC')
                    ->limit($limit)
                    ->get()
                    ->toArray();
            });
            
            return response()->json([
                'success' => true,
                'files' => $files,
                'count' => count($files)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching New KANGIS files: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch New KANGIS files',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get existing MLS files for extension dropdown
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExistingMlsFiles(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $limit = min($request->get('limit', 100), 500);
            
            $cacheKey = 'existing_mls_files_' . md5($this->normalizeSearch($search) . $limit);
            
            $normalizedSearch = $this->normalizeSearch($search);

            $files = Cache::remember($cacheKey, 600, function () use ($search, $normalizedSearch, $limit) {
                $query = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->select(['mlsfNo'])
                    ->whereNotNull('mlsfNo')
                    ->where('mlsfNo', '!=', '')
                    ->where('mlsfNo', 'NOT LIKE', '%EXTENSION%')
                    ->where('mlsfNo', 'NOT LIKE', '%(T)%');
                
                if (!empty($search)) {
                    $upperSearch = strtoupper($search);
                    $normalizedWildcard = '%' . $normalizedSearch . '%';

                    $query->where(function ($q) use ($upperSearch, $normalizedWildcard) {
                        $q->whereRaw('UPPER(mlsfNo) LIKE ?', ['%' . $upperSearch . '%'])
                          ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(UPPER(mlsfNo), '-', ''), '/', ''), ' ', ''), '.', '') LIKE ?", [$normalizedWildcard]);
                    });
                }
                
                return $query->orderBy('mlsfNo')
                    ->limit($limit)
                    ->distinct()
                    ->get()
                    ->map(function ($item) {
                        return [
                            'file_number' => $item->mlsfNo,
                            'mlsFNo' => $item->mlsfNo
                        ];
                    })
                    ->toArray();
            });
            
            return response()->json([
                'success' => true,
                'files' => $files,
                'count' => count($files)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching existing MLS files: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch existing MLPP files',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Validate file number format
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateFileNumber(Request $request)
    {
        try {
            $fileNumber = $request->get('file_number', '');
            $system = $request->get('system', 'mls');
            
            if (empty($fileNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File number is required'
                ]);
            }
            
            $validation = $this->performValidation($fileNumber, $system);
            
            return response()->json([
                'success' => $validation['valid'],
                'message' => $validation['message'],
                'suggestions' => $validation['suggestions'] ?? []
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error validating file number: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Check if file number already exists
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkDuplicate(Request $request)
    {
        try {
            $fileNumber = $request->get('file_number', '');
            $system = $request->get('system', 'mls');
            
            if (empty($fileNumber)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File number is required'
                ]);
            }
            
            $exists = $this->checkFileNumberExists($fileNumber, $system);
            
            return response()->json([
                'success' => true,
                'exists' => $exists,
                'message' => $exists ? 'File number already exists' : 'File number is available'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error checking file number duplicate: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Duplicate check failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Search file numbers across all systems
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchFileNumbers(Request $request)
    {
        try {
            $search = $request->get('search', '');
            $systems = $request->get('systems', ['mls', 'kangis', 'newkangis']);
            $limit = min($request->get('limit', 100), 500);
            
            if (empty($search)) {
                return response()->json([
                    'success' => true,
                    'results' => [],
                    'count' => 0
                ]);
            }
            
            $cacheKey = 'search_files_' . md5($search . implode(',', $systems) . $limit);
            
            $results = Cache::remember($cacheKey, 300, function () use ($search, $systems, $limit) {
                $query = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->select([
                        'id',
                        'mlsfNo',
                        'kangisFileNo',
                        'NewKANGISFileNo',
                        'created_at'
                    ]);
                
                $whereClause = [];
                if (in_array('mls', $systems)) {
                    $whereClause[] = ['mlsfNo', 'LIKE', '%' . $search . '%'];
                }
                if (in_array('kangis', $systems)) {
                    $whereClause[] = ['kangisFileNo', 'LIKE', '%' . $search . '%'];
                }
                if (in_array('newkangis', $systems)) {
                    $whereClause[] = ['NewKANGISFileNo', 'LIKE', '%' . $search . '%'];
                }
                
                if (!empty($whereClause)) {
                    $query->where(function ($q) use ($whereClause) {
                        foreach ($whereClause as $where) {
                            $q->orWhere($where[0], $where[1], $where[2]);
                        }
                    });
                }
                
                return $query->orderBy('id', 'DESC')
                    ->limit($limit)
                    ->get()
                    ->toArray();
            });
            
            return response()->json([
                'success' => true,
                'results' => $results,
                'count' => count($results)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error searching file numbers: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Perform file number validation based on system
     *
     * @param string $fileNumber
     * @param string $system
     * @return array
     */
    private function performValidation($fileNumber, $system)
    {
        switch (strtolower($system)) {
            case 'mls':
                return $this->validateMLSFormat($fileNumber);
            case 'kangis':
                return $this->validateKANGISFormat($fileNumber);
            case 'newkangis':
                return $this->validateNewKANGISFormat($fileNumber);
            default:
                return [
                    'valid' => false,
                    'message' => 'Unknown file number system'
                ];
        }
    }

    /**
     * Validate MLS format
     *
     * @param string $fileNumber
     * @return array
     */
    private function validateMLSFormat($fileNumber)
    {
        $patterns = [
            // Regular format: PREFIX-YEAR-SERIAL
            '/^(RES|COM|IND|AG|CON-RES|CON-COM|CON-IND|CON-AG|RES-RC|COM-RC|AG-RC|IND-RC|CON-RES-RC|CON-COM-RC|CON-AG-RC|CON-IND-RC)-\d{4}-\d+$/',
            // Temporary format: PREFIX-YEAR-SERIAL(T)
            '/^(RES|COM|IND|AG|CON-RES|CON-COM|CON-IND|CON-AG|RES-RC|COM-RC|AG-RC|IND-RC|CON-RES-RC|CON-COM-RC|CON-AG-RC|CON-IND-RC)-\d{4}-\d+\(T\)$/',
            // Extension format: PREFIX-YEAR-SERIAL AND EXTENSION
            '/^(RES|COM|IND|AG|CON-RES|CON-COM|CON-IND|CON-AG|RES-RC|COM-RC|AG-RC|IND-RC|CON-RES-RC|CON-COM-RC|CON-AG-RC|CON-IND-RC)-\d{4}-\d+ AND EXTENSION$/',
            // Miscellaneous format: MISC-PREFIX-SERIAL
            '/^MISC-[A-Z]{2,}-\d+$/',
            // SIT format: SIT-YEAR-SERIAL
            '/^SIT-\d{4}-\d+$/',
            // SLTR format: SLTR-SERIAL
            '/^SLTR-\d+$/',
            // Old MLS format: KN SERIAL
            '/^KN \d+$/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $fileNumber)) {
                return [
                    'valid' => true,
                    'message' => 'Valid MLPP format'
                ];
            }
        }

        return [
            'valid' => false,
            'message' => 'Invalid MLPP format. Expected formats: PREFIX-YEAR-SERIAL, MISC-PREFIX-SERIAL, SIT-YEAR-SERIAL, SLTR-SERIAL, or KN SERIAL',
            'suggestions' => [
                'RES-' . date('Y') . '-0001',
                'COM-' . date('Y') . '-0001',
                'MISC-KN-001',
                'SIT-' . date('Y') . '-001'
            ]
        ];
    }

    /**
     * Validate KANGIS format
     *
     * @param string $fileNumber
     * @return array
     */
    private function validateKANGISFormat($fileNumber)
    {
        // KANGIS format typically has spaces and various patterns
        if (empty(trim($fileNumber))) {
            return [
                'valid' => false,
                'message' => 'KANGIS file number cannot be empty'
            ];
        }

        // Basic validation - contains at least one space and alphanumeric characters
        if (!preg_match('/^[A-Z0-9\s\/\-\.]+\s+[A-Z0-9\s\/\-\.]+$/i', $fileNumber)) {
            return [
                'valid' => false,
                'message' => 'Invalid KANGIS format. Expected format: PREFIX NUMBER (with space separator)',
                'suggestions' => [
                    'NAIROBI/BLOCK1 12345',
                    'MOMBASA/AREA2 67890',
                    'KISUMU/ZONE3 11111'
                ]
            ];
        }

        return [
            'valid' => true,
            'message' => 'Valid KANGIS format'
        ];
    }

    /**
     * Validate New KANGIS format
     *
     * @param string $fileNumber
     * @return array
     */
    private function validateNewKANGISFormat($fileNumber)
    {
        if (empty(trim($fileNumber))) {
            return [
                'valid' => false,
                'message' => 'New KANGIS file number cannot be empty'
            ];
        }

        // New KANGIS format typically has no spaces, uses slashes and alphanumeric
        if (!preg_match('/^[A-Z0-9\/\-]+$/i', $fileNumber)) {
            return [
                'valid' => false,
                'message' => 'Invalid New KANGIS format. Expected format: PREFIX/NUMBER (no spaces)',
                'suggestions' => [
                    'NBI/BLK1/12345',
                    'MBA/AR2/67890',
                    'KSM/ZN3/11111'
                ]
            ];
        }

        return [
            'valid' => true,
            'message' => 'Valid New KANGIS format'
        ];
    }

    /**
     * Check if file number exists in database
     *
     * @param string $fileNumber
     * @param string $system
     * @return bool
     */
    private function checkFileNumberExists($fileNumber, $system)
    {
        try {
            $query = DB::connection('sqlsrv')->table('fileNumber');

            switch (strtolower($system)) {
                case 'mls':
                    $query->where('mlsfNo', $fileNumber);
                    break;
                case 'kangis':
                    $query->where('kangisFileNo', $fileNumber);
                    break;
                case 'newkangis':
                    $query->where('NewKANGISFileNo', $fileNumber);
                    break;
                default:
                    // Check all columns
                    $query->where(function ($q) use ($fileNumber) {
                        $q->where('mlsfNo', $fileNumber)
                          ->orWhere('kangisFileNo', $fileNumber)
                          ->orWhere('NewKANGISFileNo', $fileNumber);
                    });
            }

            return $query->exists();

        } catch (\Exception $e) {
            Log::error('Error checking file number existence: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Normalize user-provided search text for permissive LIKE comparisons.
     */
    protected function normalizeSearch(?string $value): string
    {
        $value = strtoupper(trim((string) $value));

        if ($value === '') {
            return '';
        }

        return str_replace(['-', '/', ' ', '.', '\\'], '', $value);
    }
}
