-- =====================================================
-- FILE INDEXING: CHECK AND BACKFILL MISSING ENTITIES & CUSTOMERS
-- =====================================================
-- Purpose: Identify and backfill missing entity and customer records 
--          from file_indexings table to their respective staging tables
-- Exclusions: DCIV- and LPPC- file numbers are excluded
-- Author: System Admin
-- Date: March 2, 2026
-- =====================================================

-- =====================================================
-- PART 1: CHECK & REPORT MISSING RECORDS
-- =====================================================
-- This section identifies missing entity and customer records
-- without making any changes

PRINT '================================================='
PRINT 'STEP 1: IDENTIFYING MISSING ENTITY & CUSTOMER RECORDS'
PRINT '================================================='

-- Create temp table for analysis
CREATE TABLE #MissingRecords (
    file_indexing_id INT,
    file_number NVARCHAR(255),
    file_title NVARCHAR(255),
    entity_type NVARCHAR(50),
    customer_type NVARCHAR(50),
    property_address NVARCHAR(MAX),
    workflow_status NVARCHAR(50),
    has_entity BIT,
    has_customer BIT,
    created_at DATETIME,
    PRIMARY KEY (file_indexing_id)
);

-- Identify files that should have entities and customers
-- Exclude DCIV- and LPPC- prefixes
INSERT INTO #MissingRecords
SELECT 
    fi.id,
    fi.file_number,
    fi.file_title,
    ISNULL(fi.entity_type, 'Individual') AS entity_type,
    ISNULL(fi.customer_type, 'Individual') AS customer_type,
    ISNULL(fi.property_address, fi.location) AS property_address,
    fi.workflow_status,
    CASE WHEN e.id IS NOT NULL THEN 1 ELSE 0 END AS has_entity,
    CASE WHEN c.id IS NOT NULL THEN 1 ELSE 0 END AS has_customer,
    fi.created_at
FROM 
    file_indexings fi
LEFT JOIN 
    entities_staging e ON fi.file_number = e.file_number
LEFT JOIN 
    customers_staging c ON fi.file_number = c.file_number
WHERE 
    -- Exclude DCIV and LPPC file numbers
    NOT (fi.file_number LIKE 'DCIV-%' OR fi.file_number LIKE 'LPPC-%')
    -- Only include indexed files
    AND fi.workflow_status = 'indexed'
    -- Only consider files created recently (last 90 days) - adjust as needed
    AND fi.created_at >= DATEADD(DAY, -90, CAST(GETDATE() AS DATE))
    -- Exclude Block indexing (they use different logic)
    AND fi.indexing_type != 'Block'
ORDER BY 
    fi.created_at DESC;

-- Report: Files missing entities
PRINT ''
PRINT '--- FILES MISSING ENTITIES ---'
SELECT 
    COUNT(*) AS missing_entity_count,
    COUNT(DISTINCT file_number) AS unique_files
FROM #MissingRecords
WHERE has_entity = 0;

-- Report: Files missing customers
PRINT ''
PRINT '--- FILES MISSING CUSTOMERS ---'
SELECT 
    COUNT(*) AS missing_customer_count,
    COUNT(DISTINCT file_number) AS unique_files
FROM #MissingRecords
WHERE has_customer = 0;

-- Report: Files missing both
PRINT ''
PRINT '--- FILES MISSING BOTH ENTITY & CUSTOMER ---'
SELECT 
    COUNT(*) AS missing_both_count,
    COUNT(DISTINCT file_number) AS unique_files
FROM #MissingRecords
WHERE has_entity = 0 AND has_customer = 0;

-- Detailed report of missing records
PRINT ''
PRINT '--- DETAILED MISSING RECORDS ---'
SELECT 
    file_number,
    file_title,
    entity_type,
    customer_type,
    has_entity,
    has_customer,
    CASE 
        WHEN has_entity = 0 AND has_customer = 0 THEN 'BOTH MISSING'
        WHEN has_entity = 0 THEN 'ENTITY MISSING'
        WHEN has_customer = 0 THEN 'CUSTOMER MISSING'
    END AS missing_type,
    workflow_status,
    created_at
FROM #MissingRecords
WHERE has_entity = 0 OR has_customer = 0
ORDER BY created_at DESC;

-- =====================================================
-- PART 2: BACKFILL MISSING ENTITY RECORDS
-- =====================================================
-- This section actually creates the missing entity records

PRINT ''
PRINT '================================================='
PRINT 'STEP 2: BACKFILLING MISSING ENTITY RECORDS'
PRINT '================================================='

DECLARE @entity_count INT = 0;

-- Get next identity value if needed
DECLARE @next_entity_id INT = (SELECT MAX(id) + 1 FROM entities_staging);
IF @next_entity_id IS NULL SET @next_entity_id = 1;

-- Insert missing entities
INSERT INTO entities_staging (
    entity_name,
    entity_type,
    file_number,
    created_at,
    updated_at
)
SELECT DISTINCT
    ISNULL(fi.file_title, fi.file_number) AS entity_name,
    ISNULL(fi.entity_type, 'Individual') AS entity_type,
    fi.file_number,
    GETDATE() AS created_at,
    GETDATE() AS updated_at
FROM 
    #MissingRecords mr
    INNER JOIN file_indexings fi ON mr.file_indexing_id = fi.id
WHERE 
    mr.has_entity = 0
    AND fi.file_number NOT LIKE 'DCIV-%'
    AND fi.file_number NOT LIKE 'LPPC-%';

SET @entity_count = @@ROWCOUNT;
PRINT CONCAT('Inserted ', @entity_count, ' missing entity records');

-- =====================================================
-- PART 3: BACKFILL MISSING CUSTOMER RECORDS
-- =====================================================
-- This section creates the missing customer records

PRINT ''
PRINT '================================================='
PRINT 'STEP 3: BACKFILLING MISSING CUSTOMER RECORDS'
PRINT '================================================='

DECLARE @customer_count INT = 0;

-- Insert missing customers
-- Match with their newly created entities
INSERT INTO customers_staging (
    customer_name,
    customer_type,
    file_number,
    account_no,
    property_address,
    entity_id,
    status,
    created_by,
    created_at,
    updated_at
)
SELECT 
    ISNULL(fi.file_title, fi.file_number) AS customer_name,
    ISNULL(fi.customer_type, 'Individual') AS customer_type,
    fi.file_number,
    fi.file_number AS account_no,  -- Use file_number as account_no default
    ISNULL(fi.property_address, fi.location) AS property_address,
    e.id AS entity_id,  -- Link to the entity we created or existing entity
    'Active' AS status,
    ISNULL(fi.created_by, 1) AS created_by,  -- Default to system user if not set
    GETDATE() AS created_at,
    GETDATE() AS updated_at
FROM 
    #MissingRecords mr
    INNER JOIN file_indexings fi ON mr.file_indexing_id = fi.id
    INNER JOIN entities_staging e ON fi.file_number = e.file_number
WHERE 
    mr.has_customer = 0
    AND fi.file_number NOT LIKE 'DCIV-%'
    AND fi.file_number NOT LIKE 'LPPC-%';

SET @customer_count = @@ROWCOUNT;
PRINT CONCAT('Inserted ', @customer_count, ' missing customer records');

-- =====================================================
-- PART 4: SUMMARY REPORT
-- =====================================================

PRINT ''
PRINT '================================================='
PRINT 'BACKFILL SUMMARY REPORT'
PRINT '================================================='

DECLARE @summary_entities INT = (SELECT COUNT(DISTINCT file_number) FROM entities_staging);
DECLARE @summary_customers INT = (SELECT COUNT(DISTINCT file_number) FROM customers_staging);

SELECT 
    'Total Entities Created' AS metric,
    @entity_count AS count_affected
UNION ALL
SELECT 
    'Total Customers Created' AS metric,
    @customer_count AS count_affected
UNION ALL
SELECT 
    'Total Entities in Staging (after backfill)' AS metric,
    @summary_entities AS count_affected
UNION ALL
SELECT 
    'Total Customers in Staging (after backfill)' AS metric,
    @summary_customers AS count_affected;

-- =====================================================
-- PART 5: VALIDATION
-- =====================================================
-- Verify that backfill was successful

PRINT ''
PRINT '================================================='
PRINT 'VALIDATION: Checking if backfill was complete'
PRINT '================================================='

SELECT 
    COUNT(*) AS still_missing_entities
FROM 
    file_indexings fi
WHERE 
    NOT EXISTS (SELECT 1 FROM entities_staging e WHERE e.file_number = fi.file_number)
    AND fi.file_number NOT LIKE 'DCIV-%'
    AND fi.file_number NOT LIKE 'LPPC-%'
    AND fi.workflow_status = 'indexed'
    AND fi.created_at >= DATEADD(DAY, -90, CAST(GETDATE() AS DATE));

SELECT 
    COUNT(*) AS still_missing_customers
FROM 
    file_indexings fi
WHERE 
    NOT EXISTS (SELECT 1 FROM customers_staging c WHERE c.file_number = fi.file_number)
    AND fi.file_number NOT LIKE 'DCIV-%'
    AND fi.file_number NOT LIKE 'LPPC-%'
    AND fi.workflow_status = 'indexed'
    AND fi.created_at >= DATEADD(DAY, -90, CAST(GETDATE() AS DATE));

-- =====================================================
-- CLEANUP
-- =====================================================

DROP TABLE #MissingRecords;

PRINT ''
PRINT '================================================='
PRINT 'BACKFILL PROCESS COMPLETE'
PRINT '================================================='
