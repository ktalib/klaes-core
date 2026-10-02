-- KLAES Land Admin System Database Inserts
-- Generated from klaes_user_roles.md

--====================================================
-- CLEAR EXISTING DATA (Delete in correct order for foreign keys)
--====================================================
DELETE FROM user_roles;
DELETE FROM user_types;
DELETE FROM departments;

-- Reset identity columns if they exist
DBCC CHECKIDENT ('user_roles', RESEED, 0);
DBCC CHECKIDENT ('user_types', RESEED, 0);
DBCC CHECKIDENT ('departments', RESEED, 0);

--====================================================
-- INSERT INTO user_types
--====================================================
INSERT INTO user_types ([name], [code], [description], [level_priority], [is_active], [created_at], [updated_at])
VALUES 
    ('User', 'USER', 'Basic user with lowest access level', 1, 1, GETDATE(), GETDATE()),
    ('Operations', 'OPS', 'Operational staff with high access level', 2, 1, GETDATE(), GETDATE()),
    ('Management', 'MGT', 'Management level with highest access', 3, 1, GETDATE(), GETDATE()),
    ('System', 'SYS', 'System administrator with highest privileges', 4, 1, GETDATE(), GETDATE()),
    ('ALL', 'ALL', 'Universal access for all user types', 5, 1, GETDATE(), GETDATE());

--====================================================
-- INSERT INTO departments
--====================================================
INSERT INTO departments ([name], [code], [description], [parent_id], [is_active], [created_at], [updated_at])
VALUES 
    ('Customer Service Unit', 'CSU', 'Customer relationship management department', NULL, 1, GETDATE(), GETDATE()),
    ('Lands', 'LANDS', 'Land administration and management department', NULL, 1, GETDATE(), GETDATE()),
    ('Survey', 'SURVEY', 'Land surveying and mapping department', NULL, 1, GETDATE(), GETDATE()),
    ('Geographic Information Systems', 'GIS', 'GIS and spatial data management department', NULL, 1, GETDATE(), GETDATE()),
    ('Kano Geographic Information System', 'KANGIS', 'Kano state GIS operations', NULL, 1, GETDATE(), GETDATE()),
    ('Information and Communication Technology', 'ICT', 'IT systems and administration department', NULL, 1, GETDATE(), GETDATE()),
    ('Account/Finance', 'ACC', 'Accounting and financial management department', NULL, 1, GETDATE(), GETDATE()),
    ('Deeds', 'DEEDS', 'Property deeds and registration department', NULL, 1, GETDATE(), GETDATE()),
    ('Physical Planning', 'PP', 'Physical and urban planning department', NULL, 1, GETDATE(), GETDATE()),
    ('Cadastral', 'CAD', 'Cadastral mapping and records department', NULL, 1, GETDATE(), GETDATE()),
    ('Sectional Titling', 'ST', 'Sectional property titling department', NULL, 1, GETDATE(), GETDATE()),
    ('SLTR', 'SLTR', 'Systematic Land Titling and Registration department', NULL, 1, GETDATE(), GETDATE()),
    ('ALL', 'ALL', 'Universal access for all departments', NULL, 1, GETDATE(), GETDATE());

--====================================================
-- INSERT INTO user_roles
--====================================================
INSERT INTO user_roles ([name], [guard_name], [description], [department_id], [level], [user_type], [is_active], [created_at], [updated_at])
VALUES 
    -- Dashboard (Universal Access)
    ('Dashboard', 'web', 'Universal dashboard access for all users', NULL, 'Lowest', 'ALL', 1, GETDATE(), GETDATE()),
    
    -- Customer Relationship Management
    ('CRM – Person', 'web', 'Manage individual person records in CRM', NULL, 'Lowest', 'ALL', 1, GETDATE(), GETDATE()),
    ('CRM – Corporate', 'web', 'Manage corporate entity records in CRM', NULL, 'Lowest', 'ALL', 1, GETDATE(), GETDATE()),
    ('CRM – Customer Manager', 'web', 'Customer relationship management operations', 1, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    
    -- Programmes
    ('Allocation', 'web', 'Land allocation management', 2, 'Highest', 'Management', 1, GETDATE(), GETDATE()),
    ('Compensation/Resettlement', 'web', 'Handle compensation and resettlement processes', 3, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Recertification – Application', 'web', 'Handle recertification applications', 5, 'Lowest', 'User', 1, GETDATE(), GETDATE()),
    ('Recertification – Migrate Data', 'web', 'Migrate data for recertification process', 6, 'High', 'System', 1, GETDATE(), GETDATE()),
    ('Recertification – Verification Sheet', 'web', 'Manage recertification verification sheets', 2, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('GIS – Data Capture', 'web', 'Capture GIS data for various processes', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Recertification – Vetting Sheet', 'web', 'Handle recertification vetting sheets', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Recertification – EDMS', 'web', 'Electronic document management for recertification', 5, 'Lowest', 'User', 1, GETDATE(), GETDATE()),
    ('Recertification – Certification', 'web', 'Issue recertification certificates', 5, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Recertification – DG''s List', 'web', 'Manage Director General''s recertification list', 5, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Recertification – Governors List', 'web', 'Manage Governor''s recertification list', 5, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Conversion/Regularization', 'web', 'Land conversion and regularization processes', 2, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Land Property Enumeration – Data Repository', 'web', 'Manage land property enumeration data repository', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Land Property Enumeration – Migrate Data', 'web', 'Migrate land property enumeration data', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    
    -- Information Products
    ('Letter of Administration/Grant/Offer Letter', 'web', 'Generate administrative letters and offers', 2, 'Highest', 'Management', 1, GETDATE(), GETDATE()),
    ('Occupancy Permit (OP)', 'web', 'Issue occupancy permits (GIS/Survey departments)', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Site Plan/Parcel Plan', 'web', 'Create and manage site/parcel plans', 3, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Right of Occupancy', 'web', 'Manage right of occupancy documents', 2, 'Highest', 'Management', 1, GETDATE(), GETDATE()),
    ('Certificate of Occupancy', 'web', 'Issue certificates of occupancy', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    
    -- Revenue Management
    ('Billing', 'web', 'Handle billing processes (Account/Finance)', 7, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Generate Receipt', 'web', 'Generate payment receipts (Account/Finance)', 7, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Land Use Charge (LUC)', 'web', 'Manage land use charges', 2, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Bill Balance', 'web', 'Manage billing balances', 8, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    
    -- Deeds
    ('Deeds - Property Records Assistant (Legacy Records)', 'web', 'Access legacy property records', 8, 'Lowest', 'User', 1, GETDATE(), GETDATE()),
    ('Deeds - Instrument Capture (New Records)', 'web', 'Capture new deed instruments', 8, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Deeds - Instrument Registration (New Registration)', 'web', 'Register new deed instruments', 8, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Deeds - Instrument Registration Reports', 'web', 'Generate deed registration reports', 8, 'Highest', 'Management', 1, GETDATE(), GETDATE()),
    
    -- Search
    ('Deeds - Official (for filing purpose)', 'web', 'Official deed searches for filing', 8, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Deeds - On-Premise (Pay-Per-Search)', 'web', 'On-premise deed search services', 8, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Deeds - Legal Search Reports', 'web', 'Generate legal search reports', 8, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    
    -- Lands
    ('Lands - File Tracker/Tracking - RFID', 'web', 'Track land files using RFID system', 2, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('File Digital Library (Doc-WARE)', 'web', 'Access digital library system for all departments', NULL, 'ALL', 'ALL', 1, GETDATE(), GETDATE()),
    ('EDMS', 'web', 'Electronic Document Management System', 2, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    
    -- Physical Planning
    ('PP - Regular Applications', 'web', 'Process regular planning applications', 9, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('PP - ST Applications', 'web', 'Process sectional title planning applications', 9, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('PP - SLTR Applications', 'web', 'Process SLTR planning applications', 9, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('PP Reports', 'web', 'Generate physical planning reports', 9, 'Highest', 'Management', 1, GETDATE(), GETDATE()),
    
    -- Survey
    ('Survey - Records', 'web', 'Manage survey records', 3, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Survey – AI Digital Assistant', 'web', 'AI-powered survey assistance', 3, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Survey - GIS', 'web', 'Survey GIS operations', 3, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Survey - Approvals', 'web', 'Survey approval processes', 3, 'Highest', 'Management', 1, GETDATE(), GETDATE()),
    ('Survey - E-Registry', 'web', 'Electronic survey registry', 3, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Survey Reports', 'web', 'Generate survey reports', 3, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    
    -- Cadastral
    ('Cad - Records', 'web', 'Manage cadastral records', 10, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Cad – AI Digital Assistant', 'web', 'AI-powered cadastral assistance', 10, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Cad - GIS', 'web', 'Cadastral GIS operations', 10, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Cad - Approvals', 'web', 'Cadastral approval processes', 10, 'Highest', 'Management', 1, GETDATE(), GETDATE()),
    ('Cad - E-Registry', 'web', 'Electronic cadastral registry', 10, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Cadastral Reports', 'web', 'Generate cadastral reports', 10, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    
    -- GIS
    ('GIS - Records', 'web', 'Manage GIS records', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('GIS – AI Digital Assistant', 'web', 'AI-powered GIS assistance', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('GIS - GIS', 'web', 'Core GIS operations', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('GIS - Approvals', 'web', 'GIS approval processes', 4, 'Highest', 'Management', 1, GETDATE(), GETDATE()),
    ('GIS - e-Registry', 'web', 'Electronic GIS registry', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('GIS Reports', 'web', 'Generate GIS reports', 4, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    
    -- Sectional Titling
    ('ST - Overview', 'web', 'Sectional titling overview access', 11, 'Lowest', 'ALL', 1, GETDATE(), GETDATE()),
    ('ST - Applications', 'web', 'Process sectional titling applications', 11, 'Lowest', 'User', 1, GETDATE(), GETDATE()),
    ('ST - Field Data Integration', 'web', 'Integrate field data for sectional titling', 11, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('ST – Bills & Payments', 'web', 'Handle sectional titling billing and payments', 11, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('ST – Approvals (Other Departments)', 'web', 'Inter-departmental approvals for sectional titling', 11, 'Lowest', 'ALL', 1, GETDATE(), GETDATE()),
    ('ST – ST Memo', 'web', 'Create and manage sectional titling memos', 11, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('ST – Director''s Approval', 'web', 'Director-level approvals for sectional titling', 11, 'Highest', 'Management', 1, GETDATE(), GETDATE()),
    ('ST - Certificate', 'web', 'Issue sectional titling certificates', 11, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('ST - e-Registry', 'web', 'Electronic sectional titling registry', 11, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('ST - GIS', 'web', 'Sectional titling GIS operations', 11, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('ST - Survey', 'web', 'Sectional titling survey operations', 11, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('ST – Sectional Titling BaseMap', 'web', 'Access sectional titling base maps', 11, 'Lowest', 'ALL', 1, GETDATE(), GETDATE()),
    ('ST - Reports', 'web', 'Generate sectional titling reports', 11, 'Lowest', 'ALL', 1, GETDATE(), GETDATE()),
    
    -- SLTR/First Registration
    ('SLTR - Overview', 'web', 'SLTR process overview', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - Application', 'web', 'Process SLTR applications', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - Claimants', 'web', 'Manage SLTR claimants', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - Legacy Data', 'web', 'Handle SLTR legacy data', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - Field Data', 'web', 'Manage SLTR field data', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - Payments', 'web', 'Handle SLTR payments', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - Approvals', 'web', 'SLTR approval processes', 12, 'Highest', 'Management', 1, GETDATE(), GETDATE()),
    ('SLTR - Planning Recommendation', 'web', 'Provide planning recommendations for SLTR', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - Director SLTR', 'web', 'Director-level SLTR operations', 12, 'Highest', 'Management', 1, GETDATE(), GETDATE()),
    ('SLTR - Other Departments', 'web', 'Inter-departmental SLTR coordination', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - Memo', 'web', 'Create and manage SLTR memos', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - Certificate', 'web', 'Issue SLTR certificates', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - e-Registry', 'web', 'Electronic SLTR registry', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - GIS', 'web', 'SLTR GIS operations', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - Map', 'web', 'SLTR mapping operations', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - Survey', 'web', 'SLTR survey operations', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('SLTR - Reports', 'web', 'Generate SLTR reports', 12, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    
    -- Systems
    ('Caveat', 'web', 'Manage property caveats', 8, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    ('Encumbrance', 'web', 'Manage property encumbrances', 8, 'High', 'Operations', 1, GETDATE(), GETDATE()),
    
    -- Legacy Systems
    ('Legacy System', 'web', 'Access and manage legacy systems', 6, 'Highest', 'System', 1, GETDATE(), GETDATE()),
    
    -- System Admin
    ('User Account', 'web', 'Manage user accounts', 6, 'High', 'System', 1, GETDATE(), GETDATE()),
    ('Departments', 'web', 'Manage departments', 6, 'High', 'System', 1, GETDATE(), GETDATE()),
    ('User Roles', 'web', 'Manage user roles and permissions', 6, 'High', 'System', 1, GETDATE(), GETDATE()),
    ('System Settings', 'web', 'Configure system settings', 6, 'High', 'System', 1, GETDATE(), GETDATE());

--====================================================
-- DROP TABLES (Use with caution - this will delete all data)
--====================================================
/*
-- Drop tables in correct order to handle foreign key dependencies
-- Drop child tables first, then parent tables

DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS user_types;
DROP TABLE IF EXISTS departments;
*/

--====================================================
-- Verification Queries (Optional - for testing)
--====================================================
/*
-- Count records inserted
SELECT 'user_types' as table_name, COUNT(*) as record_count FROM user_types
UNION ALL
SELECT 'departments' as table_name, COUNT(*) as record_count FROM departments
UNION ALL
SELECT 'user_roles' as table_name, COUNT(*) as record_count FROM user_roles;

-- View sample data
SELECT TOP 5 * FROM user_types;
SELECT TOP 5 * FROM departments;
SELECT TOP 5 * FROM user_roles;

-- View roles by department
SELECT d.name as department_name, ur.name as role_name, ur.user_type, ur.level
FROM user_roles ur
LEFT JOIN departments d ON ur.department_id = d.id
ORDER BY d.name, ur.name;
*/