<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    
    protected $connection = 'sqlsrv';
    protected $table = 'departments';
    
    protected $fillable = [
        'name',
        'code',
        'description',
        'parent_id',
        'is_active',
    ];
    
    /**
     * Get the parent department
     */
    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }
    
    /**
     * Get the child departments
     */
    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id');
    }
    
    /**
     * Get the users in this department
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
    
    /**
     * Get the user roles associated with this department
     */
    public function userRoles()
    {
        return $this->hasMany(UserRole::class);
    }
}
