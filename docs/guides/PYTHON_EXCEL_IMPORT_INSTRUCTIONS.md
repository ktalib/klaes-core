# AI Agent Instructions: Python Excel Import Program for Grouping Table

## Overview
Build a robust Python program to import 2.7M records from a 300MB Excel file with 12 sheets into Laravel's SQL Server grouping table. The Excel file contains land use data with consistent structure across all sheets.

## Excel File Structure Analysis
**File Specifications:**
- **File Size:** ~300MB
- **Total Records:** ~2.7M records across all sheets
- **Sheet Count:** 12 sheets with different land use categories
- **Column Structure:** Each sheet has exactly 3 columns in this order:
  1. **Year** (Column A): Integer values representing the year (1981-2025 range)
  2. **Number** (Column B): Sequential integer numbers (1, 2, 3, etc.)
  3. **FileNo** (Column C): Complete file number strings (e.g., "RES-1981-1", "CON-AG-RC-2020-15")

**Sheet Names and Land Use Category Mapping:**
- **RES** → RESIDENTIAL (Residential applications)
- **COM** → COMMERCIAL (Commercial applications)  
- **AG** → AGRICULTURE (Agriculture applications)
- **RES-RC** → RESIDENTIAL (Residential with RC suffix)
- **COM-RC** → COMMERCIAL (Commercial with RC suffix)
- **AG-RC** → AGRICULTURE (Agriculture with RC suffix)
- **CON-RES** → RESIDENTIAL (Conversion to Residential)
- **CON-COM** → COMMERCIAL (Conversion to Commercial)
- **CON-AG** → AGRICULTURE (Conversion to Agriculture)
- **CON-RES-RC** → RESIDENTIAL (Conversion to Residential with RC)
- **CON-COM-RC** → COMMERCIAL (Conversion to Commercial with RC)
- **CON-AG-RC** → AGRICULTURE (Conversion to Agriculture with RC)

**Data Examples per Sheet:**
- RES sheet: Year=1981, Number=1, FileNo="RES-1981-1"
- CON-AG-RC sheet: Year=2020, Number=15, FileNo="CON-AG-RC-2020-15"
- COM sheet: Year=1995, Number=500, FileNo="COM-1995-500"

## Technical Requirements

### 1. Database Connection
```python
# SQL Server connection details for Laravel app
SERVER = 'VMI2583396'
DATABASE = 'klas'
USERNAME = 'sa'  # Use Laravel's database config
PASSWORD = '[from Laravel config]'
TABLE = 'grouping'
```

### 2. Target Table Structure and Column Mapping
The grouping table contains 21 columns total. The Python program must map Excel data to these database fields:

**Excel Column Mapping:**
- Excel "Year" column → Database "year" field (int)
- Excel "Number" column → Database "number" field (nvarchar) 
- Excel "FileNo" column → Database "awaiting_fileno" field (nvarchar)
- Sheet name → Database "landuse" field (via mapping logic)

**Complete Database Field Mapping:**
- id: Auto-generated identity column (do not insert)
- awaiting_fileno: Maps to Excel "FileNo" column (primary file identifier)
- number: Maps to Excel "Number" column (sequential number from Excel)
- mls_fileno: Set to NULL (not used in this import)
- mapping: Set to 0 (default mapping status)
- group: Set to NULL (grouping field not used)
- batch_no: Generate unique batch identifier per import run
- mdc_batch_no: Set to NULL (MDC batch not applicable)
- sys_batch_no: Set to NULL (system batch not applicable)
- shelf_rack: Set to NULL (physical location not applicable)
- date: Set to NULL (no specific date in Excel)
- created_by: Set to 'python_import' (import tracking)
- indexed_by: Set to NULL (indexing not done during import)
- date_index: Set to NULL (no indexing date)
- year: Maps to Excel "Year" column (int value)
- landuse: Derived from sheet name using LAND_USE_MAPPING dictionary
- created_at: Current timestamp when record is inserted
- updated_at: Current timestamp when record is inserted
- updated_by: Set to 'python_import' (import tracking)
- deleted_at: Set to NULL (no soft deletes during import)
- deleted_by: Set to NULL (no deletions during import)

## Program Architecture Requirements

### 1. Required Dependencies
The Python program must import these essential libraries:
- pandas: For Excel file reading and data manipulation
- pyodbc: For SQL Server database connections
- logging: For comprehensive import tracking and debugging
- datetime: For timestamp generation
- typing: For proper type hints and code clarity
- gc: For memory management during large file processing

### 2. Memory Management Strategy
- **Chunk Processing:** Read Excel sheets in chunks of 10,000-50,000 records to avoid memory overflow
- **Memory Cleanup:** Execute garbage collection between sheet processing
- **Progress Tracking:** Implement real-time progress indicators for user feedback
- **Error Recovery:** Design checkpoint system to resume from last successful batch

### 3. Data Validation Requirements
Implement validation rules for each record before database insertion:
- **Year Validation:** Must be within valid range (1980-2030)
- **Number Validation:** Must be positive integer or numeric value
- **FileNo Validation:** Must not be empty, null, or whitespace only
- **Sheet Name Validation:** Must match one of the 12 expected sheet names
- **Data Type Validation:** Ensure proper data types for all fields

### 4. Land Use Mapping Requirements
Create mapping dictionary to convert Excel sheet names to database land use categories:
- RES → RESIDENTIAL
- COM → COMMERCIAL  
- AG → AGRICULTURE
- RES-RC → RESIDENTIAL
- COM-RC → COMMERCIAL
- AG-RC → AGRICULTURE
- CON-RES → RESIDENTIAL
- CON-COM → COMMERCIAL
- CON-AG → AGRICULTURE
- CON-RES-RC → RESIDENTIAL
- CON-COM-RC → COMMERCIAL
- CON-AG-RC → AGRICULTURE
- Handle unknown sheet names with proper error logging

### 5. Database Batch Processing Strategy
- **Transaction Management:** Use database transactions for data integrity
- **Batch Size Optimization:** Process records in batches of 1000-5000 for optimal performance
- **Error Handling:** Implement rollback mechanisms for failed batches
- **Connection Management:** Properly handle database connections and cleanup
- **SQL Injection Prevention:** Use parameterized queries for all database operations

## Implementation Steps

### Step 1: Environment Setup Requirements
- Create isolated Python virtual environment for the project
- Install required packages: pandas, openpyxl, pyodbc, python-dotenv, tqdm
- Verify Python version compatibility (3.8 or higher recommended)
- Test database connectivity before proceeding

### Step 2: Configuration Management Requirements
Create configuration system that handles:
- **Database Connection Settings:** Server name (VMI2583396), database name (klas), credentials
- **File Path Management:** Excel file path, log file paths, checkpoint file paths
- **Performance Settings:** Batch sizes, chunk sizes, memory cleanup intervals
- **Environment Variables:** Use .env file for sensitive information like passwords
- **Logging Configuration:** Log levels, output formats, file rotation settings

### Step 3: Logging and Monitoring Requirements
Implement comprehensive logging system with:
- **Console Output:** Real-time progress updates for user monitoring
- **File Logging:** Detailed logs saved to persistent log files
- **Progress Tracking:** Record counts, processing speeds, time estimates
- **Error Logging:** Detailed error messages with context and line numbers
- **Performance Metrics:** Memory usage, processing rates, batch timings

### Step 4: Main Program Architecture Requirements
Design object-oriented structure with these core components:
- **Configuration Class:** Centralized settings management
- **Database Connection Manager:** Handle SQL Server connections and transactions
- **Excel File Reader:** Efficient reading of large Excel files with chunking
- **Data Validator:** Validate each record according to business rules
- **Data Transformer:** Convert Excel rows to database record format
- **Batch Processor:** Handle database insertions in optimized batches
- **Statistics Tracker:** Monitor import progress and performance metrics
- **Error Handler:** Manage exceptions and recovery scenarios

### Step 5: Error Handling and Recovery Requirements
Implement robust error management system:
- **Checkpoint System:** Save progress at regular intervals for resumability
- **Transaction Management:** Use database transactions to ensure data integrity
- **Validation Errors:** Log invalid records but continue processing valid ones
- **Connection Failures:** Implement retry logic for temporary database issues
- **Memory Errors:** Graceful handling of memory limitations with smaller chunk sizes
- **File Access Errors:** Handle file locking and permission issues

### Step 6: Performance Optimization Requirements
Configure optimal performance settings:
- **Pandas Chunk Size:** Read Excel in 10,000-50,000 record chunks
- **SQL Batch Size:** Insert in batches of 1,000-5,000 records
- **Memory Management:** Garbage collection every 5 sheets processed
- **Connection Pooling:** Efficient database connection reuse
- **Single-Threaded Processing:** Avoid race conditions and ensure data integrity
- **Progress Indicators:** Use tqdm or similar for visual progress feedback

## Testing Requirements

### 1. Unit Testing Requirements
Create comprehensive unit tests to validate:
- **Land Use Mapping Tests:** Verify all 12 sheet names map to correct land use categories
- **Record Validation Tests:** Test validation rules for year range, number format, FileNo presence
- **Data Transformation Tests:** Ensure Excel data correctly transforms to database record format
- **Configuration Tests:** Validate configuration loading and default value handling
- **Utility Function Tests:** Test helper functions for data cleaning and formatting

### 2. Integration Testing Requirements
Implement integration tests to verify:
- **Database Connection Tests:** Confirm SQL Server connectivity with proper credentials
- **Excel File Reading Tests:** Verify Excel file accessibility and correct sheet detection
- **Small Batch Import Tests:** Test end-to-end import with 100-1000 records
- **Error Recovery Tests:** Test checkpoint system and resumability features
- **Performance Tests:** Validate memory usage stays within acceptable limits

## Execution Plan

### Phase 1: Setup and Validation (30 minutes)
1. Set up Python environment
2. Install dependencies
3. Test database connection
4. Validate Excel file structure
5. Test small batch import (100 records)

### Phase 2: Core Development (2 hours)
1. Implement ExcelImporter class
2. Build chunking and batch processing
3. Add comprehensive logging
4. Implement error handling

### Phase 3: Testing and Optimization (1 hour)
1. Run unit tests
2. Test with single sheet
3. Memory usage optimization
4. Performance tuning

### Phase 4: Full Import (2-3 hours estimated)
1. Run complete import with monitoring
2. Verify record counts per sheet
3. Data integrity validation
4. Generate import summary report

## Success Metrics
- **Data Integrity:** 100% of valid records imported
- **Performance:** Process ~1000-2000 records/second
- **Memory Usage:** Stay under 2GB RAM usage
- **Error Rate:** <0.1% processing errors
- **Resumability:** Can resume from any point if interrupted

## Deliverables
1. **Main Script:** `excel_importer.py`
2. **Configuration:** `config.py` and `.env` file
3. **Tests:** `test_importer.py`
4. **Documentation:** `README.md` with usage instructions
5. **Log Files:** Detailed import logs
6. **Summary Report:** Record counts and validation results

## Risk Mitigation
- **Memory Issues:** Chunked processing with cleanup
- **Connection Timeouts:** Connection retry logic
- **Data Corruption:** Transaction rollback on errors
- **File Access Issues:** File locking and permission checks
- **Large File Handling:** Streaming reads, not loading entire file

This comprehensive approach ensures reliable import of your 2.7M records while maintaining data integrity and system performance.