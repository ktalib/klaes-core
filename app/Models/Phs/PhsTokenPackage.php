<?php

namespace App\Models\Phs;

use Illuminate\Database\Eloquent\Model;

class PhsTokenPackage extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'phs_token_packages';

    /** A bundle is a fixed quantity of tokens; tokens = bundles × this. */
    public const TOKENS_PER_BUNDLE = 1000;

    protected $fillable = [
        'name',
        'slug',
        'bundles',
        'tokens',
        'price',
        'team_members',
        'description',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'bundles' => 'integer',
        'tokens' => 'integer',
        'price' => 'decimal:2',
        'team_members' => 'integer',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)->orderBy('display_order')->orderBy('id');
    }
}
