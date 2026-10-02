<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SecurityCodeService
{
    /**
     * Generate a unique 9-character security code.
     * Format: 8 digits and 1 alphabet character (A-Z) at any position.
     *
     * @return string
     */
    public function generateCode()
    {
        do {
            // Generate 8 digits
            $digits = str_split(str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT));
            
            // Generate 1 alphabet character (A-Z)
            $letter = chr(mt_rand(65, 90));
            
            // Random position (0 to 8)
            $position = mt_rand(0, 8);
            
            // Insert letter at position
            array_splice($digits, $position, 0, $letter);
            
            $code = implode('', $digits);
            
            // Check for uniqueness in the database
            $exists = DB::connection('sqlsrv')->table('security_codes')
                ->where('code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }

    /**
     * Get or generate a security code for a document.
     *
     * @param string $fileNumber
     * @param int|string $documentId
     * @param string $documentType
     * @return object
     */
    public function getOrGenerateForDocument($fileNumber, $documentId, $documentType)
    {
        // Try to find an existing unused code for this document
        $existing = DB::connection('sqlsrv')->table('security_codes')
            ->where('file_number', $fileNumber)
            ->where('document_id', $documentId)
            ->where('document_type', $documentType)
            ->where('is_used', 0)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Generate a new code
        $code = $this->generateCode();
        
        // Get the latest paper code logic if applicable
        
        $newId = DB::connection('sqlsrv')->table('security_codes')->insertGetId([
            'code' => $code,
            'is_used' => 0,
            'file_number' => $fileNumber,
            'document_id' => $documentId,
            'document_type' => $documentType,
            'assigned_by' => Auth::id(),
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::connection('sqlsrv')->table('security_codes')->where('id', $newId)->first();
    }

    /**
     * Format the security code into parts for display.
     * Returns an array with 'alphabet' and 'digits'.
     *
     * @param string $code
     * @return array
     */
    public function formatForDisplay($code)
    {
        if (strlen($code) !== 9) {
            return [
                'alphabet' => '',
                'digits_start' => '',
                'digits_end' => $code
            ];
        }

        $alphabet = '';
        $allDigits = '';

        for ($i = 0; $i < strlen($code); $i++) {
            if (ctype_alpha($code[$i])) {
                $alphabet = $code[$i];
            } else {
                $allDigits .= $code[$i];
            }
        }

        return [
            'alphabet' => $alphabet,
            'digits_start' => substr($allDigits, 0, 2),
            'digits_end' => substr($allDigits, 2)
        ];
    }
}
