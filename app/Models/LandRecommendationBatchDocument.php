<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * The mother's scanned recommendation letter for a subdivision batch.
 *
 * A subdivision's children inherit the mother's recommendation instead of earning
 * one each, so no letter is printed for a child. The mother's signed letter is
 * scanned once, hung off the batch, and every child in that batch views this one
 * document.
 *
 * One row per rofo_batch_id — see the unique index. A re-upload replaces the row
 * (and the file behind it) rather than adding a second.
 *
 * @see \App\Http\Controllers\LandRecommendationBatchDocumentController
 */
class LandRecommendationBatchDocument extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'land_recommendation_batch_documents';

    /**
     * Where scans used to live, relative to the 'public' disk.
     *
     * Kept because rows written before the move still point here, and
     * absolutePath() still finds them. Nothing new is written to it: the letter
     * belongs in the file's own EDMS folder, alongside everything else ever
     * scanned against that file number.
     */
    public const DIRECTORY = 'land_recommendations/batch_documents';
    /**
     * The key a "Use Subdivision Template" record files its mother's letter under.
     *
     * That option makes ONE file print as a subdivision child without creating a
     * batch -- so it has no rofo_batch_id to hang a document off, and the mother it
     * names lives in its own old_file_number rather than in batch_mother_file_no.
     * Rather than make rofo_batch_id nullable and teach every lookup a second way
     * to find a row, such a record is addressed by a key built from its id. The
     * column stays NOT NULL and every existing query keeps working unchanged.
     *
     * The prefix is what tells the two apart: a real batch id is a uuid.
     */
    public const SUBDIVISION_TEMPLATE_PREFIX = 'SUBTPL-';

    /**
     * The document key for one Use Subdivision Template record.
     *
     * A file merged from two or more parents inherits a recommendation from EACH of
     * them, so one record can carry several letters -- one per parent file. The
     * slot number separates them, and slot 1 keeps the bare key it has always had
     * so rows written before mergers were supported still resolve.
     *
     * rofo_batch_id is uniquely indexed, which is what makes each slot a row of its
     * own rather than a replace of the last one.
     */
    public static function subdivisionTemplateKey(int $recommendationId, int $slot = 1): string
    {
        $key = self::SUBDIVISION_TEMPLATE_PREFIX . $recommendationId;

        return $slot > 1 ? $key . '-' . $slot : $key;
    }

    /**
     * Every key belonging to one record: the bare slot-1 key, and the suffixed
     * slots beside it. Written as a SQL fragment so the test can be pushed into a
     * query. The hyphen is what stops SUBTPL-4362 matching SUBTPL-43628.
     */
    public static function subdivisionTemplateWhere($query, string $column, int $recommendationId)
    {
        $key = self::subdivisionTemplateKey($recommendationId);

        return $query->where(function ($q) use ($column, $key) {
            $q->where($column, $key)
              ->orWhere($column, 'LIKE', $key . '-%');
        });
    }

    /** Documents held against one Use Subdivision Template record, in slot order. */
    public static function forSubdivisionTemplate(int $recommendationId)
    {
        return self::subdivisionTemplateWhere(self::query(), 'rofo_batch_id', $recommendationId)
            ->orderBy('id')
            ->get();
    }

    /**
     * The next free slot for a record. Slots are never reused after a delete -- a
     * gap is cheaper than two letters briefly sharing a key.
     */
    public static function nextSubdivisionTemplateSlot(int $recommendationId): int
    {
        $used = self::subdivisionTemplateWhere(self::query(), 'rofo_batch_id', $recommendationId)
            ->pluck('rofo_batch_id')
            ->map(fn ($key) => self::subdivisionTemplateSlot((string) $key))
            ->filter()
            ->all();

        return $used ? max($used) + 1 : 1;
    }

    /** The slot a key names, or null when it is not one of ours. */
    public static function subdivisionTemplateSlot(string $key): ?int
    {
        if (!preg_match('/^' . preg_quote(self::SUBDIVISION_TEMPLATE_PREFIX, '/') . '(\d+)(?:-(\d+))?$/', trim($key), $m)) {
            return null;
        }

        return isset($m[2]) ? (int) $m[2] : 1;
    }

    /**
     * The recommendation id behind such a key, or null when this is an ordinary
     * batch id. Used to route a lookup to the right one of the two.
     */
    public static function subdivisionTemplateId(string $key): ?int
    {
        if (!preg_match('/^' . preg_quote(self::SUBDIVISION_TEMPLATE_PREFIX, '/') . '(\d+)(?:-\d+)?$/', trim($key), $m)) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * The subfolder inside the file's EDMS folder. A file number's folder is shared
     * with its scanned pages (A4/A3), so the letter is filed under a name that says
     * what it is instead of being dropped in among them.
     */
    public const EDMS_CATEGORY = 'Recommendation';

    protected $fillable = [
        'rofo_batch_id',
        'mother_file_no',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'size_bytes'  => 'integer',
    ];

    /**
     * Browser-facing URL for the stored scan, through the public/storage symlink.
     *
     * NOT how the letter is displayed. The batch-document.show route streams the
     * bytes instead, which is what lets the scan sit on the EDMS drive rather than
     * under the symlink, and keeps it off a URL built from a stale APP_URL.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * The scan's absolute path on disk, or null when the file is not there.
     *
     * Two roots are tried, because two eras of rows exist and because the EDMS tree
     * need not sit on the same volume as the application:
     *
     *   1. file_storage_path() — honours STORAGE_PATH in .env, which is how EDMS is
     *      pointed at the F: drive on production. Identical to (2) when STORAGE_PATH
     *      is unset, which is the local case, so this costs nothing in dev.
     *   2. the 'public' disk — where everything uploaded before the move to EDMS
     *      still lives.
     *
     * Resolved at read time rather than stored, so moving the EDMS tree to another
     * drive does not orphan a single row.
     */
    public function absolutePath(): ?string
    {
        $relative = ltrim((string) $this->path, '/\\');

        if ($relative === '') {
            return null;
        }

        foreach ($this->candidatePaths($relative) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Where the scan would be written now. Used when creating or deleting, where
     * the file's existence is not the question being asked.
     */
    public function preferredAbsolutePath(): ?string
    {
        $relative = ltrim((string) $this->path, '/\\');

        return $relative === '' ? null : ($this->candidatePaths($relative)[0] ?? null);
    }

    /** @return array<int,string> */
    private function candidatePaths(string $relative): array
    {
        $paths = [];

        if (function_exists('file_storage_path')) {
            $paths[] = file_storage_path('app/public/' . $relative);
        }

        $paths[] = Storage::disk('public')->path($relative);
        // Historical uploads can remain in the app's local storage after the
        // configured public disk has moved to the dedicated EDMS drive.
        $paths[] = base_path('storage/app/public/' . $relative);

        return array_values(array_unique($paths));
    }

    /**
     * Does the viewer's browser render this inline, or does it need a download?
     * Images and PDFs open in a tab; anything else is handed over as a file.
     */
    public function isViewableInline(): bool
    {
        $mime = strtolower((string) $this->mime_type);

        return str_starts_with($mime, 'image/') || $mime === 'application/pdf';
    }

    /**
     * What the file is, for a label: "JPG · 1.2 MB".
     */
    public function summary(): string
    {
        $extension = strtoupper(pathinfo((string) $this->path, PATHINFO_EXTENSION));
        $bytes = (int) $this->size_bytes;

        if ($bytes <= 0) {
            return $extension;
        }

        $size = $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : max(1, (int) round($bytes / 1024)) . ' KB';

        return trim($extension . ' · ' . $size, ' ·');
    }
}
