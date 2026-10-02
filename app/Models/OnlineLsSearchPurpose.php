<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A purpose a member of the public may give for an Online Legal Search.
 *
 * The public portal will not accept a purpose outside this lookup — the select
 * constrains the browser, and the payment verification endpoint re-checks the
 * submitted id against the active rows here.
 */
class OnlineLsSearchPurpose extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'online_ls_search_purposes';

    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * The list shown on the public payment card, in display order.
     */
    public static function options()
    {
        return static::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description']);
    }
}
