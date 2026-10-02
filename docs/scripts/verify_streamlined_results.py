"""
Quick verification script for streamlined generation results
"""

import pyodbc
import pandas as pd

def verify_results():
    """Verify the streamlined generation results"""
    
    # Database connection (from Laravel .env)
    server = 'VMI2583396'
    database = 'klas'
    username = 'klas'
    password = 'YourStrongPassword123!'
    
    connection_string = (
        f"DRIVER={{ODBC Driver 17 for SQL Server}};"
        f"SERVER={server};"
        f"DATABASE={database};"
        f"UID={username};"
        f"PWD={password};"
        "TrustServerCertificate=yes;"
    )
    
    try:
        connection = pyodbc.connect(connection_string)
        
        print("🔍 VERIFYING STREAMLINED GENERATION RESULTS")
        print("=" * 50)
        
        # Total count
        cursor = connection.cursor()
        cursor.execute("SELECT COUNT(*) FROM grouping")
        total_count = cursor.fetchone()[0]
        print(f"📊 Total Records: {total_count:,}")
        
        # Land use breakdown
        cursor.execute("""
            SELECT landuse, COUNT(*) as count,
                   CAST(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM grouping) AS DECIMAL(5,2)) as percentage
            FROM grouping 
            GROUP BY landuse 
            ORDER BY COUNT(*) DESC
        """)
        
        print(f"\n🏷️  Land Use Distribution:")
        landuse_results = cursor.fetchall()
        for row in landuse_results:
            print(f"  {row[0]:<12}: {row[1]:>9,} ({row[2]:>5.2f}%)")
        
        # Column verification
        cursor.execute("""
            SELECT TOP 5 awaiting_fileno, number, year, created_by, landuse, created_at
            FROM grouping 
            ORDER BY awaiting_fileno
        """)
        
        print(f"\n📋 Sample Records:")
        samples = cursor.fetchall()
        for row in samples:
            print(f"  {row[0]} | {row[1]} | {row[2]} | {row[3]} | {row[4]} | {row[5]}")
        
        # Validation
        expected = 2700000
        print(f"\n✅ Validation:")
        print(f"  Expected: {expected:,}")
        print(f"  Actual:   {total_count:,}")
        print(f"  Match:    {'✅ YES' if total_count == expected else '❌ NO'}")
        
        if total_count == expected:
            print(f"\n🎉 SUCCESS: Perfect generation!")
        else:
            print(f"\n⚠️  WARNING: Count mismatch!")
        
        cursor.close()
        connection.close()
        
    except Exception as e:
        print(f"❌ Error: {e}")

if __name__ == "__main__":
    verify_results()