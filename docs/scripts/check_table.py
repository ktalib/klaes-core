import pyodbc

conn = pyodbc.connect('DRIVER={ODBC Driver 17 for SQL Server};SERVER=localhost;DATABASE=klas;Trusted_Connection=yes;')
cursor = conn.cursor()

# Get table structure
cursor.execute("SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Rack_Shelf_Labels' ORDER BY ORDINAL_POSITION")
print('Rack_Shelf_Labels table schema:')
for row in cursor.fetchall():
    print(f'  {row[0]}: nullable={row[1]}, default={row[2]}')

# Get sample data
cursor.execute('SELECT TOP 3 * FROM Rack_Shelf_Labels')
columns = [desc[0] for desc in cursor.description]
print(f'\nColumns: {columns}')
print('\nSample data:')
for row in cursor.fetchall():
    print(f'  {row}')

conn.close()