#!/usr/bin/env python3
"""
Single partition test - process just one land use + year combination
"""

import pyodbc
import math

def test_single_partition():
    try:
        print("Testing single partition assignment...")
        connection_string = "DRIVER={ODBC Driver 17 for SQL Server};SERVER=vmi2583396;DATABASE=klas;Trusted_Connection=yes;"
        connection = pyodbc.connect(connection_string, timeout=30)
        
        cursor = connection.cursor()
        
        # Test with AGRICULTURE 1981 (should be 20k records)
        land_use = "AGRICULTURE"
        year = 1981
        
        print(f"Processing {land_use} {year}...")
        
        # Get records for this partition
        query = """
        SELECT TOP 500 id, awaiting_fileno
        FROM grouping 
        WHERE landuse = ? 
        AND year = ?
        AND awaiting_fileno IS NOT NULL 
        AND awaiting_fileno != ''
        AND [group] IS NULL
        ORDER BY awaiting_fileno
        """
        
        cursor.execute(query, land_use, year)
        records = cursor.fetchall()
        
        print(f"Found {len(records)} unprocessed records")
        
        if len(records) == 0:
            print("No unprocessed records found - checking existing assignments...")
            cursor.execute("SELECT COUNT(*) FROM grouping WHERE landuse = ? AND year = ? AND [group] IS NOT NULL", land_use, year)
            existing = cursor.fetchone()[0]
            print(f"Already processed: {existing} records")
            return
        
        # Assign groups to first 500 records (5 groups)
        updates = []
        group_num = 1
        
        for i, record in enumerate(records):
            if i > 0 and i % 100 == 0:
                group_num += 1
            
            updates.append((group_num, group_num, group_num, record.id))
        
        print(f"Assigning {len(updates)} records to {group_num} groups...")
        
        # Update in batches
        update_query = """
        UPDATE grouping 
        SET [group] = ?, sys_batch_no = ?, shelf_rack = ?
        WHERE id = ?
        """
        
        batch_size = 100
        for i in range(0, len(updates), batch_size):
            batch = updates[i:i + batch_size]
            cursor.executemany(update_query, batch)
            connection.commit()
            print(f"Updated batch {i//batch_size + 1}/{math.ceil(len(updates)/batch_size)}")
        
        print(f"✓ Successfully assigned {len(updates)} records")
        
        # Verify updates
        cursor.execute("SELECT COUNT(*) FROM grouping WHERE landuse = ? AND year = ? AND [group] IS NOT NULL", land_use, year)
        updated_count = cursor.fetchone()[0]
        print(f"Total updated records for {land_use} {year}: {updated_count}")
        
        cursor.close()
        connection.close()
        print("✓ Test completed successfully")
        return True
        
    except Exception as e:
        print(f"✗ Error: {e}")
        return False

if __name__ == "__main__":
    test_single_partition()