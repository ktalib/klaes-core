# Excel Import Setup and Usage Guide

## Quick Setup Instructions

### 1. Python Environment Setup
```bash
# Create virtual environment
python -m venv excel_import_env

# Activate environment (Windows)
excel_import_env\Scripts\activate

# Install dependencies
pip install -r requirements.txt
```

### 2. Configuration
Edit `excel_import_template.py` and update the `ImportConfig` class:

```python
class ImportConfig:
    def __init__(self):
        # REQUIRED: Set your Excel file path
        self.EXCEL_FILE_PATH = r'C:\path\to\your\300mb_excel_file.xlsx'
        
        # REQUIRED: Set your database password
        self.DB_PASSWORD = 'your_database_password_here'
        
        # Optional: Adjust performance settings
        self.CHUNK_SIZE = 10000      # Records per chunk
        self.BATCH_SIZE = 1000       # Records per SQL batch
```

### 3. Pre-Import Verification
Before running the full import, test with a small sample:

```python
# In the template, temporarily set:
self.CHUNK_SIZE = 100  # Small test batch
# Then run: python excel_import_template.py
```

### 4. Full Import Execution
```bash
python excel_import_template.py
```

## Expected Performance
- **File Size:** 300MB Excel file
- **Records:** ~2.7M records across 12 sheets
- **Estimated Time:** 2-3 hours
- **Memory Usage:** <2GB RAM with chunking
- **Processing Rate:** 1000-2000 records/second

## Monitoring Progress
The script provides real-time logging:
- Console output with progress indicators
- `excel_import.log` file with detailed logs
- Checkpoint system for resuming interrupted imports

## Sheet Processing Order
The script will process sheets in this order:
1. RES (Residential)
2. COM (Commercial)  
3. AG (Agriculture)
4. RES-RC, COM-RC, AG-RC (RC variants)
5. CON-RES, CON-COM, CON-AG (Conversion types)
6. CON-RES-RC, CON-COM-RC, CON-AG-RC (Conversion RC types)

## Data Validation
Each record is validated for:
- Valid year range (1980-2030)
- Positive number values
- Non-empty FileNo fields
- Consistent sheet-to-landuse mapping

## Error Handling
- Invalid records are logged but skipped
- Database transactions ensure data integrity
- Automatic rollback on batch errors
- Resume capability from checkpoints

## Final Verification
After import completion, verify results:
```sql
-- Check total record count
SELECT COUNT(*) FROM grouping;

-- Check land use distribution
SELECT landuse, COUNT(*) as count 
FROM grouping 
GROUP BY landuse 
ORDER BY count DESC;

-- Check sheet completion
SELECT LEFT(awaiting_fileno, CHARINDEX('-', awaiting_fileno + '-') - 1) as prefix,
       COUNT(*) as count
FROM grouping 
GROUP BY LEFT(awaiting_fileno, CHARINDEX('-', awaiting_fileno + '-') - 1)
ORDER BY count DESC;
```

## Troubleshooting
- **Memory errors:** Reduce CHUNK_SIZE to 5000 or lower
- **Connection timeouts:** Reduce BATCH_SIZE to 500
- **File access errors:** Ensure Excel file is not open in Excel
- **Permission errors:** Run as administrator if needed

## Success Criteria
✅ All 12 sheets processed successfully  
✅ ~2.7M records imported  
✅ Land use mapping applied correctly  
✅ No data corruption or duplicates  
✅ Import completes within 3 hours  
✅ Less than 0.1% error rate  