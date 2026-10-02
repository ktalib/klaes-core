import pyodbc

conn = pyodbc.connect('DRIVER={ODBC Driver 17 for SQL Server};SERVER=localhost;DATABASE=klas;Trusted_Connection=yes;')
cursor = conn.cursor()

# Check if id column is auto-increment
cursor.execute("""
SELECT 
    COLUMN_NAME,
    IS_NULLABLE,
    DATA_TYPE,
    COLUMNPROPERTY(OBJECT_ID(TABLE_SCHEMA + '.' + TABLE_NAME), COLUMN_NAME, 'IsIdentity') AS is_identity
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'Rack_Shelf_Labels' 
AND COLUMN_NAME = 'id'
""")

row = cursor.fetchone()
print(f'ID column info: {row}')
print(f'Is identity (auto-increment): {"Yes" if row[3] == 1 else "No"}')

conn.close()