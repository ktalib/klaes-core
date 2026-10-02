#!/usr/bin/env python3
"""
Quick database test and partition summary
"""

import pyodbc
import sys

def test_connection():
    try:
        print("Testing SQL Server connection...")
        connection_string = "DRIVER={ODBC Driver 17 for SQL Server};SERVER=vmi2583396;DATABASE=klas;Trusted_Connection=yes;"
        connection = pyodbc.connect(connection_string, timeout=10)
        print("✓ Connected successfully")
        
        cursor = connection.cursor()
        
        # Quick count test
        print("Getting total record count...")
        cursor.execute("SELECT COUNT(*) FROM grouping WHERE awaiting_fileno IS NOT NULL AND awaiting_fileno != ''")
        total = cursor.fetchone()[0]
        print(f"Total valid records: {total:,}")
        
        # Quick partition summary
        print("Getting partition summary...")
        cursor.execute("""
        SELECT 
            landuse,
            COUNT(*) as count
        FROM grouping 
        WHERE awaiting_fileno IS NOT NULL AND awaiting_fileno != ''
        GROUP BY landuse
        ORDER BY count DESC
        """)
        
        partitions = cursor.fetchall()
        print("\nPartition Summary by Land Use:")
        for land_use, count in partitions:
            groups = (count + 99) // 100  # Ceiling division
            print(f"{land_use}: {count:,} records → {groups:,} groups")
        
        cursor.close()
        connection.close()
        print("✓ Test completed successfully")
        return True
        
    except Exception as e:
        print(f"✗ Error: {e}")
        return False

if __name__ == "__main__":
    test_connection()