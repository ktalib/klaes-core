<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    use HasFactory;
    
    protected $connection = 'sqlsrv';
    protected $table = 'user_roles';
    
    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'department_id',
        'level',
        'user_type',
        'is_active',
    ];
    
    /**
     * Get the department this role belongs to
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }
    
    /**
     * Get all users with this role
     */
    public function users()
    {
        return User::whereRaw("CHARINDEX(',$this->id,', CONCAT(',', assign_role, ',')) > 0")
                   ->orWhereRaw("assign_role = '$this->id'")
                   ->orWhereRaw("assign_role LIKE '$this->id,%'")
                   ->orWhereRaw("assign_role LIKE '%,$this->id'")
                   ->orWhereRaw("assign_role LIKE '%,$this->id,%'")
                   ->get();
    }
}
