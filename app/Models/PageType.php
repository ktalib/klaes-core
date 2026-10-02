<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageType extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'PageType';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'PageType'
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    // Relationship to page subtypes
    public function pageSubTypes()
    {
        return $this->hasMany(PageSubType::class, 'PageTypeId', 'id');
    }

    // Relationship to page typings
    public function pageTypings()
    {
        return $this->hasMany(PageTyping::class, 'page_type', 'id');
    }

    // Accessor for name
    public function getNameAttribute()
    {
        return $this->attributes['PageType'];
    }

    // Generate code from name
    public function getCodeAttribute()
    {
        $words = explode(' ', $this->PageType);
        $code = '';
        foreach ($words as $word) {
            $code .= strtoupper(substr($word, 0, 1));
        }
        return substr($code, 0, 4);
    }
}