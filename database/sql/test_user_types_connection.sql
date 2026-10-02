-- Test script to verify user types and levels data
-- Run this in SQL Server Management Studio or similar tool

-- Check if tables exist
SELECT 
    TABLE_NAME,
    TABLE_TYPE
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_NAME IN ('user_types', 'user_levels')
ORDER BY TABLE_NAME;

-- Check user types data
SELECT 
    id,
    name,
    code,
    level_priority,
    is_active,
    created_at
FROM user_types
ORDER BY level_priority DESC;

-- Check user levels data
SELECT 
    ul.id,
    ul.name as level_name,
    ul.code as level_code,
    ut.name as user_type_name,
    ul.priority,
    ul.is_active
FROM user_levels ul
INNER JOIN user_types ut ON ul.user_type_id = ut.id
ORDER BY ut.level_priority DESC, ul.priority DESC;

-- Check Operations user type levels specifically
SELECT 
    ut.name as UserType,
    ul.name as UserLevel,
    ul.code as LevelCode,
    ul.priority
FROM user_types ut
INNER JOIN user_levels ul ON ut.id = ul.user_type_id
WHERE ut.name = 'Operations'
ORDER BY ul.priority DESC;

-- Count records
SELECT 
    'User Types' as TableName, 
    COUNT(*) as RecordCount 
FROM user_types
WHERE is_active = 1
UNION ALL
SELECT 
    'User Levels' as TableName, 
    COUNT(*) as RecordCount 
FROM user_levels
WHERE is_active = 1;