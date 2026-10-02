<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Title extends Model
{
    use HasFactory;
    
    protected $connection = 'sqlsrv';
    protected $table = 'titles';
    
    protected $fillable = [
        'title',
        'display_name',
        'is_active',
        'sort_order',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
    
    /**
     * Get active titles ordered by sort_order
     */
    public static function getActiveTitles()
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
    
    /**
     * Get titles for form dropdown
     */
    public static function getFormOptions()
    {
        return static::getActiveTitles()
            ->pluck('display_name', 'title')
            ->toArray();
    }
}
