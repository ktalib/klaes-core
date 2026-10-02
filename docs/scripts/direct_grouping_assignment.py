#!/usr/bin/env python3
"""
Direct Grouping Assignment - Skip partition analysis, process directly
"""

import pyodbc
import math
import sys

class DirectGroupingAssigner:
    def __init__(self, server="vmi2583396", database="klas"):
        self.server = server
        self.database = database
        self.connection = None
        
    def connect(self):
        """Establish database connection"""
        try:
            print("Connecting to SQL Server...")
            connection_string = f"DRIVER={{ODBC Driver 17 for SQL Server}};SERVER={self.server};DATABASE={self.database};Trusted_Connection=yes;"
            self.connection = pyodbc.connect(connection_string)
            print("✓ Connected successfully")
            return True
        except Exception as e:
            print(f"✗ Connection failed: {e}")
            return False
    
    def disconnect(self):
        """Close database connection"""
        if self.connection:
            self.connection.close()
            print("✓ Database connection closed")
    
    def extract_serial_number(self, file_number):
        """Extract serial number from file number for sorting"""
        if not file_number:
            return 0
        
        # Handle different formats
        if file_number.startswith('RES-') or file_number.startswith('COM-') or file_number.startswith('AGR-'):
            # Format: XXX-YYYY-SERIAL
            parts = file_number.split('-')
            if len(parts) >= 3:
                try:
                    return int(parts[2])
                except ValueError:
                    return 0
        elif file_number.startswith('AG-'):
            # Format: AG-YYYY-SERIAL
            parts = file_number.split('-')
            if len(parts) >= 3:
                try:
                    return int(parts[2])
                except ValueError:
                    return 0
        
        return 0
    
    def process_specific_partitions(self, partitions_to_process, batch_size=1000):
        """Process specific partitions efficiently"""
        total_processed = 0
        
        for land_use, year in partitions_to_process:
            print(f"\nProcessing {land_use} {year}...")
            
            # Get unprocessed records for this partition
            query = """
            SELECT id, awaiting_fileno
            FROM grouping 
            WHERE landuse = ? 
            AND year = ?
            AND awaiting_fileno IS NOT NULL 
            AND awaiting_fileno != ''
            AND [group] IS NULL
            ORDER BY awaiting_fileno
            """
            
            cursor = self.connection.cursor()
            cursor.execute(query, land_use, year)
            records = cursor.fetchall()
            cursor.close()
            
            if not records:
                print(f"  No unprocessed records found")
                continue
            
            print(f"  Found {len(records):,} unprocessed records")
            
            # Sort by extracted serial number for proper grouping
            records_with_serial = [(r.id, r.awaiting_fileno, self.extract_serial_number(r.awaiting_fileno)) for r in records]
            records_with_serial.sort(key=lambda x: x[2])  # Sort by serial number
            
            # Assign groups (100 files per group)
            group_num = 1
            updates = []
            
            for i, (record_id, file_number, serial) in enumerate(records_with_serial):
                if i > 0 and i % 100 == 0:
                    group_num += 1
                
                updates.append((group_num, group_num, group_num, record_id))
            
            print(f"  Assigning {len(updates):,} records to {group_num} groups...")
            
            # Update in batches
            cursor = self.connection.cursor()
            update_query = """
            UPDATE grouping 
            SET [group] = ?, sys_batch_no = ?, shelf_rack = ?
            WHERE id = ?
            """
            
            for i in range(0, len(updates), batch_size):
                batch = updates[i:i + batch_size]
                cursor.executemany(update_query, batch)
                self.connection.commit()
                
                progress = min(i + batch_size, len(updates))
                print(f"    Batch {i//batch_size + 1}/{math.ceil(len(updates)/batch_size)}: {progress:,}/{len(updates):,} records")
            
            cursor.close()
            total_processed += len(updates)
            print(f"  ✓ Completed {land_use} {year} - {len(updates):,} records assigned")
            print(f"  Total processed so far: {total_processed:,} records")
        
        return total_processed
    
    def run_batch_assignment(self, start_year=1981, end_year=1985):
        """Run assignment for a specific year range"""
        if not self.connect():
            return False
        
        try:
            # Define partitions to process
            land_uses = ['AGRICULTURE', 'COMMERCIAL', 'RESIDENTIAL']
            partitions = []
            
            for year in range(start_year, end_year + 1):
                for land_use in land_uses:
                    partitions.append((land_use, year))
            
            print(f"Processing {len(partitions)} partitions from {start_year} to {end_year}")
            print(f"Land uses: {', '.join(land_uses)}")
            
            total_processed = self.process_specific_partitions(partitions)
            
            print(f"\n{'='*50}")
            print(f"BATCH ASSIGNMENT COMPLETE!")
            print(f"Processed {total_processed:,} records across {len(partitions)} partitions")
            print(f"Years: {start_year}-{end_year}")
            print(f"{'='*50}")
            
            return True
            
        except Exception as e:
            print(f"✗ Error during processing: {e}")
            return False
        finally:
            self.disconnect()

def main():
    if len(sys.argv) > 1:
        # Parse year range from command line
        try:
            if '-' in sys.argv[1]:
                start_year, end_year = map(int, sys.argv[1].split('-'))
            else:
                start_year = end_year = int(sys.argv[1])
        except ValueError:
            print("Usage: python script.py [start_year-end_year] or [single_year]")
            print("Example: python script.py 1981-1985")
            print("Example: python script.py 1981")
            sys.exit(1)
    else:
        # Default to first 5 years
        start_year, end_year = 1981, 1985
    
    print(f"Direct Grouping Assignment - Years {start_year} to {end_year}")
    
    assigner = DirectGroupingAssigner()
    success = assigner.run_batch_assignment(start_year, end_year)
    
    if success:
        print("\n✓ Batch assignment completed successfully")
        sys.exit(0)
    else:
        print("\n✗ Batch assignment failed")
        sys.exit(1)

if __name__ == "__main__":
    main()