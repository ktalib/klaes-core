import pyodbc

conn = pyodbc.connect('DRIVER={ODBC Driver 17 for SQL Server};SERVER=vmi2583396;DATABASE=klas;Trusted_Connection=yes;')
cur = conn.cursor()
cur.execute(
    """
    SELECT TOP 10
        awaiting_fileno,
        [group] AS group_number,
        sys_batch_no,
        shelf_rack
    FROM grouping WITH (NOLOCK)
    OUTER APPLY (SELECT CHARINDEX('-', REVERSE(awaiting_fileno)) AS dash_pos) dash
    OUTER APPLY (
        SELECT CASE
            WHEN dash.dash_pos > 1
                THEN TRY_CONVERT(INT, RIGHT(awaiting_fileno, dash.dash_pos - 1))
            ELSE NULL
        END AS serial_number
    ) serial
    WHERE awaiting_fileno IS NOT NULL AND LTRIM(RTRIM(awaiting_fileno)) <> ''
    ORDER BY
        CASE WHEN awaiting_fileno LIKE 'CON-%' THEN 1 ELSE 0 END,
        CASE UPPER(ISNULL(landuse, ''))
            WHEN 'RESIDENTIAL' THEN 0
            WHEN 'COMMERCIAL' THEN 1
            WHEN 'AGRICULTURE' THEN 2
            WHEN 'INDUSTRIAL' THEN 3
            WHEN 'MIXED' THEN 4
            ELSE 9
        END,
        COALESCE(TRY_CONVERT(INT, year), 0),
        COALESCE(serial.serial_number, 0),
        awaiting_fileno
    """
)
rows = cur.fetchall()
for row in rows:
    print(f"{row.awaiting_fileno:20} | group={row.group_number} | sys_batch={row.sys_batch_no} | shelf={row.shelf_rack}")

cur.close()
conn.close()
