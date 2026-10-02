<?php

namespace App\Models\Phs;

use Illuminate\Database\Eloquent\Model;

class PhsFeedback extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'phs_feedback';

    protected $fillable = [
        'phs_institution_id',
        'phs_member_id',
        'category',
        'file_number',
        'reference_no',
        'subject',
        'message',
        'status',
        'admin_response',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /** Complaint categories presented to members, keyed by stored value. */
    public const CATEGORIES = [
        'incomplete_transaction' => 'Incomplete transaction',
        'wrong_transaction'      => 'Wrong / incorrect transaction',
        'missing_record'         => 'Missing record',
        'other'                  => 'Other',
    ];

    public function institution()
    {
        return $this->belongsTo(PhsInstitution::class, 'phs_institution_id');
    }

    public function member()
    {
        return $this->belongsTo(PhsMember::class, 'phs_member_id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucwords(str_replace('_', ' ', (string) $this->category));
    }
}
