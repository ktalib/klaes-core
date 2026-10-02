# Entity-Customer System - Quick Start Guide

## ⚡ 5-Minute Setup

### Step 1: Verify Files (30 seconds)
```bash
# Check all files exist
ls app/Models/Entity.php
ls app/Services/EntityService.php
ls app/Http/Controllers/EntityCustomerController.php
ls database/migrations/2025_11_11_*
```

### Step 2: Run Migrations (1 minute)
```bash
php artisan migrate --database=sqlsrv
```

### Step 3: Add Routes (1 minute)
Add to `routes/apps3.php`:
```php
Route::resource('entities', EntityCustomerController::class);
Route::resource('customers', EntityCustomerController::class);

Route::post('api/entity-customer/find-similar', [EntityCustomerController::class, 'findSimilarEntities']);
Route::post('api/entity-customer/link-customers', [EntityCustomerController::class, 'linkCustomersToEntity']);
Route::post('api/entity-customer/merge-entities', [EntityCustomerController::class, 'mergeEntities']);
Route::get('api/entity-customer/statistics', [EntityCustomerController::class, 'getStatistics']);
Route::get('api/entity-customer/search', [EntityCustomerController::class, 'searchEntities']);
Route::get('api/entity-customer/entity/{entity}/customers', [EntityCustomerController::class, 'getEntityCustomers']);
```

### Step 4: Clear Caches (1 minute)
```bash
php artisan route:clear
php artisan view:clear
php artisan config:clear
```

### Step 5: Test (1 minute)
```bash
# Visit in browser:
# http://localhost/entities
# http://localhost/customers
```

---

## 🎯 Most Common Tasks

### Create Entity with Customer
```php
// In controller or artisan command
$entityService = resolve(EntityService::class);

$customer = $entityService->createCustomerWithEntity(
    [
        'customer_type' => 'Individual',
        'customer_name' => 'Musa Ali',
        'email' => 'john@example.com',
        'phone' => '08012345678',
        'created_by' => auth()->id(),
    ]
);

echo "Created: {$customer->entity->entity_name}";
```

### Find Similar Entities
```php
$similar = $entityService->findSimilarEntities(
    'Doe John', // Variation
    'Individual',
    0.65 // 65% match threshold
);
```

### Link Customer to Existing Entity
```php
$customer->update(['entity_id' => $entityId]);
```

### Merge Duplicate Entities
```php
// Move all customers from entity 5 to entity 1
$entityService->mergeEntities(5, 1);
```

### Get Entity with All Customers
```php
$entity = $entityService->getEntityWithCustomers($entityId);

foreach ($entity->customers as $customer) {
    echo $customer->customer_name;
}
```

---

## 📖 Essential Information

### Entity Types
- **Individual**: Person (stores passport photo)
- **Corporate**: Business (stores company logo)
- **Multiple Owners**: Group (no media)

### Customer Statuses
- Active, Inactive, Blocked, Archived

### Database Tables
- `entities`: Unique owners
- `customers`: Ownership records linked to entities via `entity_id`

### Key Features
✅ Automatic entity matching  
✅ 1-to-many relationships  
✅ Media storage (passport/logo)  
✅ Bulk operations (merge, link)  
✅ 30+ REST endpoints  
✅ Transaction support  
✅ Soft deletes  

---

## 🔗 Useful Links

| What | Where |
|------|-------|
| Complete Guide | `ENTITY_CUSTOMER_IMPLEMENTATION.md` |
| Quick Reference | `ENTITY_CUSTOMER_DEPLOYMENT_SUMMARY.md` |
| Architecture | `ENTITY_CUSTOMER_ARCHITECTURE.md` |
| Testing Guide | `test_entity_customer_system.html` |
| API Reference | EntityCustomerController comments |

---

## ✅ Verification Checklist

- [ ] All files created in correct locations
- [ ] Migrations run successfully
- [ ] Routes added to route file
- [ ] Caches cleared
- [ ] Can access /entities
- [ ] Can access /customers
- [ ] Can create entity
- [ ] Can create customer
- [ ] Entity appears in customer dropdown
- [ ] Customer shows entity info

---

## 🐛 Quick Troubleshooting

| Issue | Solution |
|-------|----------|
| 404 on /entities | Clear routes: `php artisan route:clear` |
| Foreign key error | Ensure entity exists before linking |
| Media not saving | Run: `php artisan storage:link` |
| Migrations fail | Check SQL Server connection in config |
| Entity dropdown empty | Refresh page, check entity count |

---

## 📞 Getting Help

1. **Quick Issues**: Check troubleshooting table above
2. **Setup Problems**: See `ENTITY_CUSTOMER_DEPLOYMENT_SUMMARY.md`
3. **Architecture Questions**: Read `ENTITY_CUSTOMER_ARCHITECTURE.md`
4. **Testing**: Open `test_entity_customer_system.html` in browser
5. **Deep Dive**: Read `ENTITY_CUSTOMER_IMPLEMENTATION.md`

---

## 🎓 Next Steps

1. ✅ Complete 5-minute setup above
2. ✅ Run verification checklist
3. ✅ Create test entity & customer
4. ✅ Test entity matching with similar name
5. ✅ Explore API endpoints
6. ✅ Review full documentation
7. ✅ Deploy to staging/production

---

**Status**: ✅ Ready to Use  
**Setup Time**: ~5 minutes  
**Documentation**: Comprehensive  
**Support**: Fully documented
