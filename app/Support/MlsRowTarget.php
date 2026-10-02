<?php

namespace App\Support;

/**
 * Which physical record a row on the MLS File Commission list actually points at.
 *
 * That list is a UNION of three tables whose ids collide freely
 * (FileNumberController::getData): `fileNumber`, plus — in the "New" view — temporary
 * files whose id comes from `mls_file_no` and plot extensions whose id comes from
 * `plot_extensions`. `fileNumber.id` runs to six figures while `mls_file_no.id` and
 * `plot_extensions.id` start at 1, so a bare id is genuinely ambiguous: temporary file
 * RES-1993-2644(T) carries id 1166, and fileNumber 1166 is the unrelated live file
 * CON-AG-1987-57.
 *
 * Edit already disambiguated via an `entity` flag; Delete and Update-Allocation did not,
 * and resolved the bare id against `fileNumber` unconditionally — so a Master Delete on a
 * temporary row purged a different file from five tables. This class is the one place
 * that turns a row (or a checkbox value) into an unambiguous {entity, id} pair.
 *
 * Bare ids still resolve to `file_number`. That is deliberate: a page cached before this
 * shipped will keep posting them, and the *server* refuses the dangerous combinations
 * (see FileNumberController::destroy) rather than relying on the client to be current.
 */
class MlsRowTarget
{
    public const FILE_NUMBER    = 'file_number';
    public const TEMPORARY      = 'temporary';
    public const PLOT_EXTENSION = 'plot_extension';

    /** Row `type` values emitted by getData(), mapped to their backing table. */
    private const TYPE_MAP = [
        'plot extension' => self::PLOT_EXTENSION,
        'temporary'      => self::TEMPORARY,
    ];

    /** Single-letter prefixes used on checkbox values, mirroring the UNION's source_type. */
    private const PREFIX_MAP = [
        'P' => self::PLOT_EXTENSION,
        'T' => self::TEMPORARY,
        'F' => self::FILE_NUMBER,
    ];

    /**
     * Normalise whatever the client sent as an entity into one of the three constants.
     * Anything unrecognised — including null and '' — is a plain fileNumber row.
     */
    public static function entity(?string $entity): string
    {
        $entity = strtolower(trim((string) $entity));

        return in_array($entity, [self::TEMPORARY, self::PLOT_EXTENSION], true)
            ? $entity
            : self::FILE_NUMBER;
    }

    /**
     * Map a DataTable row's `type` column onto an entity. Used when wiring the row's
     * action buttons, so the client sends the same vocabulary the server checks.
     */
    public static function fromRowType(?string $rowType): string
    {
        return self::TYPE_MAP[strtolower(trim((string) $rowType))] ?? self::FILE_NUMBER;
    }

    /**
     * Parse one bulk-delete selection key.
     *
     * Current clients send "F:123" / "T:1166" / "P:4". Older cached pages send a bare
     * "123", which is read as a fileNumber row for backwards compatibility.
     *
     * @return array{entity: string, id: int}|null  null when the key names no usable id
     */
    public static function parseKey($key): ?array
    {
        $key = trim((string) $key);

        if ($key === '') {
            return null;
        }

        if (strpos($key, ':') !== false) {
            [$prefix, $rawId] = explode(':', $key, 2);
            $entity = self::PREFIX_MAP[strtoupper(trim($prefix))] ?? null;

            // An unknown prefix is a client we do not understand. Refuse it rather than
            // guessing "fileNumber" and deleting something.
            if ($entity === null) {
                return null;
            }
        } else {
            $entity = self::FILE_NUMBER;
            $rawId  = $key;
        }

        $rawId = trim($rawId);

        if ($rawId === '' || !ctype_digit($rawId) || (int) $rawId < 1) {
            return null;
        }

        return ['entity' => $entity, 'id' => (int) $rawId];
    }

    /**
     * Parse a whole selection, dropping unusable keys and de-duplicating.
     *
     * @param  iterable<mixed>  $keys
     * @return array{targets: array<int, array{entity: string, id: int}>, rejected: array<int, string>}
     */
    public static function parseKeys(iterable $keys): array
    {
        $targets  = [];
        $rejected = [];
        $seen     = [];

        foreach ($keys as $key) {
            $target = self::parseKey($key);

            if ($target === null) {
                $rejected[] = (string) $key;
                continue;
            }

            $dedupe = $target['entity'] . ':' . $target['id'];

            if (isset($seen[$dedupe])) {
                continue;
            }

            $seen[$dedupe] = true;
            $targets[]     = $target;
        }

        return ['targets' => $targets, 'rejected' => $rejected];
    }

    /**
     * Human label for a refusal message.
     */
    public static function label(string $entity): string
    {
        switch (self::entity($entity)) {
            case self::TEMPORARY:
                return 'Temporary file';
            case self::PLOT_EXTENSION:
                return 'Plot Extension';
            default:
                return 'MLS file record';
        }
    }
}
