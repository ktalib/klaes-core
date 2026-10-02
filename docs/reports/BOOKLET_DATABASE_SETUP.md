# Booklet Management Database Integration - Setup Instructions

## Step 1: Execute the SQL Script

**IMPORTANT**: Execute the SQL script first before testing the booklet functionality.

### Execute this script in SQL Server Management Studio:
Location: `database_scripts/06_add_booklet_fields_to_pagetypings.sql`

**Before executing, update the database name:**
Replace `[your_database_name]` in the script with your actual database name.

### The script will add these fields to the `pagetypings` table:

1. **`booklet_id`** (NVARCHAR(50), nullable)
   - Stores unique booklet identifier (e.g., "booklet_1693747200000")
   - Links multiple pages together as a booklet

2. **`is_booklet_page`** (BIT, nullable, default 0)
   - Boolean flag indicating if the page is part of a booklet
   - 0 = normal page, 1 = booklet page

3. **`booklet_sequence`** (NVARCHAR(5), nullable)
   - Stores the alphabetic sequence within a booklet ("a", "b", "c", etc.)
   - Used for generating page codes like 01a, 01b, 01c

### Performance Indexes:
- `IX_pagetypings_booklet_id` - For efficient booklet queries
- `IX_pagetypings_booklet_composite` - For composite booklet queries

## Step 2: Verify Database Changes

After executing the script, verify the columns were added:

```sql
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'pagetypings' 
    AND COLUMN_NAME IN ('booklet_id', 'is_booklet_page', 'booklet_sequence')
ORDER BY COLUMN_NAME
```

## Step 3: Code Changes Applied

### Backend Changes:

#### PageTyping Model (`app/Models/PageTyping.php`):
✅ Added to `$fillable` array:
- `booklet_id`
- `is_booklet_page` 
- `booklet_sequence`

✅ Added to `$casts` array:
- `is_booklet_page => 'boolean'`

#### PageTypingController (`app/Http/Controllers/PageTypingController.php`):
✅ Updated validation rules in `saveSingle()` method:
- `booklet_id => 'nullable|string|max:50'`
- `is_booklet_page => 'nullable|boolean'`
- `booklet_sequence => 'nullable|string|max:5'`
- `serial_number => 'required|integer|min:0'` (changed from min:1 to allow 0 for booklet base)

✅ Updated create/update operations to handle booklet fields

### Frontend Changes:

#### JavaScript (`resources/views/pagetyping/index.blade.php`):
✅ Enhanced `pageData` object to include:
- `booklet_id`
- `is_booklet_page`
- `booklet_sequence`

✅ Updated serial number logic for booklet pages

## Step 4: Testing the Booklet Functionality

### Test Scenario 1: Normal Pages
1. Process pages normally
2. Verify page codes: `FC-POA-01-01`, `FC-POA-01-02`, etc.
3. Check database: `is_booklet_page = 0`, `booklet_id = NULL`

### Test Scenario 2: Booklet Pages
1. Click "Start Booklet"
2. Process multiple pages
3. Verify page codes: `FC-POA-01-01a`, `FC-POA-01-01b`, `FC-POA-01-01c`
4. Check database:
   - `is_booklet_page = 1`
   - `booklet_id = "booklet_1693747200000"` (or similar timestamp)
   - `booklet_sequence = "a"`, `"b"`, `"c"`
   - `serial_number = 1` (base number for all pages in booklet)

### Test Scenario 3: Mixed Pages
1. Process normal page (01)
2. Start booklet, process pages (02a, 02b)
3. End booklet, process normal page (03)
4. Verify database entries correctly distinguish booklet vs normal pages

## Step 5: Database Query Examples

### Find all pages in a specific booklet:
```sql
SELECT * FROM pagetypings 
WHERE booklet_id = 'booklet_1693747200000'
ORDER BY booklet_sequence
```

### Find all booklet pages:
```sql
SELECT * FROM pagetypings 
WHERE is_booklet_page = 1
ORDER BY booklet_id, booklet_sequence
```

### Group booklet statistics:
```sql
SELECT 
    booklet_id,
    COUNT(*) as page_count,
    MIN(booklet_sequence) as first_page,
    MAX(booklet_sequence) as last_page
FROM pagetypings 
WHERE is_booklet_page = 1
GROUP BY booklet_id
```

## Troubleshooting

### If you get 422 errors:
1. Verify the SQL script was executed successfully
2. Check that new columns exist in the database
3. Clear Laravel cache: `php artisan cache:clear`
4. Check Laravel logs for detailed error messages

### If booklet functionality doesn't work:
1. Check browser console for JavaScript errors
2. Verify the booklet management section appears in the UI
3. Test with browser developer tools to see the actual API requests

The booklet management feature is now fully integrated with the database and ready for testing! 🎉
