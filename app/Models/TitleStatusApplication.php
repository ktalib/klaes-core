<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TitleStatusApplication extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'title_status_applications';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Withdrawal comes in two flavours, by what is being withdrawn:
     *   APPLICATION — the applicant withdraws their own pending application.
     *   ALLOCATION  — the Ministry withdraws an allocation already made.
     */
    public const TYPE_WITHDRAWAL   = 'Withdrawal (Application)';
    public const TYPE_WITHDRAWAL_ALLOCATION = 'Withdrawal (Allocation)';
    public const TYPE_CANCELLATION = 'Cancellation (RofO)';
    public const TYPE_REVOKE       = 'Revoke (CofO)';
    public const TYPE_LITIGATION   = 'Litigation';
    public const TYPE_AMENDMENT    = 'Amendment/Reconsideration (Application/RofO/CofO)';
    public const TYPE_SURRENDER    = 'Surrender';
    public const TYPE_REGRANT      = 'Re-grant';
    /**
     * Re-grant sub-kinds. Which side of the transition the file being recorded sits on:
     *   FROM — this file is the NEW file; `see_fileno` holds the OLD number it came from.
     *   TO   — this file is the OLD file; `see_fileno` holds the NEW number replacing it.
     * The bare TYPE_REGRANT is still written by older records and the commissioning path.
     */
    public const TYPE_REGRANT_FROM = 'Re-granted From';
    public const TYPE_REGRANT_TO   = 'Re-granted To';
    public const TYPE_RESETTLEMENT = 'Resettlement';
    /**
     * Resettlement sub-kinds, same shape as the Re-grant pair:
     *   FROM — this file is the NEW file; `see_fileno` holds the OLD number resettled from.
     *   TO   — this file is the OLD file; `see_fileno` holds the NEW number resettled to.
     * recordResettlement() already writes both wordings; these let File Indexing say which.
     */
    public const TYPE_RESETTLED_FROM = 'Resettled From';
    public const TYPE_RESETTLED_TO   = 'Resettled To';
    public const TYPE_SUBDIVISION  = 'Subdivision';
    public const TYPE_MERGER       = 'Merger';
    public const TYPE_PURPOSE      = 'Change of Purpose';
    public const TYPE_EXTENSION    = 'Extension';
    public const TYPE_CHANGE_NAME  = 'Change of Name';
    public const TYPE_SEPARATION   = 'Separation';
    public const TYPE_CLOSED       = 'Closed';
    /**
     * Closed sub-kinds. Which side of the closure the file being recorded sits on:
     *   CLOSED TO      — this file is being closed; `see_fileno` holds the file it continues in.
     *   CONTINUED FROM — this file is the continuation; `see_fileno` holds the closed file.
     * The bare TYPE_CLOSED is still written by older records.
     */
    public const TYPE_CLOSED_TO      = 'Closed To';
    public const TYPE_CONTINUED_FROM = 'Continued From';

    /** Every type that counts as a Re-grant, for querying the register. */
    public const REGRANT_TYPES = [
        self::TYPE_REGRANT,
        self::TYPE_REGRANT_FROM,
        self::TYPE_REGRANT_TO,
    ];

    /** Every type that counts as a Resettlement, in either direction. */
    public const RESETTLEMENT_TYPES = [
        self::TYPE_RESETTLEMENT,
        self::TYPE_RESETTLED_FROM,
        self::TYPE_RESETTLED_TO,
    ];

    /** Every type that counts as a file closure, in either direction. */
    public const CLOSED_TYPES = [
        self::TYPE_CLOSED,
        self::TYPE_CLOSED_TO,
        self::TYPE_CONTINUED_FROM,
    ];

    public const AUTHORITY_OPTIONS = [
        "Governor's Directive",
        "Ministerial Directive",
        "Court Order",
        "Administrative Decision",
        "Regulatory Notice",
        "Other",
    ];

    public const INITIATED_BY_OPTIONS = [
        'Ministry',
        'Allottee',
        'Applicant',
        'Court Order',
    ];

    /** Per-type options for the Initiated By dropdown. Single-option types lock the value. */
    public const INITIATED_BY_BY_TYPE = [
        self::TYPE_WITHDRAWAL   => ['Applicant'],
        self::TYPE_WITHDRAWAL_ALLOCATION => ['Ministry', 'Allottee'],
        self::TYPE_CANCELLATION => ['Ministry', 'Allottee'],
        self::TYPE_REVOKE       => ['Ministry', 'Allottee'],
        self::TYPE_LITIGATION   => ['Court Order'],
        self::TYPE_AMENDMENT    => ['Ministry', 'Allottee'],
        self::TYPE_SURRENDER    => ['Applicant'],
        self::TYPE_REGRANT      => ['Ministry'],
        self::TYPE_REGRANT_FROM => ['Ministry'],
        self::TYPE_REGRANT_TO   => ['Ministry'],
        self::TYPE_RESETTLEMENT   => ['Ministry', 'Allottee'],
        self::TYPE_RESETTLED_FROM => ['Ministry', 'Allottee'],
        self::TYPE_RESETTLED_TO   => ['Ministry', 'Allottee'],
        self::TYPE_SUBDIVISION  => ['Applicant', 'Ministry'],
        self::TYPE_MERGER       => ['Applicant', 'Ministry'],
        self::TYPE_PURPOSE      => ['Applicant', 'Ministry'],
        self::TYPE_EXTENSION    => ['Applicant', 'Ministry'],
        self::TYPE_CHANGE_NAME  => ['Applicant', 'Ministry'],
        self::TYPE_SEPARATION   => ['Applicant', 'Ministry'],
        self::TYPE_CLOSED          => ['Ministry'],
        self::TYPE_CLOSED_TO       => ['Ministry'],
        self::TYPE_CONTINUED_FROM  => ['Ministry'],
    ];

    protected $fillable = [
        'url',
        'title_type',
        'source_table',
        'source_id',
        'file_no',
        'see_fileno',
        'file_title',
        'applicant_name',
        'plot_no',
        'house_no',
        'street_name',
        'district',
        'lga',
        'state',
        'location',
        'land_use',
        'cofo_number',
        'title_no',
        'reg_page',
        'reg_volume',
        'date_of_issue',
        'date_of_expiry',
        'authority',
        'authority_reference',
        'initiated_by',
        'reason',
        'remark',
        'status',
        'captured_by',
        'updated_by',
        'approved_by',
        'approved_at',
        'is_deleted',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'date_of_issue'  => 'date',
        'date_of_expiry' => 'date',
        'approved_at'    => 'datetime',
    ];
}
