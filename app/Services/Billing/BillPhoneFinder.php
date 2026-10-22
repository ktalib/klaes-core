<?php

namespace App\Services\Billing;

use App\Support\PlaceholderPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The payer's phone for a bill, from wherever the records hold one for the file
 * (user, 2026-10-21: "if the phone number is found in file indexing or anywhere,
 * it should be entered on the bill form during generating bill").
 *
 * Looked up in order, first usable number wins (newest row of each table first):
 * File Indexing, earlier bills on the file, then the application tables that
 * carry a phone. A placeholder number (08000000000…) is never offered.
 */
class BillPhoneFinder
{
    /** [table, file-number columns, phone column, label shown on the form] */
    private const SOURCES = [
        ['file_indexings', ['file_number', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no'], 'phone', 'File Indexing'],
        ['bill_master', ['file_number'], 'payer_phone', 'an earlier bill'],
        ['consent_applications', ['file_number'], 'applicant_phone', 'Consent application'],
        ['oss_applications', ['file_no', 'corresponding_fileno', 'shadow_fileno'], 'phone', 'One-Stop Shop application'],
        ['land_recommendations', ['file_number'], 'applicant_phone', 'Land recommendation'],
        ['laas_applications', ['file_number'], 'applicant_phone', 'LAAS application'],
        ['change_of_purpose_applications', ['file_no'], 'phone', 'Change of Purpose application'],
        ['valuation_reports', ['file_number'], 'phone', 'Valuation report'],
        ['spa_applications', ['file_number'], 'phone', 'SPAS record'],
        ['st_file_numbers', ['np_fileno', 'fileno', 'mls_fileno'], 'applicant_phone', 'ST file number'],
        ['mother_applications', ['np_fileno', 'fileno'], 'phone_number', 'ST primary application'],
        ['subapplications', ['np_fileno', 'fileno', 'mls_fileno'], 'phone_number', 'ST unit application'],
        ['recertification_applications', ['file_number', 'mlsFileNo', 'kangisFileNo', 'NewKANGISFileno'], 'phone_no', 'Recertification application'],
    ];

    /** @return array{phone: ?string, source: ?string} */
    public function find(?string $fileNumber): array
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '') {
            return ['phone' => null, 'source' => null];
        }

        $db = DB::connection('sqlsrv');
        foreach (self::SOURCES as [$table, $fileCols, $phoneCol, $label]) {
            try {
                $phones = $db->table($table)
                    ->where(function ($q) use ($fileCols, $fileNumber) {
                        foreach ($fileCols as $col) {
                            $q->orWhere($col, $fileNumber);
                        }
                    })
                    ->whereNotNull($phoneCol)->where($phoneCol, '<>', '')
                    ->orderByDesc('id')->limit(5)->pluck($phoneCol);
            } catch (\Throwable $e) {
                Log::warning('BillPhoneFinder: lookup failed', ['table' => $table, 'message' => $e->getMessage()]);
                continue;
            }

            foreach ($phones as $phone) {
                $phone = trim((string) $phone);
                if (strlen(preg_replace('/\D/', '', $phone)) >= 10 && !PlaceholderPhone::is($phone)) {
                    return ['phone' => $phone, 'source' => $label];
                }
            }
        }

        return ['phone' => null, 'source' => null];
    }
}
