-- Indexes to speed up Property Record Assistant queries on SQL Server
-- Run in the target database and re-run if tables are rebuilt.

-- Provided indexes
CREATE NONCLUSTERED INDEX IX_pra_owner_status_created
ON dbo.pra (owner_id, status, created_at);

CREATE NONCLUSTERED INDEX IX_pra_property
ON dbo.pra (property_id);

-- Lookup and filtering indexes used by the assistant
CREATE NONCLUSTERED INDEX IX_pra_file_numbers
ON dbo.pra (kangisFileNo, mlsFNo, NewKANGISFileno);

CREATE NONCLUSTERED INDEX IX_pra_instrument_created
ON dbo.pra (instrument_type, title_type, created_at);

CREATE NONCLUSTERED INDEX IX_pra_prop_created
ON dbo.pra (prop_id, created_at);
