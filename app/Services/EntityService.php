<?php

namespace App\Services;

use App\Models\Entity;
use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EntityService
{
    /**
     * Find or create an entity and link it to a customer.
     *
     * This method implements the core entity matching logic:
     * 1. Check if entity_name already exists in entities table
     * 2. If found → reuse existing entity_id
     * 3. If not found → create new entity record
     * 4. Link the customer record with correct entity_id
     *
     * @param string $entityName Legal name of the entity
     * @param string $entityType Individual|Corporate|Multiple
     * @param array $mediaData ['passport_photo' => path, 'company_logo' => path]
     * @return Entity
     */
    public function findOrCreateEntity($entityName, $entityType = 'Individual', $mediaData = [])
    {
        // Normalize the entity name (trim whitespace)
        $normalizedName = trim($entityName);

        // Check if entity with exact name already exists
        $entity = Entity::where('entity_name', $normalizedName)
            ->where('entity_type', $entityType)
            ->first();

        // If not found, create new entity
        if (!$entity) {
            $entity = Entity::create([
                'entity_name' => $normalizedName,
                'entity_type' => $entityType,
                'passport_photo' => $mediaData['passport_photo'] ?? null,
                'company_logo' => $mediaData['company_logo'] ?? null,
            ]);
        } else {
            // Update media if provided
            if (!empty($mediaData)) {
                $entity->update($mediaData);
            }
        }

        return $entity;
    }

    /**
     * Find similar entities by name (for user validation before creating).
     *
     * Names can vary in order or spelling (e.g., "Olaniyan Olakunle" vs "Olakunle Olaniyan").
     * This method returns entities with similar names based on similarity score.
     *
     * @param string $entityName
     * @param string|null $entityType
     * @param float $threshold Similarity threshold (0.0-1.0)
     * @return Collection
     */
    public function findSimilarEntities($entityName, $entityType = null, $threshold = 0.6)
    {
        return Entity::findSimilar($entityName, $entityType, $threshold);
    }

    /**
     * Create a customer and link it to an entity.
     *
     * @param array $customerData Customer information
     * @param int|null $entityId Existing entity ID (optional)
     * @param array $mediaData Media files for entity creation
     * @return Customer
     */
    public function createCustomerWithEntity($customerData, $entityId = null, $mediaData = [])
    {
        return DB::connection('sqlsrv')->transaction(function () use ($customerData, $entityId, $mediaData) {
            // Determine entity_id
            if ($entityId) {
                // Use existing entity
                $entity = Entity::findOrFail($entityId);
            } else {
                // Create or find entity based on customer data
                $entityName = $customerData['customer_name'] ?? $customerData['first_name'] ?? 'Unknown';
                $entityType = $this->mapCustomerTypeToEntityType($customerData['customer_type'] ?? 'Individual');

                $entity = $this->findOrCreateEntity($entityName, $entityType, $mediaData);
            }

            // Add entity_id to customer data
            $customerData['entity_id'] = $entity->id;

            // Create customer record
            $customer = Customer::create($customerData);

            return $customer;
        });
    }

    /**
     * Update a customer and optionally update its linked entity.
     *
     * @param Customer $customer
     * @param array $customerData
     * @param array $entityData
     * @return Customer
     */
    public function updateCustomerWithEntity($customer, $customerData = [], $entityData = [])
    {
        return DB::connection('sqlsrv')->transaction(function () use ($customer, $customerData, $entityData) {
            // Update customer
            if (!empty($customerData)) {
                $customer->update($customerData);
            }

            // Update linked entity if data provided
            if (!empty($entityData) && $customer->entity) {
                $customer->entity->update($entityData);
            }

            return $customer;
        });
    }

    /**
     * Get all customers for a specific entity.
     *
     * @param int $entityId
     * @return Collection
     */
    public function getCustomersByEntity($entityId)
    {
        return Customer::where('entity_id', $entityId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get entity with all related customers.
     *
     * @param int $entityId
     * @return Entity|null
     */
    public function getEntityWithCustomers($entityId)
    {
        return Entity::with('customers')->find($entityId);
    }

    /**
     * Link multiple customers to a single entity.
     *
     * Use case: Merging duplicate entities
     *
     * @param int $entityId
     * @param array $customerIds
     * @return int Number of customers linked
     */
    public function linkCustomersToEntity($entityId, $customerIds = [])
    {
        return Customer::whereIn('id', $customerIds)
            ->update(['entity_id' => $entityId]);
    }

    /**
     * Merge duplicate entities into one.
     *
     * Transfers all customers from source entity to target entity,
     * then soft-deletes the source entity record.
     *
     * @param int $sourceEntityId
     * @param int $targetEntityId
     * @return bool
     */
    public function mergeEntities($sourceEntityId, $targetEntityId)
    {
        return DB::connection('sqlsrv')->transaction(function () use ($sourceEntityId, $targetEntityId) {
            // Get all customers from source entity
            $sourceEntity = Entity::find($sourceEntityId);
            if (!$sourceEntity) {
                return false;
            }

            $customerIds = $sourceEntity->customers()->pluck('id')->toArray();

            // Link all customers to target entity
            if (!empty($customerIds)) {
                $this->linkCustomersToEntity($targetEntityId, $customerIds);
            }

            // Delete source entity
            $sourceEntity->delete();

            return true;
        });
    }

    /**
     * Store passport photo for individual entity.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $entityId
     * @return string|null File path
     */
    public function storePassportPhoto($file, $entityId)
    {
        if (!$file) {
            return null;
        }

        $path = $file->store("uploads/passports/entity_{$entityId}", 'public');
        return $path;
    }

    /**
     * Store company logo for corporate entity.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $entityId
     * @return string|null File path
     */
    public function storeCompanyLogo($file, $entityId)
    {
        if (!$file) {
            return null;
        }

        $path = $file->store("uploads/logos/entity_{$entityId}", 'public');
        return $path;
    }

    /**
     * Delete media file from storage.
     *
     * @param string $filePath
     * @return bool
     */
    public function deleteMedia($filePath)
    {
        if (!$filePath || !Storage::disk('public')->exists($filePath)) {
            return false;
        }

        return Storage::disk('public')->delete($filePath);
    }

    /**
     * Get entity statistics.
     *
     * @return array
     */
    public function getEntityStatistics()
    {
        return [
            'total_entities' => Entity::count(),
            'by_type' => Entity::selectRaw('entity_type, COUNT(*) as count')
                ->groupBy('entity_type')
                ->get()
                ->keyBy('entity_type')
                ->map(function ($item) {
                    return $item->count; })
                ->toArray(),
            'total_customers' => Customer::count(),
            'entities_with_customers' => Entity::has('customers')->count(),
            'orphaned_customers' => Customer::whereNull('entity_id')->count(),
        ];
    }

    /**
     * Map customer type to entity type.
     *
     * @param string $customerType
     * @return string
     */
    private function mapCustomerTypeToEntityType($customerType)
    {
        $mapping = [
            'Individual' => 'Individual',
            'Corporate' => 'Corporate',
            'Multiple' => 'Multiple',
            'Multiple Owners' => 'Multiple',
        ];

        return $mapping[ucfirst($customerType)] ?? 'Individual';
    }

    /**
     * Validate entity name is not empty.
     *
     * @param string $entityName
     * @return bool
     */
    public function validateEntityName($entityName)
    {
        return !empty(trim($entityName));
    }

    /**
     * Search entities by name or type.
     *
     * @param string|null $name
     * @param string|null $type
     * @param int $limit
     * @return Collection
     */
    public function searchEntities($name = null, $type = null, $limit = 50)
    {
        $query = Entity::query();

        if ($name) {
            $query->byName($name);
        }

        if ($type) {
            $query->ofType($type);
        }

        return $query->limit($limit)->get();
    }
}
