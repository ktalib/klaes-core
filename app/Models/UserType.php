<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    use HasFactory;
    
    protected $connection = 'sqlsrv';
    protected $table = 'user_types';
    
    protected $fillable = [
        'name',
        'code',
        'description',
        'level_priority',
        'is_active',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'level_priority' => 'integer',
    ];
    
    /**
     * Get the user levels for this user type
     */
    public function userLevels()
    {
        return $this->hasMany(UserLevel::class)->orderBy('priority', 'desc');
    }
    
    /**
     * Get active user levels for this user type
     */
    public function activeUserLevels()
    {
        return $this->hasMany(UserLevel::class)->where('is_active', 1)->orderBy('priority', 'desc');
    }
    
    /**
     * Scope to get only active user types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
    
    /**
     * Scope to order by priority
     */
    public function scopeOrderByPriority($query)
    {
        return $query->orderBy('level_priority', 'desc');
    }
}