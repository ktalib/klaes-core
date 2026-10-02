<?php

namespace App\Services\DocumentQr;

use App\Enums\DocumentType;
use RuntimeException;

/**
 * Mints and verifies KLAES-Q1 document tokens.
 *
 * Wire format (38 bytes -> 51 base64url chars -> 60 with the prefix, which is a
 * QR version 3 symbol at ECC-M and stays scannable printed at 20mm):
 *
 *   KLAES-Q1:<base64url( header ‖ nonce ‖ ciphertext ‖ tag )>
 *
 *   header      2 bytes   qr_version, key_id   (also passed as AEAD additional
 *                                               authenticated data, so the
 *                                               header cannot be swapped)
 *   nonce      12 bytes   random per token
 *   ciphertext  8 bytes   document_qr_id (5) ‖ document_type_code (1)
 *                         ‖ issued_epoch_days (2)
 *   tag        16 bytes   AES-256-GCM authentication tag
 *
 * The token deliberately carries NO file number, tracking ID, holder or URL.
 * Everything is resolved server-side from the document_qr_id.
 *
 * Laravel's Crypt::encryptString() was considered and rejected: it is properly
 * authenticated (AES-256-CBC + HMAC), but its base64'd JSON envelope runs 200+
 * characters for this payload, pushing the symbol to QR version 9 and hurting
 * scan reliability at small print sizes.
 */
class QrTokenService
{
    private const HEADER_BYTES = 2;
    private const NONCE_BYTES  = 12;
    private const TAG_BYTES    = 16;

    /** 2020-01-01, the origin for the compact 2-byte issued-date field. */
    private const EPOCH = 1577836800;

    /**
     * Build the printable token for a document_qr_codes row.
     */
    public function mint(int $documentQrId, DocumentType $type, ?int $issuedAt = null): string
    {
        if ($documentQrId < 1 || $documentQrId > 0xFFFFFFFFFF) {
            throw new RuntimeException('document_qr_id out of range for the 5-byte token field.');
        }

        $keyId = $this->activeKeyId();
        $key   = $this->key($keyId);

        $header = pack('CC', $this->version(), $keyId);

        // 5-byte big-endian id: pack() has no 40-bit type, so take the low 5
        // bytes of a 64-bit big-endian value.
        $idBytes = substr(pack('J', $documentQrId), 3, 5);

        $days = (int) floor((($issuedAt ?? time()) - self::EPOCH) / 86400);
        $days = max(0, min(0xFFFF, $days));

        $plain = $idBytes . pack('C', $type->code()) . pack('n', $days);

        // Deterministic (synthetic) nonce, NOT random.
        //
        // Approach A requires that every reprint of a document carry the SAME
        // QR string, and the row stores only a hash of the token, so minting
        // must be reproducible — a random nonce would emit a different token on
        // every call and no reprint would match the stored hash.
        //
        // Deriving the nonce from the key and the full plaintext is safe here:
        // GCM nonce reuse is only dangerous when two DIFFERENT messages share a
        // key and nonce, and distinct plaintexts always derive distinct nonces.
        $nonce = substr(
            hash_hmac('sha256', "klaes-qr-nonce\0" . $header . $plain, $key, true),
            0,
            self::NONCE_BYTES
        );

        $tag = '';

        $cipher = openssl_encrypt(
            $plain,
            $this->cipherName(),
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $header,
            self::TAG_BYTES
        );

        if ($cipher === false) {
            throw new RuntimeException('Failed to encrypt the QR token payload.');
        }

        return $this->prefix() . $this->b64UrlEncode($header . $nonce . $cipher . $tag);
    }

    /**
     * Decrypt and authenticate a token.
     *
     * Returns ['document_qr_id' => int, 'type' => DocumentType|null,
     *          'key_id' => int, 'issued_at' => int] on success.
     *
     * Returns null when the token is not a Q1 token at all (so the caller can
     * fall through to the legacy Q0 decoder); throws InvalidQrToken when it
     * looks like a Q1 token but fails authentication — that distinction is the
     * difference between "not found" and "tampered" on the console.
     */
    public function verify(string $token): ?array
    {
        $token = trim($token);

        if (! $this->looksLikeQ1($token)) {
            return null;
        }

        $raw = $this->b64UrlDecode(substr($token, strlen($this->prefix())));

        $minimum = self::HEADER_BYTES + self::NONCE_BYTES + self::TAG_BYTES + 1;
        if ($raw === false || strlen($raw) < $minimum) {
            throw new InvalidQrToken('Token payload is truncated or not valid base64url.');
        }

        $header  = substr($raw, 0, self::HEADER_BYTES);
        $parts   = unpack('Cversion/CkeyId', $header);
        $version = $parts['version'];
        $keyId   = $parts['keyId'];

        if ($version !== $this->version()) {
            throw new InvalidQrToken("Unsupported token version {$version}.");
        }

        $key = $this->keyOrNull($keyId);
        if ($key === null) {
            throw new InvalidQrToken("Token was signed with key {$keyId}, which is not configured on this server.");
        }

        $nonce  = substr($raw, self::HEADER_BYTES, self::NONCE_BYTES);
        $tag    = substr($raw, -self::TAG_BYTES);
        $cipher = substr(
            $raw,
            self::HEADER_BYTES + self::NONCE_BYTES,
            strlen($raw) - self::HEADER_BYTES - self::NONCE_BYTES - self::TAG_BYTES
        );

        $plain = openssl_decrypt(
            $cipher,
            $this->cipherName(),
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $header
        );

        if ($plain === false || strlen($plain) < 8) {
            throw new InvalidQrToken('Authentication tag rejected — the payload was altered or forged.');
        }

        $idBytes = substr($plain, 0, 5);
        $id      = unpack('J', str_repeat("\0", 3) . $idBytes)[1];

        $typeCode = unpack('C', substr($plain, 5, 1))[1];
        $days     = unpack('n', substr($plain, 6, 2))[1];

        return [
            'document_qr_id' => $id,
            'type'           => DocumentType::fromCode($typeCode),
            'key_id'         => $keyId,
            'issued_at'      => self::EPOCH + ($days * 86400),
        ];
    }

    public function looksLikeQ1(string $value): bool
    {
        return str_starts_with(strtoupper(trim($value)), strtoupper($this->prefix()));
    }

    /**
     * SHA-256 of the emitted token, stored on the row for lookup and for
     * detecting a duplicate mint. The plaintext token is never stored: a table
     * of valid tokens would be a forgery kit for anyone with read access.
     */
    public function hash(string $token): string
    {
        return hash('sha256', trim($token));
    }

    /* ------------------------------------------------------------------ */

    public function activeKeyId(): int
    {
        return (int) config('document_qr.active_key', 1);
    }

    public function isConfigured(): bool
    {
        return $this->keyOrNull($this->activeKeyId()) !== null;
    }

    public function cipherAvailable(): bool
    {
        return in_array($this->cipherName(), openssl_get_cipher_methods(), true);
    }

    public function configuredKeyIds(): array
    {
        $ids = [];
        foreach ((array) config('document_qr.keys', []) as $id => $value) {
            if (is_string($value) && $value !== '') {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    private function key(int $keyId): string
    {
        $key = $this->keyOrNull($keyId);

        if ($key === null) {
            // .env is gitignored, so a fresh code upload simply has no key.
            // Fail loudly here rather than emitting an unsigned QR onto paper.
            throw new RuntimeException(
                "The document QR signing key is not configured (DOCUMENT_QR_KEY_{$keyId} is missing from .env). "
                . 'Run "php artisan qr:doctor" for details.'
            );
        }

        return $key;
    }

    private function keyOrNull(int $keyId): ?string
    {
        $raw = config('document_qr.keys.' . $keyId);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = base64_decode($raw, true);

        return ($decoded !== false && strlen($decoded) === 32) ? $decoded : null;
    }

    private function cipherName(): string
    {
        return (string) config('document_qr.cipher', 'aes-256-gcm');
    }

    private function prefix(): string
    {
        return (string) config('document_qr.prefix', 'KLAES-Q1:');
    }

    private function version(): int
    {
        return (int) config('document_qr.version', 1);
    }

    private function b64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private function b64UrlDecode(string $text)
    {
        $padded = strtr($text, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);

        return base64_decode($padded, true);
    }
}
