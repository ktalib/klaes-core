<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class PageTypingTestController extends Controller
{
    public function testDbConnection()
    {
        try {
            // Test SQL Server connection
            $result = DB::connection('sqlsrv')->select('SELECT 1 as test');
            
            return response()->json([
                'success' => true,
                'message' => 'Database connection successful',
                'connection' => 'sqlsrv',
                'result' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection failed',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function testRawTables()
    {
        try {
            $tables = [];
            
            // Check if CoverType table exists and get data
            try {
                $coverTypes = DB::connection('sqlsrv')->table('CoverType')->get();
                $tables['CoverType'] = [
                    'exists' => true,
                    'count' => count($coverTypes),
                    'data' => $coverTypes->toArray()
                ];
            } catch (Exception $e) {
                $tables['CoverType'] = [
                    'exists' => false,
                    'error' => $e->getMessage()
                ];
            }

            // Check if PageType table exists and get data
            try {
                $pageTypes = DB::connection('sqlsrv')->table('PageType')->get();
                $tables['PageType'] = [
                    'exists' => true,
                    'count' => count($pageTypes),
                    'data' => $pageTypes->toArray()
                ];
            } catch (Exception $e) {
                $tables['PageType'] = [
                    'exists' => false,
                    'error' => $e->getMessage()
                ];
            }

            // Check if PageSubType table exists and get data
            try {
                $pageSubTypes = DB::connection('sqlsrv')->table('PageSubType')->get();
                $tables['PageSubType'] = [
                    'exists' => true,
                    'count' => count($pageSubTypes),
                    'data' => $pageSubTypes->toArray()
                ];
            } catch (Exception $e) {
                $tables['PageSubType'] = [
                    'exists' => false,
                    'error' => $e->getMessage()
                ];
            }

            // Get list of all tables in database
            try {
                $allTables = DB::connection('sqlsrv')->select("
                    SELECT TABLE_NAME 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_TYPE = 'BASE TABLE' 
                    ORDER BY TABLE_NAME
                ");
                $tables['all_tables'] = array_map(function($table) {
                    return $table->TABLE_NAME;
                }, $allTables);
            } catch (Exception $e) {
                $tables['all_tables'] = ['error' => $e->getMessage()];
            }

            return response()->json([
                'success' => true,
                'message' => 'Table analysis complete',
                'tables' => $tables
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze tables',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function createTables()
    {
        try {
            // Create CoverType table
            DB::connection('sqlsrv')->statement("
                IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='CoverType' AND xtype='U')
                CREATE TABLE CoverType (
                    Id INT IDENTITY(1,1) PRIMARY KEY,
                    Name NVARCHAR(100) NOT NULL,
                    Code NVARCHAR(10) NULL,
                    Created_Date DATETIME DEFAULT GETDATE(),
                    Updated_Date DATETIME DEFAULT GETDATE()
                )
            ");

            // Insert default cover types
            $coverTypeExists = DB::connection('sqlsrv')->table('CoverType')->count();
            if ($coverTypeExists == 0) {
                DB::connection('sqlsrv')->table('CoverType')->insert([
                    ['Name' => 'Front Cover', 'Code' => 'FC'],
                    ['Name' => 'Back Cover', 'Code' => 'BC'],
                    ['Name' => 'Document', 'Code' => 'DOC']
                ]);
            }

            // Create PageType table
            DB::connection('sqlsrv')->statement("
                IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='PageType' AND xtype='U')
                CREATE TABLE PageType (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    PageType NVARCHAR(100) NOT NULL,
                    Code NVARCHAR(10) NULL,
                    Created_Date DATETIME DEFAULT GETDATE(),
                    Updated_Date DATETIME DEFAULT GETDATE()
                )
            ");

            // Insert default page types
            $pageTypeExists = DB::connection('sqlsrv')->table('PageType')->count();
            if ($pageTypeExists == 0) {
                DB::connection('sqlsrv')->table('PageType')->insert([
                    ['PageType' => 'File Cover', 'Code' => 'FC'],
                    ['PageType' => 'Application', 'Code' => 'APP'],
                    ['PageType' => 'Bill Notice', 'Code' => 'BN'],
                    ['PageType' => 'Correspondence', 'Code' => 'COR'],
                    ['PageType' => 'Land Title', 'Code' => 'LT'],
                    ['PageType' => 'Legal', 'Code' => 'LEG'],
                    ['PageType' => 'Payment Evidence', 'Code' => 'PE'],
                    ['PageType' => 'Report', 'Code' => 'REP'],
                    ['PageType' => 'Survey', 'Code' => 'SUR'],
                    ['PageType' => 'Miscellaneous', 'Code' => 'MISC']
                ]);
            }

            // Create PageSubType table
            DB::connection('sqlsrv')->statement("
                IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='PageSubType' AND xtype='U')
                CREATE TABLE PageSubType (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    PageTypeId INT NOT NULL,
                    PageSubType NVARCHAR(100) NOT NULL,
                    Code NVARCHAR(10) NULL,
                    Created_Date DATETIME DEFAULT GETDATE(),
                    Updated_Date DATETIME DEFAULT GETDATE()
                )
            ");

            // Insert default page subtypes
            $pageSubTypeExists = DB::connection('sqlsrv')->table('PageSubType')->count();
            if ($pageSubTypeExists == 0) {
                // Get PageType IDs
                $fileCovertTypeId = DB::connection('sqlsrv')->table('PageType')->where('PageType', 'File Cover')->value('id');
                $applicationTypeId = DB::connection('sqlsrv')->table('PageType')->where('PageType', 'Application')->value('id');
                $billNoticeTypeId = DB::connection('sqlsrv')->table('PageType')->where('PageType', 'Bill Notice')->value('id');
                $correspondenceTypeId = DB::connection('sqlsrv')->table('PageType')->where('PageType', 'Correspondence')->value('id');

                $subtypes = [];
                
                if ($fileCovertTypeId) {
                    $subtypes[] = ['PageTypeId' => $fileCovertTypeId, 'PageSubType' => 'New File Cover', 'Code' => 'NFC'];
                    $subtypes[] = ['PageTypeId' => $fileCovertTypeId, 'PageSubType' => 'Old File Cover', 'Code' => 'OFC'];
                }
                
                if ($applicationTypeId) {
                    $subtypes[] = ['PageTypeId' => $applicationTypeId, 'PageSubType' => 'Certificate of Occupancy', 'Code' => 'CO'];
                    $subtypes[] = ['PageTypeId' => $applicationTypeId, 'PageSubType' => 'Revalidation', 'Code' => 'REV'];
                }
                
                if ($billNoticeTypeId) {
                    $subtypes[] = ['PageTypeId' => $billNoticeTypeId, 'PageSubType' => 'Demand for Ground Rent', 'Code' => 'DGR'];
                    $subtypes[] = ['PageTypeId' => $billNoticeTypeId, 'PageSubType' => 'Demand Notice', 'Code' => 'DN'];
                }
                
                if ($correspondenceTypeId) {
                    $subtypes[] = ['PageTypeId' => $correspondenceTypeId, 'PageSubType' => 'Acknowledgment Letter', 'Code' => 'AL'];
                    $subtypes[] = ['PageTypeId' => $correspondenceTypeId, 'PageSubType' => 'Application Submission', 'Code' => 'ASR'];
                }

                if (!empty($subtypes)) {
                    DB::connection('sqlsrv')->table('PageSubType')->insert($subtypes);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Tables created and populated successfully',
                'details' => [
                    'CoverType_count' => DB::connection('sqlsrv')->table('CoverType')->count(),
                    'PageType_count' => DB::connection('sqlsrv')->table('PageType')->count(),
                    'PageSubType_count' => DB::connection('sqlsrv')->table('PageSubType')->count()
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tables',
                'error' => $e->getMessage()
            ]);
        }
    }
}