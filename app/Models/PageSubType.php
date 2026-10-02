<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageSubType extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'PageSubType';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'PageTypeId',
        'PageSubType'
    ];

    protected $casts = [
        'id' => 'integer',
        'PageTypeId' => 'integer',
    ];

    // Relationship to page type
    public function pageType()
    {
        return $this->belongsTo(PageType::class, 'PageTypeId', 'id');
    }

    // Relationship to page typings
    public function pageTypings()
    {
        return $this->hasMany(PageTyping::class, 'page_subtype', 'id');
    }

    // Accessor for name
    public function getNameAttribute()
    {
        return $this->attributes['PageSubType'];
    }

    // Generate code from name
    public function getCodeAttribute()
    {
        $words = explode(' ', $this->PageSubType);
        $code = '';
        foreach ($words as $word) {
            $code .= strtoupper(substr($word, 0, 1));
        }
        return substr($code, 0, 4);
    }
}