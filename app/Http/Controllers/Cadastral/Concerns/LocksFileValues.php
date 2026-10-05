<?php

namespace App\Http\Controllers\Cadastral\Concerns;

use App\Models\Cadastral\CadastralFileReceipt;
use App\Services\Cadastral\CadastralRegistryLookup;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The server half of the shared file picker (partials/_file_picker).
 *
 * The picker greys out every field the file's records supply; this puts the
 * same values into the request before validation, so what the records say
 * wins over anything posted (a forged or re-enabled field changes nothing)
 * and a required rule is still satisfied by a value the browser never sent.
 * Fields the records leave blank keep the clerk's input.
 */
trait LocksFileValues
{
    /** Receipts in these states cannot be built on. */
    private static array $deadReceipts = ['Rejected', 'Returned'];

    /**
     * Merge the file's values over the request.
     *
     * @param  string[]  $fields  the form fields the records may supply
     * @param  array     $fixed   values that are never the clerk's (file_number …)
     * @return array  the full value set (CadastralRegistryLookup::fileValues)
     */
    protected function lockFromFile(Request $r, array $fields, ?CadastralFileReceipt $receipt = null, ?int $indexingId = null, array $fixed = []): array
    {
        $lookup = app(CadastralRegistryLookup::class);
        $source = $lookup->sourceFile(null, $indexingId ?: $receipt?->file_indexing_id);
        $values = $lookup->fileValues($source, $receipt);

        $r->merge(CadastralRegistryLookup::lockedInput($values, $fields) + $fixed);

        return $values;
    }

    /** A registered intake receipt that has not been returned or rejected, or a validation error. */
    protected function requireRegisteredReceipt($id, string $doing): CadastralFileReceipt
    {
        $receipt = $id ? CadastralFileReceipt::find((int) $id) : null;

        if (! $receipt || ! $receipt->registered_at || in_array($receipt->status, self::$deadReceipts, true)) {
            throw ValidationException::withMessages([
                'cadastral_file_receipt_id' => $id
                    ? "Only a file registered at intake can {$doing}. Select it again with the file-number selector."
                    : 'Select the file with the file-number selector first.',
            ]);
        }

        return $receipt;
    }

    /** The Cadastral single-value address rules (CadastralAddress::rules). */
    protected function addressRules(Request $r, string $prefix = 'prop_', bool $required = true): array
    {
        return \App\Services\Cadastral\CadastralAddress::rules($prefix, $required);
    }

    /** The Cadastral address messages (CadastralAddress::messages). */
    protected function addressMessages(string $prefix = 'prop_'): array
    {
        return \App\Services\Cadastral\CadastralAddress::messages($prefix);
    }

    /** The latest registered, live receipt for a file number, if any. */
    protected function latestRegisteredReceipt(?string $fileNumber): ?CadastralFileReceipt
    {
        if (trim((string) $fileNumber) === '') {
            return null;
        }

        return CadastralFileReceipt::query()
            ->where('file_number', $fileNumber)
            ->whereNotNull('registered_at')
            ->whereNotIn('status', self::$deadReceipts)
            ->orderByDesc('id')
            ->first();
    }
}
