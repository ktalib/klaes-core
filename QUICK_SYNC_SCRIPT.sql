-- =====================================================
-- QUICK START: Entity & Customer Backfill
-- =====================================================
-- This is a simplified, step-by-step version
-- Run each section independently for safety
-- =====================================================

-- ========== STEP 1: BACKUP & SAFETY CHECK ==========
-- RECOMMENDED: Create backup before proceeding
-- BACKUP DATABASE [your_database_name] TO DISK = 'C:\backups\backup_before_sync.bak';

-- ========== STEP 2: CHECK CURRENT STATE ==========
-- How many files are missing entities?
SELECT COUNT(DISTINCT fi.file_number) AS files_missing_entities
FROM file_indexings fi
LEFT JOIN entities_staging e ON fi.file_number = e.file_number
WHERE e.id IS NULL
  AND fi.file_number NOT LIKE 'DCIV-%'
  AND fi.file_number NOT LIKE 'LPPC-%'
  AND fi.workflow_status = 'indexed';

-- How many files are missing customers?
SELECT COUNT(DISTINCT fi.file_number) AS files_missing_customers
FROM file_indexings fi
LEFT JOIN customers_staging c ON fi.file_number = c.file_number
WHERE c.id IS NULL
  AND fi.file_number NOT LIKE 'DCIV-%'
  AND fi.file_number NOT LIKE 'LPPC-%'
  AND fi.workflow_status = 'indexed';

-- Sample of missing records
SELECT TOP 10
    fi.id,
    fi.file_number,
    fi.file_title,
    CASE WHEN e.id IS NULL THEN 'MISSING' ELSE 'EXISTS' END AS entity_status,
    CASE WHEN c.id IS NULL THEN 'MISSING' ELSE 'EXISTS' END AS customer_status,
    fi.created_at
FROM file_indexings fi
LEFT JOIN entities_staging e ON fi.file_number = e.file_number
LEFT JOIN customers_staging c ON fi.file_number = c.file_number
WHERE (e.id IS NULL OR c.id IS NULL)
  AND fi.file_number NOT LIKE 'DCIV-%'
  AND fi.file_number NOT LIKE 'LPPC-%'
  AND fi.workflow_status = 'indexed'
ORDER BY fi.created_at DESC;

-- ========== STEP 3: BACKFILL ENTITIES ==========
-- Create missing entities from file_indexings
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
FROM file_indexings fi
WHERE NOT EXISTS (
    SELECT 1 FROM entities_staging e 
    WHERE e.file_number = fi.file_number
)
  AND fi.file_number NOT LIKE 'DCIV-%'
  AND fi.file_number NOT LIKE 'LPPC-%'
  AND fi.workflow_status = 'indexed';

PRINT CONCAT('Entities created: ', @@ROWCOUNT);

-- ========== STEP 4: BACKFILL CUSTOMERS ==========
-- Create missing customers and link to entities
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
    fi.file_number AS account_no,
    ISNULL(fi.property_address, fi.location) AS property_address,
    e.id AS entity_id,
    'Active' AS status,
    ISNULL(fi.created_by, 1) AS created_by,
    GETDATE() AS created_at,
    GETDATE() AS updated_at
FROM file_indexings fi
INNER JOIN entities_staging e ON fi.file_number = e.file_number
WHERE NOT EXISTS (
    SELECT 1 FROM customers_staging c 
    WHERE c.file_number = fi.file_number
)
  AND fi.file_number NOT LIKE 'DCIV-%'
  AND fi.file_number NOT LIKE 'LPPC-%'
  AND fi.workflow_status = 'indexed';

PRINT CONCAT('Customers created: ', @@ROWCOUNT);

-- ========== STEP 5: VERIFY BACKFILL ==========
-- Verify no files are still missing entities
SELECT COUNT(DISTINCT fi.file_number) AS still_missing_entities
FROM file_indexings fi
LEFT JOIN entities_staging e ON fi.file_number = e.file_number
WHERE e.id IS NULL
  AND fi.file_number NOT LIKE 'DCIV-%'
  AND fi.file_number NOT LIKE 'LPPC-%'
  AND fi.workflow_status = 'indexed';

-- Verify no files are still missing customers
SELECT COUNT(DISTINCT fi.file_number) AS still_missing_customers
FROM file_indexings fi
LEFT JOIN customers_staging c ON fi.file_number = c.file_number
WHERE c.id IS NULL
  AND fi.file_number NOT LIKE 'DCIV-%'
  AND fi.file_number NOT LIKE 'LPPC-%'
  AND fi.workflow_status = 'indexed';

-- Show final summary
SELECT 
    COUNT(DISTINCT fi.file_number) AS total_indexed_files,
    COUNT(DISTINCT e.file_number) AS files_with_entities,
    COUNT(DISTINCT c.file_number) AS files_with_customers
FROM file_indexings fi
LEFT JOIN entities_staging e ON fi.file_number = e.file_number
LEFT JOIN customers_staging c ON fi.file_number = c.file_number
WHERE fi.file_number NOT LIKE 'DCIV-%'
  AND fi.file_number NOT LIKE 'LPPC-%'
  AND fi.workflow_status = 'indexed';

-- ========== OPTIONAL: AUDIT RECENT CHANGES ==========
-- View recently created entities
SELECT TOP 20 id, entity_name, file_number, entity_type, created_at
FROM entities_staging
WHERE created_at >= DATEADD(HOUR, -1, GETDATE())
ORDER BY created_at DESC;

-- View recently created customers
SELECT TOP 20 id, customer_name, file_number, entity_id, status, created_at
FROM customers_staging
WHERE created_at >= DATEADD(HOUR, -1, GETDATE())
ORDER BY created_at DESC;
