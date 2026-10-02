<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One version of a file's captured state.
 *
 * APPEND-ONLY. Rows are written by App\Services\FileSnapshotService and never
 * updated or deleted — a change to the file produces a NEW version, which is the
 * entire point of the table. Nothing in the app should call save() on a loaded
 * FileSnapshot; if you find yourself wanting to, you want a new snapshot instead.
 *
 * @property array|null $payload  full readable state of the file at this version
 * @property array|null $changes  diff against the previous version; null on v1
 */
class FileSnapshot extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'file_snapshots';

    protected $fillable = [
        'file_indexing_id',
        'file_number',
        'temp_file_no',
        'tracking_id',
        'prop_id',
        'parent_prop_id',
        'version',
        'event_type',
        'event_label',
        'payload',
        'changes',
        'changed_field_count',
        'payload_hash',
        'performed_by',
        'performed_by_name',
        'performed_at',
        'source',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'payload' => 'array',
        'changes' => 'array',
        'version' => 'integer',
        'changed_field_count' => 'integer',
        'performed_at' => 'datetime',
    ];

    /** Event types, in the order they typically occur on a file's life. */
    public const EVENT_INDEXED = 'indexed';
    public const EVENT_EDITED = 'edited';
    public const EVENT_LINKED = 'linked';
    public const EVENT_TRANSACTION_ADDED = 'transaction_added';

    /**
     * The shape the front-end cards read. Kept here rather than in the controller
     * so the post-save response and the /snapshot re-fetch route cannot drift.
     *
     * @return array<string,mixed>
     */
    public function toCardPayload(): array
    {
        return [
            'id' => $this->id,
            'file_indexing_id' => $this->file_indexing_id,
            'file_number' => $this->file_number,
            'temp_file_no' => $this->temp_file_no,
            'tracking_id' => $this->tracking_id,
            'version' => (int) $this->version,
            'event_type' => $this->event_type,
            'event_label' => $this->event_label,
            'changed_field_count' => (int) $this->changed_field_count,
            'performed_by_name' => $this->performed_by_name,
            'performed_at' => optional($this->performed_at)->toDateTimeString(),
            'payload' => $this->payload ?: [],
            'changes' => $this->changes ?: [],
        ];
    }
}
