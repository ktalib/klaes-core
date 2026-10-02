<?php

namespace App\Console\Commands;

use App\Enums\DocumentType;
use App\Services\DocumentQr\InvalidQrToken;
use App\Services\DocumentQr\QrPayloadReader;
use App\Services\DocumentQr\QrRenderer;
use App\Services\DocumentQr\QrTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Reports, in plain language, whether document QR signing actually works on
 * this box.
 *
 * This exists because .env is gitignored: after a code upload the signing key
 * is simply absent, and without a doctor the first symptom is a print failing
 * at a counter with a stack trace nobody can read.
 */
class QrDoctor extends Command
{
    protected $signature = 'qr:doctor {--generate-key : Print a fresh base64 32-byte key and exit}';

    protected $description = 'Check that document QR signing, rendering and storage are correctly configured';

    public function handle(QrTokenService $tokens, QrRenderer $renderer, QrPayloadReader $reader): int
    {
        if ($this->option('generate-key')) {
            $this->line(base64_encode(random_bytes(32)));
            $this->newLine();
            $this->comment('Add to .env as DOCUMENT_QR_KEY_1 (and set DOCUMENT_QR_ACTIVE_KEY=1).');
            $this->comment('Never commit this value, and never store it in the database.');

            return self::SUCCESS;
        }

        $this->info('KLAES Document QR — configuration check');
        $this->newLine();

        $ok = true;

        // 1. Cipher
        if ($tokens->cipherAvailable()) {
            $this->line('  <fg=green>OK</>    Cipher aes-256-gcm is available in OpenSSL.');
        } else {
            $ok = false;
            $this->line('  <fg=red>FAIL</>  Cipher aes-256-gcm is NOT available in this PHP build.');
            $this->line('        Tokens cannot be minted or verified. Enable the openssl extension.');
        }

        // 2. Keys
        $activeId = $tokens->activeKeyId();
        $keyIds   = $tokens->configuredKeyIds();

        if ($tokens->isConfigured()) {
            $this->line("  <fg=green>OK</>    Active signing key #{$activeId} is present and 32 bytes.");
        } else {
            $ok = false;
            $this->line("  <fg=red>FAIL</>  Active signing key #{$activeId} is missing or malformed.");
            $this->line("        Set DOCUMENT_QR_KEY_{$activeId} in .env to a base64-encoded 32-byte value.");
            $this->line('        Generate one with: php artisan qr:doctor --generate-key');
            $this->line('        This is expected on a fresh deploy — .env is gitignored and does');
            $this->line('        not travel with a code upload.');
        }

        if ($keyIds) {
            $this->line('  <fg=green>OK</>    Configured key ids: ' . implode(', ', $keyIds)
                . ' (old keys must stay configured forever so previously printed paper keeps verifying).');
        }

        // 3. Round trip
        if ($ok) {
            try {
                $token = $tokens->mint(123456, DocumentType::ROFO);
                $back  = $tokens->verify($token);

                if ($back && $back['document_qr_id'] === 123456 && $back['type'] === DocumentType::ROFO) {
                    $this->line('  <fg=green>OK</>    Mint/verify round trip succeeded (' . strlen($token) . ' chars).');
                } else {
                    $ok = false;
                    $this->line('  <fg=red>FAIL</>  Round trip returned unexpected data.');
                }

                // Tamper check — flip a character in the payload body.
                $tampered = substr($token, 0, -1) . (substr($token, -1) === 'A' ? 'B' : 'A');
                try {
                    $tokens->verify($tampered);
                    $ok = false;
                    $this->line('  <fg=red>FAIL</>  A tampered token was ACCEPTED. Do not print with this configuration.');
                } catch (InvalidQrToken) {
                    $this->line('  <fg=green>OK</>    Tampered token correctly rejected.');
                }
            } catch (\Throwable $e) {
                $ok = false;
                $this->line('  <fg=red>FAIL</>  Round trip threw: ' . $e->getMessage());
            }
        }

        // 4. Renderer
        if ($renderer->svg('KLAES-QR-DOCTOR') !== null) {
            $this->line('  <fg=green>OK</>    QR images render locally (bacon/bacon-qr-code, no outbound request).');
        } else {
            $ok = false;
            $this->line('  <fg=red>FAIL</>  Local QR rendering failed — check bacon/bacon-qr-code is installed.');
        }

        // 5. Tables
        foreach (['document_qr_codes', 'document_print_logs', 'document_scan_logs'] as $table) {
            if (Schema::connection('sqlsrv')->hasTable($table)) {
                $this->line("  <fg=green>OK</>    Table {$table} exists on sqlsrv.");
            } else {
                $ok = false;
                $this->line("  <fg=red>FAIL</>  Table {$table} is MISSING on sqlsrv.");
                $this->line('        The artisan ledger lives in MySQL while these tables are on');
                $this->line('        sqlsrv, so a migration can read as "run" while its DDL never');
                $this->line('        landed. Apply database/sql/2026_08_23_document_qr_tables.sql');
                $this->line('        and the paired *_ledger.mysql.sql.');
            }
        }

        // 6. Legacy decoder sanity — all four shapes found in the audit.
        $shapes = [
            '{"tracking_id":"TRK-260101120000-AB12-R010","file_number":"KN 8841"}' => QrPayloadReader::KIND_Q0_JSON,
            'https://klaes.test/verify-file/KN%208841/TRK-260101120000-AB12'       => QrPayloadReader::KIND_Q0_URL,
            'TRK-260101120000-AB12-R010'                                            => QrPayloadReader::KIND_Q0_TRACKING,
            'TRK-CEDEAA2B-B601D'                                                    => QrPayloadReader::KIND_Q0_TRACKING,
            'ABCD-EFGH2345'                                                         => QrPayloadReader::KIND_Q0_TRACKING,
            '179239'                                                                => QrPayloadReader::KIND_Q0_TRACKING,
            'N/A'                                                                   => QrPayloadReader::KIND_Q0_EMPTY,
        ];

        $legacyOk = true;
        foreach ($shapes as $payload => $expected) {
            if ($reader->read($payload)['kind'] !== $expected) {
                $legacyOk = false;
                $this->line('  <fg=red>FAIL</>  Legacy decoder misread: ' . substr($payload, 0, 48));
            }
        }
        if ($legacyOk) {
            $this->line('  <fg=green>OK</>    Legacy (Q0) decoder handles all printed shapes and tracking-ID grammars.');
        } else {
            $ok = false;
        }

        $this->newLine();

        if ($ok) {
            $this->info('All checks passed. Document QR signing is ready.');

            return self::SUCCESS;
        }

        $this->error('One or more checks failed — see above. Do not print Q1 QR codes until resolved.');

        return self::FAILURE;
    }
}
