SELECT f.id AS indexing_id,
       COALESCE(NULLIF(LTRIM(RTRIM(f.mls_file_no)), ''), LTRIM(RTRIM(f.file_number))) AS file_number,
       f.file_title,
       COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(u.first_name, ' ', u.last_name))), ''),
                NULLIF(LTRIM(RTRIM(CONVERT(NVARCHAR(255), f.created_by))), ''), 'Not recorded') AS indexed_by,
       f.created_at AS indexed_at, f.source AS indexing_source
FROM file_indexings f
LEFT JOIN users u ON u.id = TRY_CONVERT(BIGINT, f.created_by)
WHERE ISNULL(f.is_deleted, 0) = 0
  AND COALESCE(NULLIF(LTRIM(RTRIM(f.mls_file_no)), ''), LTRIM(RTRIM(f.file_number))) LIKE '%-2026-%'
  AND REPLACE(UPPER(ISNULL(f.general_registry, '')), ' ', '') IN ('LANDSREGISTRY', 'LANDREGISTRY')
  AND EXISTS (SELECT 1 FROM fileNumber fn
              WHERE (fn.mlsfNo = f.file_number OR fn.mlsfNo = f.mls_file_no)
                AND LOWER(LTRIM(RTRIM(fn.SOURCE))) = 'indexing')
  AND NOT EXISTS (SELECT 1 FROM mls_file_no m
                  WHERE m.full_file_number = f.file_number OR m.full_file_number = f.mls_file_no)
  AND NOT EXISTS (SELECT 1 FROM file_commissioning_sheets cs
                  WHERE cs.file_number = f.file_number OR cs.file_number = f.mls_file_no)
  AND NOT EXISTS (SELECT 1 FROM fileNumber fn
                  WHERE (fn.mlsfNo = f.file_number OR fn.mlsfNo = f.mls_file_no)
                    AND (fn.commissioning_date IS NOT NULL
                         OR UPPER(ISNULL(fn.SOURCE, '')) LIKE '%COMMISSION%'
                         OR UPPER(ISNULL(fn.SOURCE, '')) LIKE 'OSS%'))
ORDER BY file_number, f.id
