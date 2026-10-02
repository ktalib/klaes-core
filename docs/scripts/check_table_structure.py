import pyodbc

conn = pyodbc.connect(
    "DRIVER={ODBC Driver 17 for SQL Server};"
    "SERVER=localhost;"
    "DATABASE=klas;"
    "Trusted_Connection=yes;"
)

cursor = conn.cursor()

# Get table structure
cursor.execute("""
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'Rack_Shelf_Labels'
ORDER BY ORDINAL_POSITION
""")

print("Rack_Shelf_Labels table structure:")
for row in cursor.fetchall():
    print(f"  {row[0]}: {row[1]} (nullable: {row[2]}, default: {row[3]})")

# Get a sample row
cursor.execute("SELECT TOP 1 * FROM Rack_Shelf_Labels")
sample = cursor.fetchone()
if sample:
    print(f"\nSample row: {sample}")
else:
    print("\nNo existing rows found")

conn.close()