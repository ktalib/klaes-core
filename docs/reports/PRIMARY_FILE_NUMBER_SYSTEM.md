# 📋 Primary File Number Generation System

## 🎯 **File Number Format Specification**

### **Format Pattern**
```
ST-[LANDUSE]-[YEAR]-[SERIAL]
```

### **Land Use Types and Formats**

#### 1. **Commercial Land Use**
```
ST-COM-2025-1
ST-COM-2025-2  
ST-COM-2025-3
...
```

#### 2. **Residential Land Use**
```
ST-RES-2025-1
ST-RES-2025-2
ST-RES-2025-3
...
```

#### 3. **Industrial Land Use**
```
ST-IND-2025-1
ST-IND-2025-2
ST-IND-2025-3
...
```

#### 4. **Mixed Land Use**
```
ST-MIXED-2025-1
ST-MIXED-2025-2
ST-MIXED-2025-3
...
```

---

## 🗄️ **Database Design**

### **Primary Table: `land_use_serials`**
```sql
CREATE TABLE [dbo].[land_use_serials] (
    id INT IDENTITY(1,1) PRIMARY KEY,
    land_use_type VARCHAR(50) NOT NULL,    -- 'COMMERCIAL', 'RESIDENTIAL', 'INDUSTRIAL', 'MIXED'
    prefix VARCHAR(20) NOT NULL,           -- 'ST-COM', 'ST-RES', 'ST-IND', 'ST-MIXED'
    year INT NOT NULL,                     -- e.g. 2025
    current_serial INT NOT NULL DEFAULT 0, -- last used serial number
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE(),
    
    CONSTRAINT UQ_land_use_year UNIQUE (land_use_type, year)
);
```

### **Serial Tracking Logic**
- Each **land use type** maintains its **own independent serial sequence**
- Serials reset to 1 each year for each land use type
- **Atomic operations** prevent duplicate numbers under concurrent access

---

## ⚙️ **Implementation Architecture**

### **1. Controller Methods**
```php
// PrimaryFormController.php

private function getNextSerialNumber($landUse, $year)
{
    // Uses stored procedure for atomic serial generation
    // Falls back to direct table operations if procedure unavailable
    // Ultimate fallback counts existing records
}

private function generateFileNumber($landUse, $serial, $year) 
{
    // Formats: ST-{LANDUSE_CODE}-{YEAR}-{SERIAL}
}
```

### **2. Atomic Serial Generation**
```sql
-- Stored Procedure: GetNextFileSerial
EXEC GetNextFileSerial @LandUseType='COMMERCIAL', @Year=2025
-- Returns: Next available serial number atomically
```

### **3. Form Integration**
```php
// index() method - Preview file number
$nextSerial = $this->getNextSerialNumber($landUse, $year);
$previewFileNo = $this->generateFileNumber($landUse, $nextSerial, $year);

// store() method - Final file number generation  
$finalSerial = $this->getNextSerialNumber($landUse, $year);
$finalFileNo = $this->generateFileNumber($landUse, $finalSerial, $year);
```

---

## 🚀 **Setup Instructions**

### **1. Run Database Setup**
```bash
php setup_land_use_serials.php
```

### **2. Verify Setup**
- Check `land_use_serials` table exists
- Verify initial records for all land use types  
- Test stored procedure functionality

### **3. Test File Number Generation**
```php
// Test in Laravel Tinker
php artisan tinker

$controller = new App\Http\Controllers\PrimaryFormController();
$serial = $controller->getNextSerialNumber('COMMERCIAL', 2025);
// Should return: 1 (first time)

$fileNo = $controller->generateFileNumber('COMMERCIAL', $serial, 2025);  
// Should return: ST-COM-2025-1
```

---

## 📊 **Serial Sequence Independence**

### **Example Scenario**
If we create applications in this order:
1. Commercial application → `ST-COM-2025-1`
2. Residential application → `ST-RES-2025-1`  
3. Commercial application → `ST-COM-2025-2`
4. Industrial application → `ST-IND-2025-1`
5. Residential application → `ST-RES-2025-2`

### **Result**
- **COMMERCIAL**: Uses serials 1, 2, 3... independently
- **RESIDENTIAL**: Uses serials 1, 2, 3... independently  
- **INDUSTRIAL**: Uses serials 1, 2, 3... independently
- **MIXED**: Uses serials 1, 2, 3... independently

---

## 🔒 **Concurrency Safety**

### **Race Condition Prevention**
- ✅ **SQL Server Transactions** - Atomic operations
- ✅ **Row-level Locking** - `UPDLOCK` on serial records  
- ✅ **Stored Procedures** - Database-level atomicity
- ✅ **Fallback Methods** - Multiple layers of protection

### **Performance Optimization**
- **Indexed Lookups** on `(land_use_type, year)`
- **Minimal Lock Duration** - Quick increment operations
- **Connection Pooling** - Efficient database usage

---

## 📈 **Monitoring and Maintenance**

### **Current Serial Numbers**
```sql
SELECT land_use_type, year, current_serial, updated_at 
FROM land_use_serials 
ORDER BY year DESC, land_use_type;
```

### **Annual Reset Process**
Each January 1st, new records are automatically created for the new year with `current_serial = 0`, allowing each land use type to start fresh.

### **Data Integrity Checks**
- Monitor for gaps in serial sequences
- Verify file numbers match the expected format
- Check for duplicate file numbers (should never occur)

---

## 🛠️ **Troubleshooting**

### **Common Issues**
1. **Stored Procedure Not Found**
   - Solution: Run setup script to create procedure
   - Fallback: Direct table operations will work

2. **Table Missing**  
   - Solution: Run `php setup_land_use_serials.php`
   - Creates table with initial data

3. **Serial Number Gaps**
   - Cause: Failed transactions or rollbacks
   - Impact: Cosmetic only, doesn't affect functionality

4. **Duplicate File Numbers**
   - Should never happen with proper setup
   - Check transaction isolation levels if occurring

---

**Last Updated:** September 26, 2025  
**Version:** 1.0.0  
**Author:** KLAES GIS EDMS Development Team