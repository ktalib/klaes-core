<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoverType extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'CoverType';
    protected $primaryKey = 'Id';
    
    protected $fillable = [
        'Name',
        'Description',
    ];

    public $timestamps = false; // CoverType table might not have timestamps

    /**
     * Get page typings that use this cover type
     */
    public function pageTypings()
    {
        return $this->hasMany(PageTyping::class, 'cover_type_id', 'Id');
    }

    /**
     * Generate cover type code (FC for Front Cover, BC for Back Cover)
     */
    public function getCodeAttribute()
    {
        $name = $this->Name;
        if (stripos($name, 'front') !== false) {
            return 'FC';
        } elseif (stripos($name, 'back') !== false) {
            return 'BC';
        } else {
            // Generate code from first letters of words
            $words = explode(' ', $name);
            return strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
        }
    }
}