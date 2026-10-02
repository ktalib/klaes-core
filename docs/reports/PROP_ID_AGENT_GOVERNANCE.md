# Prop ID Agent Governance Rules

## Master Table Structure

**PropID_Master** is the single source of truth for prop_id to file-number mappings:

```sql
CREATE TABLE dbo.PropID_Master (
    id INT IDENTITY(1,1) PRIMARY KEY,
    prop_id INT NOT NULL UNIQUE,
    primary_file_number NVARCHAR(255) NOT NULL,
    mlsFNo NVARCHAR(255) NULL,
    kangisFileNo NVARCHAR(255) NULL, 
    NewKANGISFileno NVARCHAR(255) NULL,
    temp_fileno NVARCHAR(255) NULL,
    source_table NVARCHAR(128) NULL,
    source_record_id INT NULL,
    status NVARCHAR(32) DEFAULT ('active'),
    notes NVARCHAR(512) NULL,
    created_at DATETIME2(0) DEFAULT (SYSUTCDATETIME()),
    updated_at DATETIME2(0) NULL,
    -- Computed normalization columns
    primary_file_number_norm AS UPPER(LTRIM(RTRIM(primary_file_number))),
    mlsFNo_norm AS UPPER(LTRIM(RTRIM(mlsFNo))),
    kangisFileNo_norm AS UPPER(LTRIM(RTRIM(kangisFileNo))),
    NewKANGISFileno_norm AS UPPER(LTRIM(RTRIM(NewKANGISFileno))),
    temp_fileno_norm AS UPPER(LTRIM(RTRIM(temp_fileno)))
);
```

## Source Tables

Python agents must pull candidate data ONLY from these four staging tables:

1. **file_history_staging** - Property file history records
2. **pra** - Property Record Assistant files  
3. **pic** - Property Index Card files
4. **CofO_staging** - Certificate of Occupancy staging records

## Critical Governance Rules

### 1. Temp File Number Exclusion
**NEVER assign prop_id to records whose normalized file number matches a temp_fileno variant.** Temporary file numbers are placeholders and must never receive prop_id allocation.

### 2. Normalization Protocol
Before any prop_id operations, normalize file numbers using: `UPPER(LTRIM(RTRIM(file_number)))`

### 3. Uniqueness Enforcement
- Each `prop_id` maps to exactly ONE normalized primary file number
- No legacy fields (mlsFNo, kangisFileNo, NewKANGISFileno, temp_fileno) may collide if populated
- Check existing records via normalized values before creating new PropID_Master entries

### 4. Conflict Detection
After batch operations, query `vw_prop_id_conflicts` view and halt/alert if any rows appear, indicating duplicate prop_id assignments requiring resolution.

### 5. Provenance Tracking
Record complete provenance for every change:
- `source_table` - originating staging table name
- `source_record_id` - primary key from source table  
- `created_at`/`updated_at` - operation timestamps

### 6. Update Protocol
For existing prop_id records:
- Update supplementary columns (legacy file numbers, notes, metadata) only
- NEVER modify `primary_file_number` unless explicit migration path documented
- Preserve canonical relationships

## Implementation Checklist

- [ ] Normalize all incoming file numbers before comparison
- [ ] Reject temp file number variants from prop_id allocation
- [ ] Verify uniqueness across all normalized file number fields
- [ ] Query conflict view after batch operations
- [ ] Record complete provenance metadata
- [ ] Maintain referential integrity with PropID_Master as authority

## Error Handling

If conflicts detected in `vw_prop_id_conflicts`:
1. Stop processing immediately
2. Log conflict details (file number, conflicting prop_ids, source tables)
3. Require manual data steward resolution before retry
4. Do not attempt automatic conflict resolution

This governance ensures PropID_Master remains the authoritative cross-import key while preserving data integrity across all staging sources.