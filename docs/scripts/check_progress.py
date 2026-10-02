#!/usr/bin/env python3
"""
Quick progress check
"""

import pyodbc

def check_progress():
    try:
        connection_string = "DRIVER={ODBC Driver 17 for SQL Server};SERVER=vmi2583396;DATABASE=klas;Trusted_Connection=yes;"
        connection = pyodbc.connect(connection_string, timeout=10)
        
        cursor = connection.cursor()
        
        # Check assignment progress
        cursor.execute("SELECT COUNT(*) FROM grouping WHERE [group] IS NOT NULL")
        with_group = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) FROM grouping")
        total = cursor.fetchone()[0]
        
        percentage = (with_group / total * 100) if total > 0 else 0
        
        print(f"Progress: {with_group:,}/{total:,} records assigned ({percentage:.2f}%)")
        
        if with_group > 0:
            # Show sample
            cursor.execute("SELECT TOP 3 awaiting_fileno, [group], sys_batch_no, shelf_rack FROM grouping WHERE [group] IS NOT NULL")
            samples = cursor.fetchall()
            print("Sample assignments:")
            for sample in samples:
                print(f"  {sample[0]} → Group {sample[1]}, Batch {sample[2]}, Shelf {sample[3]}")
            
            # Show group distribution
            cursor.execute("SELECT COUNT(DISTINCT [group]) FROM grouping WHERE [group] IS NOT NULL")
            groups_assigned = cursor.fetchone()[0]
            print(f"Groups assigned: {groups_assigned:,}")
        
        # Check shelf labels
        cursor.execute("SELECT COUNT(*) FROM Rack_Shelf_Labels WHERE is_used = 0")
        available_labels = cursor.fetchone()[0]
        print(f"Available shelf labels: {available_labels:,}")
        
        cursor.close()
        connection.close()
        
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    check_progress()