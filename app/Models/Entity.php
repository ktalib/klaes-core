<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entity extends Model
{
    use HasFactory;

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
    protected $table = 'entities_staging';

    /**
     * The primary key associated with the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'entity_type',
        'entity_name',
        'passport_photo',
        'company_logo',
        'file_number',
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
    ];

    /**
     * Get all customers associated with this entity.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customers()
    {
        return $this->hasMany(Customer::class, 'entity_id', 'id');
    }

    /**
     * Get the full name of the entity.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return $this->entity_name;
    }

    /**
     * Get the display name for the entity.
     *
     * @return string
     */
    public function getDisplayNameAttribute()
    {
        return $this->entity_name;
    }

    /**
     * Get the media URL based on entity type.
     *
     * @return string|null
     */
    public function getMediaUrlAttribute()
    {
        if ($this->entity_type === 'Individual' && $this->passport_photo) {
            return asset('storage/' . $this->passport_photo);
        }

        if ($this->entity_type === 'Corporate' && $this->company_logo) {
            return asset('storage/' . $this->company_logo);
        }

        return null;
    }

    /**
     * Scope to filter by entity type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('entity_type', $type);
    }

    /**
     * Scope to filter by entity name.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $name
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByName($query, $name)
    {
        return $query->where('entity_name', 'LIKE', '%' . $name . '%');
    }

    /**
     * Find or create an entity based on name and type.
     *
     * @param string $entityName
     * @param string $entityType
     * @return \App\Models\Entity
     */
    public static function findOrCreateByName($entityName, $entityType = 'Individual')
    {
        return static::firstOrCreate(
            [
                'entity_name' => trim($entityName),
                'entity_type' => $entityType,
            ],
            [
                'entity_type' => $entityType,
                'entity_name' => trim($entityName),
            ]
        );
    }

    /**
     * Attempt to find similar entities by name (for matching).
     * Returns entities with similar names for user validation.
     *
     * @param string $entityName
     * @param string|null $entityType
     * @param float $threshold
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function findSimilar($entityName, $entityType = null, $threshold = 0.6)
    {
        $cleanName = trim($entityName);
        $query = static::query();

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        return $query->get()->filter(function ($entity) use ($cleanName, $threshold) {
            // Calculate similarity using similar_text function
            similar_text(strtolower($cleanName), strtolower($entity->entity_name), $percent);
            return $percent >= ($threshold * 100);
        });
    }

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Normalize entity name on saving
        static::saving(function ($model) {
            $model->entity_name = trim($model->entity_name);
        });
    }
}
