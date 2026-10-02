#!/usr/bin/env python3
"""
Monitor grouping metadata assignment progress
"""

import pyodbc
import time
import sys

def monitor_progress():
    try:
        connection_string = "DRIVER={ODBC Driver 17 for SQL Server};SERVER=vmi2583396;DATABASE=klas;Trusted_Connection=yes;"
        connection = pyodbc.connect(connection_string, timeout=10)
        
        cursor = connection.cursor()
        
        while True:
            # Check how many records have been updated
            cursor.execute("SELECT COUNT(*) FROM grouping WHERE [group] IS NOT NULL")
            with_group = cursor.fetchone()[0]
            
            cursor.execute("SELECT COUNT(*) FROM grouping WHERE sys_batch_no IS NOT NULL")
            with_batch = cursor.fetchone()[0]
            
            cursor.execute("SELECT COUNT(*) FROM grouping WHERE shelf_rack IS NOT NULL")
            with_shelf = cursor.fetchone()[0]
            
            cursor.execute("SELECT COUNT(*) FROM grouping")
            total = cursor.fetchone()[0]
            
            print(f"Progress: Group={with_group:,}/{total:,} ({with_group/total*100:.1f}%), Batch={with_batch:,}, Shelf={with_shelf:,}")
            
            if with_group > 0:
                # Show sample of assigned data
                cursor.execute("SELECT TOP 5 id, awaiting_fileno, [group], sys_batch_no, shelf_rack FROM grouping WHERE [group] IS NOT NULL ORDER BY id")
                samples = cursor.fetchall()
                print("Sample assigned records:")
                for sample in samples:
                    print(f"  ID {sample[0]}: {sample[1]} → Group {sample[2]}, Batch {sample[3]}, Shelf {sample[4]}")
            
            time.sleep(30)  # Check every 30 seconds
            
    except KeyboardInterrupt:
        print("\nMonitoring stopped")
    except Exception as e:
        print(f"Error: {e}")
    finally:
        if 'connection' in locals():
            connection.close()

if __name__ == "__main__":
    monitor_progress()