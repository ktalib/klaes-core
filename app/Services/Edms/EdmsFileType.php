<?php

namespace App\Services\Edms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * EdmsFileType
 *
 * The master folders that sit between a registry and its file-number folders in
 * every EDMS tree:
 *
 *   EDMS/{TREE}/{Registry_Slug}/{File_Type_Folder}/{FILE NUMBER}/{PAPER}/{file}
 *
 * Until now a registry folder held file-number folders directly ("naked", as the
 * registry calls it). A file's nature — regular, a subdivision mother, a merger
 * child, a temporary number — was nowhere on disk, so nobody browsing the tree
 * could tell them apart. These folders make that visible.
 *
 * NULL is a real, supported state: a file nobody has classified yet keeps the
 * legacy layout, directly under its registry. Nothing is moved until an operator
 * picks a type, so every path stored before this feature still resolves — see
 * EdmsDocumentPathResolver::candidates(), which offers both layouts.
 *
 * ── Three levels, picked in three dropdowns ──────────────────────────────────
 *
 * A type is a CATEGORY, a TYPE and (usually) a VARIANT:
 *
 *   Regular                                        -> Regular
 *   Parcel Update  › Subdivision                   -> Parcel_Update/Subdivision
 *   Title Status   › Regrant     › New             -> Title_Status/Regrant/New
 *
 * Regular is the whole answer on its own — it has no type or variant, which is
 * why the UI hides the second and third dropdowns once it is chosen.
 *
 * The third dropdown is per-type, not universal, and only Regrant and
 * Resettlement use it — they alone split into Old and New. Every other type is
 * a single folder, so the UI hides the variant control for all of them and the
 * choice is complete after two dropdowns.
 *
 * The key (`subdivision_mother`) is what the database stores; the folder
 * (`Parcel_Update/Subdivision/Mother`) is what the disk gets.
 *
 * ── Where the catalogue lives ────────────────────────────────────────────────
 *
 * The `edms_file_types` lookup table is the source of truth, so the registry can
 * add a type without a deploy. CATALOGUE below is the seed the migration writes
 * and the fallback used when the table is missing or empty — a fresh checkout,
 * or a database the migration has not reached yet, must still render the
 * dropdowns rather than offer an empty list. Reads are cached; call flush()
 * after writing to the table.
 *
 * NOTE: file_indexings already has an unrelated `file_type` column holding the
 * applicant type (Individual / Corporate / Government). This concept lives in
 * `edms_file_type` and the two must not be confused.
 */
class EdmsFileType
{
    /** The column that carries the type on file_indexings, scannings and pagetypings. */
    public const COLUMN = 'edms_file_type';

    /** The lookup table this catalogue is read from. */
    public const TABLE = 'edms_file_types';

    private const CONNECTION = 'sqlsrv';

    private const CACHE_KEY = 'edms.file_types.catalogue';

    /** Display order of the categories, and their headings. */
    private const CATEGORIES = [
        'regular'       => 'Regular',
        'parcel_update' => 'Parcel Update',
        'title_status'  => 'Title Status',
    ];

    /**
     * key => [category, type, type_label, variant, variant_label, label, folder, sort_order]
     *
     * Seed for `edms_file_types`, and the fallback when that table cannot be
     * read. Keys are stable: they are what lands in the database column, so a
     * renamed label must never change one.
     */
    private const CATALOGUE = [

        // -- Regular --
        'regular' => [
            'category'      => 'regular',
            'type'          => 'regular',
            'type_label'    => 'Regular',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Regular',
            'folder'        => 'Regular',
            'sort_order'    => 10,
        ],

        // -- Parcel Update --
        'subdivision' => [
            'category'      => 'parcel_update',
            'type'          => 'subdivision',
            'type_label'    => 'Subdivision',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Subdivision',
            'folder'        => 'Parcel_Update/Subdivision',
            'sort_order'    => 20,
        ],
        'merger' => [
            'category'      => 'parcel_update',
            'type'          => 'merger',
            'type_label'    => 'Merger',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Merger',
            'folder'        => 'Parcel_Update/Merger',
            'sort_order'    => 30,
        ],
        'extension' => [
            'category'      => 'parcel_update',
            'type'          => 'extension',
            'type_label'    => 'Extension',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Extension',
            'folder'        => 'Parcel_Update/Extension',
            'sort_order'    => 40,
        ],
        'separation' => [
            'category'      => 'parcel_update',
            'type'          => 'separation',
            'type_label'    => 'Separation',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Separation',
            'folder'        => 'Parcel_Update/Separation',
            'sort_order'    => 50,
        ],
        'temporary' => [
            'category'      => 'parcel_update',
            'type'          => 'temporary',
            'type_label'    => 'Temporary File',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Temporary File',
            'folder'        => 'Parcel_Update/Temporary_File',
            'sort_order'    => 60,
        ],
        'change_of_purpose' => [
            'category'      => 'parcel_update',
            'type'          => 'change_of_purpose',
            'type_label'    => 'Change of Purpose',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Change of Purpose',
            'folder'        => 'Parcel_Update/Change_of_Purpose',
            'sort_order'    => 70,
        ],

        // -- Title Status --
        'title_status_regrant_old' => [
            'category'      => 'title_status',
            'type'          => 'regrant',
            'type_label'    => 'Regrant',
            'variant'       => 'old',
            'variant_label' => 'Old',
            'label'         => 'Regrant — Old',
            'folder'        => 'Title_Status/Regrant/Old',
            'sort_order'    => 80,
        ],
        'title_status_regrant_new' => [
            'category'      => 'title_status',
            'type'          => 'regrant',
            'type_label'    => 'Regrant',
            'variant'       => 'new',
            'variant_label' => 'New',
            'label'         => 'Regrant — New',
            'folder'        => 'Title_Status/Regrant/New',
            'sort_order'    => 90,
        ],
        'title_status_resettlement_old' => [
            'category'      => 'title_status',
            'type'          => 'resettlement',
            'type_label'    => 'Resettlement',
            'variant'       => 'old',
            'variant_label' => 'Old',
            'label'         => 'Resettlement — Old',
            'folder'        => 'Title_Status/Resettlement/Old',
            'sort_order'    => 100,
        ],
        'title_status_resettlement_new' => [
            'category'      => 'title_status',
            'type'          => 'resettlement',
            'type_label'    => 'Resettlement',
            'variant'       => 'new',
            'variant_label' => 'New',
            'label'         => 'Resettlement — New',
            'folder'        => 'Title_Status/Resettlement/New',
            'sort_order'    => 110,
        ],
        'title_status_litigation' => [
            'category'      => 'title_status',
            'type'          => 'litigation',
            'type_label'    => 'Litigation',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Litigation',
            'folder'        => 'Title_Status/Litigation',
            'sort_order'    => 120,
        ],
        'title_status_amendment' => [
            'category'      => 'title_status',
            'type'          => 'amendment',
            'type_label'    => 'Amendment',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Amendment',
            'folder'        => 'Title_Status/Amendment',
            'sort_order'    => 130,
        ],
        'title_status_revocation' => [
            'category'      => 'title_status',
            'type'          => 'revocation',
            'type_label'    => 'Revocation',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Revocation',
            'folder'        => 'Title_Status/Revocation',
            'sort_order'    => 140,
        ],
        'title_status_withdrawal' => [
            'category'      => 'title_status',
            'type'          => 'withdrawal',
            'type_label'    => 'Withdrawal',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Withdrawal',
            'folder'        => 'Title_Status/Withdrawal',
            'sort_order'    => 150,
        ],
        'title_status_close' => [
            'category'      => 'title_status',
            'type'          => 'close',
            'type_label'    => 'Close',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Close',
            'folder'        => 'Title_Status/Close',
            'sort_order'    => 160,
        ],
        'title_status_cancellation' => [
            'category'      => 'title_status',
            'type'          => 'cancellation',
            'type_label'    => 'Cancellation',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Cancellation',
            'folder'        => 'Title_Status/Cancellation',
            'sort_order'    => 170,
        ],
        'title_status_surrender' => [
            'category'      => 'title_status',
            'type'          => 'surrender',
            'type_label'    => 'Surrender',
            'variant'       => null,
            'variant_label' => null,
            'label'         => 'Surrender',
            'folder'        => 'Title_Status/Surrender',
            'sort_order'    => 180,
        ],
    ];

    /**
     * Older or looser spellings that should land on a known key rather than mint
     * a folder of their own. Includes the folder names themselves — both the
     * current ones and the flat pre-category layout — so a value read back off
     * disk round-trips.
     *
     * The keys written before Regrant, Resettlement, Surrender, Separation and
     * Temporary File gained an Old/New variant resolve to Old: a file classified
     * back then was the already-existing file.
     */
    private const ALIASES = [
        // Flat folder spellings from the pre-category layout.
        'temporary_file'        => 'temporary',
        'temporary file'        => 'temporary',
        'temp'                  => 'temporary',
        'change of purpose'     => 'change_of_purpose',

        // Subdivision and Merger are one folder each now, so the Mother /
        // Children / New File forms they used to split into all fold back onto
        // the type.
        'subdivision/mother'    => 'subdivision',
        'subdivision/children'  => 'subdivision',
        'subdivision_mother'    => 'subdivision',
        'subdivision_children'  => 'subdivision',
        'merger/children'       => 'merger',
        'merger/new_file'       => 'merger',
        'merger new file'       => 'merger',
        'merger_children'       => 'merger',
        'merger_new_file'       => 'merger',
        'merger_new'            => 'merger',

        // Extension, Separation, Temporary File and Change of Purpose are one
        // folder each — no Old/New split — so any Old/New form of them, from a
        // folder or an earlier build, folds back onto the single type.
        'extension/old'                 => 'extension',
        'extension/new'                 => 'extension',
        'extension_old'                 => 'extension',
        'extension_new'                 => 'extension',
        'separation/old'                => 'separation',
        'separation/new'                => 'separation',
        'separation_old'                => 'separation',
        'separation_new'                => 'separation',
        'temporary_file/old'            => 'temporary',
        'temporary_file/new'            => 'temporary',
        'temporary_old'                 => 'temporary',
        'temporary_new'                 => 'temporary',
        'change_of_purpose/old'         => 'change_of_purpose',
        'change_of_purpose/new'         => 'change_of_purpose',
        'change_of_purpose_old'         => 'change_of_purpose',
        'change_of_purpose_new'         => 'change_of_purpose',
        'change of purpose old'         => 'change_of_purpose',
        'change of purpose new'         => 'change_of_purpose',

        // Only Regrant and Resettlement keep an Old/New split, and a value
        // written before they had one is the already-existing file, so it lands
        // on Old.
        'regrant'        => 'title_status_regrant_old',
        're-grant'       => 'title_status_regrant_old',
        're grant'       => 'title_status_regrant_old',
        'resettlement'   => 'title_status_resettlement_old',
        'surrender'      => 'title_status_surrender',

        // Litigation, Amendment, Revocation, Withdrawal, Close, Cancellation and
        // Surrender have no Old/New split — only Regrant and Resettlement do —
        // so any Old/New form of them, from a folder or an earlier build, folds
        // back onto the single type.
        'title_status/litigation/old'   => 'title_status_litigation',
        'title_status/litigation/new'   => 'title_status_litigation',
        'title_status/revocation/old'   => 'title_status_revocation',
        'title_status/revocation/new'   => 'title_status_revocation',
        'title_status/cancellation/old' => 'title_status_cancellation',
        'title_status/cancellation/new' => 'title_status_cancellation',
        'title_status/withdrawal/old'   => 'title_status_withdrawal',
        'title_status/withdrawal/new'   => 'title_status_withdrawal',
        'title_status/amendment/old'    => 'title_status_amendment',
        'title_status/amendment/new'    => 'title_status_amendment',
        'title_status/close/old'        => 'title_status_close',
        'title_status/close/new'        => 'title_status_close',
        'title_status/surrender/old'    => 'title_status_surrender',
        'title_status/surrender/new'    => 'title_status_surrender',
        'title_status_litigation_old'   => 'title_status_litigation',
        'title_status_litigation_new'   => 'title_status_litigation',
        'title_status_revocation_old'   => 'title_status_revocation',
        'title_status_revocation_new'   => 'title_status_revocation',
        'title_status_cancellation_old' => 'title_status_cancellation',
        'title_status_cancellation_new' => 'title_status_cancellation',
        'title_status_withdrawal_old'   => 'title_status_withdrawal',
        'title_status_withdrawal_new'   => 'title_status_withdrawal',
        'title_status_amendment_old'    => 'title_status_amendment',
        'title_status_amendment_new'    => 'title_status_amendment',
        'title_status_close_old'        => 'title_status_close',
        'title_status_close_new'        => 'title_status_close',
        'title_status_surrender_old'    => 'title_status_surrender',
        'title_status_surrender_new'    => 'title_status_surrender',

        // The bare words, since a Title Status folder is usually named by its
        // action alone ("Revocation") rather than by the group it sits in.
        'litigation'   => 'title_status_litigation',
        'revocation'   => 'title_status_revocation',
        'cancellation' => 'title_status_cancellation',
        'cancelation'  => 'title_status_cancellation',
        'withdrawal'   => 'title_status_withdrawal',
        'amendment'    => 'title_status_amendment',
        'close'        => 'title_status_close',
    ];

    /** In-request memo, so one request never queries the lookup table twice. */
    private static ?array $catalogue = null;

    /* ───────────────────────── catalogue loading ───────────────────────── */

    /**
     * The catalogue, keyed by canonical key, in display order.
     *
     * Read from `edms_file_types` and cached. Any failure — table not created
     * yet, connection down, cache driver unavailable — falls back to CATALOGUE
     * rather than returning nothing: an empty File Type dropdown would look like
     * the feature is broken, and would silently stop classifying files.
     *
     * @return array<string, array{category:string,type:string,type_label:string,variant:?string,variant_label:?string,label:string,folder:string,sort_order:int}>
     */
    public static function all(): array
    {
        if (self::$catalogue !== null) {
            return self::$catalogue;
        }

        try {
            $rows = Cache::rememberForever(self::CACHE_KEY, fn () => self::readTable());
        } catch (Throwable $e) {
            $rows = self::readTable();
        }

        return self::$catalogue = (is_array($rows) && $rows !== []) ? $rows : self::CATALOGUE;
    }

    /** @return array<string, array<string, mixed>> the lookup table, or [] when unreadable */
    private static function readTable(): array
    {
        try {
            if (!Schema::connection(self::CONNECTION)->hasTable(self::TABLE)) {
                return [];
            }

            $rows = DB::connection(self::CONNECTION)
                ->table(self::TABLE)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get();
        } catch (Throwable $e) {
            return [];
        }

        $catalogue = [];

        foreach ($rows as $row) {
            $key = trim((string) ($row->code ?? ''));
            if ($key === '') {
                continue;
            }

            $catalogue[$key] = [
                'category'      => (string) $row->category,
                'type'          => (string) $row->type,
                'type_label'    => (string) $row->type_label,
                'variant'       => ($row->variant === null || $row->variant === '') ? null : (string) $row->variant,
                'variant_label' => ($row->variant_label === null || $row->variant_label === '') ? null : (string) $row->variant_label,
                'label'         => (string) $row->label,
                'folder'        => (string) $row->folder,
                'sort_order'    => (int) $row->sort_order,
            ];
        }

        return $catalogue;
    }

    /** Drop the cached catalogue — call after any write to `edms_file_types`. */
    public static function flush(): void
    {
        self::$catalogue = null;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable $e) {
            // A missing cache driver is not a reason to fail the write.
        }
    }

    /**
     * The seed rows for `edms_file_types`, ready for an insert.
     *
     * Used by the migration so the table and the fallback can never disagree.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function seedRows(): array
    {
        $rows = [];

        foreach (self::CATALOGUE as $key => $meta) {
            $rows[] = [
                'code'           => $key,
                'category'       => $meta['category'],
                'category_label' => self::CATEGORIES[$meta['category']] ?? $meta['category'],
                'type'           => $meta['type'],
                'type_label'     => $meta['type_label'],
                'variant'        => $meta['variant'],
                'variant_label'  => $meta['variant_label'],
                'label'          => $meta['label'],
                'folder'         => $meta['folder'],
                'sort_order'     => $meta['sort_order'],
                'is_active'      => 1,
            ];
        }

        return $rows;
    }

    /* ───────────────────────────── lookups ─────────────────────────────── */

    /** @return string[] every valid key, in display order */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * Every folder segment, deepest form — "Regular",
     * "Parcel_Update/Subdivision/Mother", …
     *
     * @return string[]
     */
    public static function folders(): array
    {
        return array_values(array_column(self::all(), 'folder'));
    }

    /**
     * The complete folder skeleton one registry needs, parents included and
     * ordered shallowest-first so a plain foreach can mkdir straight through.
     *
     * @return string[]
     */
    public static function folderSkeleton(): array
    {
        $folders = [];

        foreach (self::folders() as $folder) {
            $parts = explode('/', $folder);
            $path = '';
            foreach ($parts as $part) {
                $path = $path === '' ? $part : $path . '/' . $part;
                $folders[$path] = true;
            }
        }

        return array_keys($folders);
    }

    /**
     * Resolve any stored value (key, label or folder, in any case) to a canonical
     * key, or null when it is empty or unrecognised.
     *
     * Unrecognised is deliberately null rather than an exception: an unclassified
     * file is the normal state, and a junk value must not strand documents in a
     * folder no reader knows about.
     */
    public static function normalize($value): ?string
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $types = self::all();
        $key = strtolower($raw);

        if (isset($types[$key])) {
            return $key;
        }

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        // The current folder path, which carries the category segment.
        foreach ($types as $candidate => $meta) {
            if ($key === strtolower($meta['folder'])) {
                return $candidate;
            }
        }

        // Labels use an em dash; accept a hyphen or any spacing the UI may send.
        $flat = self::flatten($raw);
        foreach ($types as $candidate => $meta) {
            if ($flat === self::flatten($meta['label'])) {
                return $candidate;
            }
        }

        return null;
    }

    /** Case- and punctuation-insensitive form used for loose label matching. */
    private static function flatten(string $value): string
    {
        return strtolower(preg_replace('/\s+/', ' ', str_replace(['—', '–', '-', '_', '/'], ' ', $value)));
    }

    public static function isValid($value): bool
    {
        return self::normalize($value) !== null;
    }

    /** Human label — "Subdivision — Mother" — or null for an unclassified file. */
    public static function label($value): ?string
    {
        $meta = self::meta($value);

        return $meta === null ? null : $meta['label'];
    }

    /**
     * Folder segment for a type, or null when unclassified — and null means
     * "no segment at all", i.e. the legacy layout directly under the registry.
     */
    public static function folder($value): ?string
    {
        $meta = self::meta($value);

        return $meta === null ? null : $meta['folder'];
    }

    /**
     * The display heading a type is listed under — its category label, or null
     * for Regular, which stands alone.
     */
    public static function group($value): ?string
    {
        $meta = self::meta($value);

        if ($meta === null || $meta['category'] === 'regular') {
            return null;
        }

        return self::CATEGORIES[$meta['category']] ?? null;
    }

    /** The category key ('regular' | 'parcel_update' | 'title_status'), or null. */
    public static function category($value): ?string
    {
        $meta = self::meta($value);

        return $meta === null ? null : $meta['category'];
    }

    /** The type key within the category ('subdivision', 'regrant', …), or null. */
    public static function type($value): ?string
    {
        $meta = self::meta($value);

        return $meta === null ? null : $meta['type'];
    }

    /** The variant key ('old', 'new', 'mother', …), or null when there is none. */
    public static function variant($value): ?string
    {
        $meta = self::meta($value);

        return $meta === null ? null : $meta['variant'];
    }

    /** The full catalogue row for a value, or null when unrecognised. */
    public static function meta($value): ?array
    {
        $key = self::normalize($value);

        return $key === null ? null : self::all()[$key];
    }

    /**
     * Resolve a category / type / variant triple — what the three dropdowns
     * post — to a canonical key, or null when the combination does not exist.
     *
     * Regular needs only its category, so a blank type and variant are accepted
     * there and nowhere else.
     */
    public static function fromParts(?string $category, ?string $type = null, ?string $variant = null): ?string
    {
        $category = strtolower(trim((string) $category));
        if ($category === '') {
            return null;
        }

        $type = strtolower(trim((string) $type));
        $variant = strtolower(trim((string) $variant));

        foreach (self::all() as $key => $meta) {
            if ($meta['category'] !== $category) {
                continue;
            }
            if ($type !== '' && $meta['type'] !== $type) {
                continue;
            }
            if ($type === '' && $meta['type'] !== $category) {
                // Only a category that is its own single type (Regular) resolves
                // without one; anything else is an incomplete selection.
                continue;
            }
            if (($meta['variant'] ?? '') !== ($variant === '' ? '' : $variant)) {
                continue;
            }

            return $key;
        }

        return null;
    }

    /* ────────────────────────── UI catalogues ──────────────────────────── */

    /**
     * The categories for the first dropdown.
     *
     * `has_children` tells the UI whether picking it should reveal the Type and
     * Variant dropdowns — false for Regular, which is a complete answer.
     *
     * @return array<int, array{key:string, label:string, has_children:bool}>
     */
    public static function categories(): array
    {
        $categories = [];

        foreach (self::all() as $meta) {
            $key = $meta['category'];
            if (!isset($categories[$key])) {
                $categories[$key] = [
                    'key'          => $key,
                    'label'        => self::CATEGORIES[$key] ?? $key,
                    'has_children' => false,
                ];
            }

            // A category whose only type is itself (Regular) needs no second
            // dropdown; anything with a distinct type or a variant does.
            if ($meta['type'] !== $key || $meta['variant'] !== null) {
                $categories[$key]['has_children'] = true;
            }
        }

        return array_values($categories);
    }

    /**
     * The whole catalogue shaped for the cascading dropdowns:
     *
     *   [ category_key => [ 'label' => …, 'types' => [
     *         type_key => [ 'label' => …, 'variants' => [
     *             [ 'key' => variant_key|'', 'label' => …, 'code' => canonical key ]
     *         ] ]
     *   ] ] ]
     *
     * A type whose single variant has key '' has no third dropdown.
     *
     * @return array<string, array{label:string, has_children:bool, types:array<string, array{label:string, variants:array<int, array{key:string,label:string,code:string}>}>}>
     */
    public static function tree(): array
    {
        $tree = [];

        foreach (self::all() as $code => $meta) {
            $category = $meta['category'];
            $type = $meta['type'];

            if (!isset($tree[$category])) {
                $tree[$category] = [
                    'label'        => self::CATEGORIES[$category] ?? $category,
                    'has_children' => false,
                    'types'        => [],
                ];
            }

            if (!isset($tree[$category]['types'][$type])) {
                $tree[$category]['types'][$type] = [
                    'label'    => $meta['type_label'],
                    'variants' => [],
                ];
            }

            $tree[$category]['types'][$type]['variants'][] = [
                'key'   => $meta['variant'] ?? '',
                'label' => $meta['variant_label'] ?? $meta['type_label'],
                'code'  => $code,
            ];

            if ($type !== $category || $meta['variant'] !== null) {
                $tree[$category]['has_children'] = true;
            }
        }

        return $tree;
    }

    /**
     * The flat catalogue, as the older single-select UIs and the transfer modal
     * still want it.
     *
     * @return array<int, array{key:string, label:string, folder:string, group:?string, category:string, type:string, variant:?string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $key => $meta) {
            $options[] = [
                'key'      => $key,
                'label'    => $meta['label'],
                'folder'   => $meta['folder'],
                'group'    => $meta['category'] === 'regular'
                    ? null
                    : (self::CATEGORIES[$meta['category']] ?? null),
                'category' => $meta['category'],
                'type'     => $meta['type'],
                'variant'  => $meta['variant'],
            ];
        }

        return $options;
    }

    /** Validation rule body for `in:` — the canonical keys only. */
    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::keys());
    }
}
