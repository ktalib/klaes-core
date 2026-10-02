<?php

namespace App\Models;

use App\Models\OnlineLegalSearch\OnlineLsUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalSearchOnlinePayment extends Model
{
    // The Online Legal Search portal lives on the sqlsrv connection
    // (alongside online_ls_users and online_ls_search_logs).
    protected $connection = 'sqlsrv';

    protected $fillable = [
        'reference',
        'tracking_id',
        'user_id',
        'online_ls_user_id',
        'email',
        'file_number',
        'file_numbers',
        'file_count',
        'search_params',
        'amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'search_params' => 'array',
        'file_numbers'  => 'array',
        'file_count'    => 'integer',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function onlineLsUser(): BelongsTo
    {
        return $this->belongsTo(OnlineLsUser::class, 'online_ls_user_id');
    }

    /**
     * The identification submitted for this payment. One per payment, however many
     * files it covers — the same applicant is identified once for the whole basket.
     */
    public function verification(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LegalSearchOnlineVerification::class, 'payment_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Every file this payment covers, primary first.
     *
     * Falls back to the single `file_number` for rows written before a payment
     * could cover several files, so callers never have to special-case them.
     *
     * @return array<int, string>
     */
    public function fileNumbers(): array
    {
        $files = array_values(array_filter(
            (array) ($this->file_numbers ?? []),
            fn ($f) => trim((string) $f) !== ''
        ));

        if (!empty($files)) {
            return $files;
        }

        $single = trim((string) $this->file_number);

        return $single !== '' ? [$single] : [];
    }

    /** True when this payment bought more than one Legal Search report. */
    public function isMultiFile(): bool
    {
        return count($this->fileNumbers()) > 1;
    }
}
