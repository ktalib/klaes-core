<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtherReceivingOfficer extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'other_receiving_officers';
    protected $primaryKey = 'id';

    protected $fillable = [
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'office_name',
        'designation',
        'department',
        'notes',
        'created_by',
        'updated_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created this officer
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this officer
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the full name attribute
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get the name attribute (alias for full_name for consistency with User model)
     */
    public function getNameAttribute(): string
    {
        return $this->getFullNameAttribute();
    }

    /**
     * Scope to get only active officers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope to search by name
     */
    public function scopeSearchByName($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('first_name', 'LIKE', "%{$search}%")
              ->orWhere('last_name', 'LIKE', "%{$search}%")
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
        });
    }
}