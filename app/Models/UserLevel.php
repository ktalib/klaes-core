<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLevel extends Model
{
    use HasFactory;
    
    protected $connection = 'sqlsrv';
    protected $table = 'user_levels';
    
    protected $fillable = [
        'name',
        'code',
        'description',
        'user_type_id',
        'priority',
        'is_active',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'user_type_id' => 'integer',
    ];
    
    /**
     * Get the user type that owns this level
     */
    public function userType()
    {
        return $this->belongsTo(UserType::class);
    }
    
    /**
     * Scope to get only active user levels
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
        return $query->orderBy('priority', 'desc');
    }
    
    /**
     * Scope to filter by user type
     */
    public function scopeForUserType($query, $userTypeId)
    {
        return $query->where('user_type_id', $userTypeId);
    }
}