<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalSearchOnlineFeedback extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'legal_search_online_feedback';

    protected $fillable = [
        'user_id',
        'online_ls_user_id',
        'legal_search_online_payment_id',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'reference',
        'status',
        'admin_response',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'open'        => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-50 text-red-700 ring-1 ring-red-200">Open</span>',
            'in_progress' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-700 ring-1 ring-amber-200">In Progress</span>',
            'resolved'    => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">Resolved</span>',
            default       => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-500">Unknown</span>',
        };
    }
}
