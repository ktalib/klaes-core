<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'sqlsrv';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customers_staging';

    /**
     * The primary key associated with the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'entity_id',
        'customer_type',
        'status',
        'customer_name',
        'file_number',
        'account_no',
        'email',
        'phone',
        'mobile',
        'reason_retired',
        'property_address',
        'residential_address',
        'physical_address',
        'notes',
        'customer_code',
        'created_by',
        'updated_by',
        'retired_by',
        'test_control',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Get the entity associated with this customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id', 'id');
    }

    /**
     * Get the full name of the customer.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return $this->customer_name;
    }

    /**
     * Get the display name for the customer.
     *
     * @return string
     */
    public function getDisplayNameAttribute()
    {
        return $this->customer_name;
    }

    /**
     * Scope to filter by customer type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('customer_type', $type);
    }

    /**
     * Scope to filter customers by entity ID.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $entityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByEntity($query, $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    /**
     * Scope to filter by customer type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter active customers.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Generate a unique customer code if not already set.
     *
     * @return string
     */
    public static function generateCustomerCode()
    {
        do {
            $code = 'CUST-' . strtoupper(uniqid());
        } while (static::where('customer_code', $code)->exists());

        return $code;
    }

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate customer code before creating
        static::creating(function ($model) {
            if (!$model->customer_code) {
                $model->customer_code = static::generateCustomerCode();
            }
        });
    }
}
