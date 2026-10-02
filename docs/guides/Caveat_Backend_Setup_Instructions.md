# Caveat Backend Implementation Instructions

## Quick Setup Steps

### 1. Database Migrations
```bash
# Create and run migrations
php artisan make:migration create_caveats_table
php artisan make:migration create_file_numbers_table
php artisan make:migration add_columns_to_instrument_types_table
php artisan migrate
```

### 2. Create Models
```bash
php artisan make:model Caveat
php artisan make:model FileNumber
```

### 3. Create Controllers
```bash
php artisan make:controller CaveatController
php artisan make:controller FileNumberController
```

### 4. Essential Routes (add to routes/api.php)
```php
// Caveat API routes
Route::prefix('caveats')->group(function () {
    Route::get('/', [CaveatController::class, 'getCaveats']);
    Route::post('/', [CaveatController::class, 'store']);
    Route::get('/{id}', [CaveatController::class, 'show']);
    Route::put('/{id}', [CaveatController::class, 'update']);
    Route::delete('/{id}', [CaveatController::class, 'destroy']);
    Route::get('/utils/next-page-number', [CaveatController::class, 'getNextPageNumber']);
});

// File number API routes
Route::prefix('file-numbers')->group(function () {
    Route::get('/search', [FileNumberController::class, 'search']);
    Route::post('/', [FileNumberController::class, 'store']);
    Route::get('/{id}', [FileNumberController::class, 'show']);
});
```

### 5. Web Routes (add to routes/web.php)
```php
Route::middleware(['auth'])->group(function () {
    Route::get('/caveats', [CaveatController::class, 'index'])->name('caveats.index');
});
```

## Database Schema Requirements

### Caveats Table Fields:
- id, caveat_number (unique), registration_number, page_number
- file_number_id (foreign key), file_number_type, caveator_name, caveator_address
- caveatee_name, caveatee_address, property_description, status (default: 'active')
- date_created, expiry_date, remarks, created_by, updated_by, timestamps

### File Numbers Table Fields:
- id, file_number (unique), file_type, property_type, property_description
- location, status (default: 'active'), timestamps

### Instrument Types Table Additions:
- description, category, status (if not exists)

## Model Requirements

### Caveat Model Features:
- Relationships: belongsTo FileNumber
- Scopes: active(), byDateRange()
- Methods: generateCaveatNumber(), generateRegistrationNumber()
- Fillable fields and date casting

### FileNumber Model Features:
- Relationships: hasMany Caveats
- Scopes: active(), byType(), search()
- Accessor: getDisplayTypeAttribute()

## Controller Methods

### CaveatController:
- index() - return view
- getCaveats() - API endpoint with filters
- store() - create new caveat
- show() - get single caveat
- update() - update caveat
- destroy() - delete caveat
- getNextPageNumber() - utility endpoint

### FileNumberController:
- search() - search file numbers
- store() - create file number
- show() - get single file number

## Key Implementation Notes

1. **Auto-fill Logic**: 
   - Next page number based on current year
   - Registration number format: REG/YYYY/P{page_number}
   - Caveat number format: CAV/YYYY/{sequential}

2. **File Number Types**:
   - MLSF, KANGIS, New KANGIS
   - Display appropriate labels in frontend

3. **Status Management**:
   - active, expired, withdrawn for caveats
   - active, inactive for file numbers

4. **API Response Format**:
   ```json
   {
     "success": true/false,
     "data": {},
     "message": "",
     "errors": {} // for validation
   }
   ```

5. **Validation Rules**:
   - Required: caveator_name, date_created
   - Unique: caveat_number, file_number
   - Date validation: expiry_date after date_created

## Testing Commands
```bash
# Test database connection
php artisan tinker --execute="use App\Models\Caveat; echo Caveat::count();"

# Create sample data
php artisan make:seeder CaveatSeeder
php artisan make:seeder FileNumberSeeder
php artisan db:seed
```

## Authentication Setup (if needed)
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

## Production Checklist
- [ ] Database indexes on frequently queried columns
- [ ] Proper validation rules
- [ ] Authentication middleware
- [ ] CORS configuration
- [ ] Error logging
- [ ] API rate limiting
- [ ] Database backups

This backend implementation will work seamlessly with the existing JavaScript frontend modules (caveat-data.js, caveat-rendering.js, caveat-events.js, file-number-selector.js, manual-file-number-modal.js).
