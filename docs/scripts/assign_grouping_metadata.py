#!/usr/bin/env python3
"""
Grouping Metadata Assignment Script
Populates group, sys_batch_no, and shelf_rack columns for the grouping table.
"""

import pyodbc
import re
import math
import time
from typing import Dict, List, Tuple, Optional
from collections import defaultdict
import string

class GroupingMetadataAssigner:
    def __init__(self, connection_string: str, batch_size: int = 10000):
        self.connection_string = connection_string
        self.batch_size = batch_size
        self.conn = None
        self.GROUP_SIZE = 100
        
    def connect(self):
        """Establish database connection"""
        print("Connecting to SQL Server...")
        self.conn = pyodbc.connect(self.connection_string)
        self.conn.autocommit = False
        print("✓ Connected successfully")
        
    def disconnect(self):
        """Close database connection"""
        if self.conn:
            self.conn.close()
            print("✓ Database connection closed")
            
    def extract_serial_number(self, file_number: str) -> int:
        """Extract numeric serial from file number for ordering"""
        if not file_number:
            return 999999999  # Put nulls at the end
            
        # Try to find the last numeric segment
        matches = re.findall(r'\d+', str(file_number))
        if matches:
            return int(matches[-1])
        return 999999999
        
    def get_grouping_data(self) -> List[Dict]:
        """Fetch all grouping records that need metadata assignment"""
        print("Fetching grouping records...")
        
        cursor = self.conn.cursor()
        query = """
        SELECT 
            id, 
            landuse, 
            year, 
            awaiting_fileno, 
            mls_fileno,
            number,
            [group],
            sys_batch_no,
            shelf_rack
        FROM grouping 
        WHERE deleted_at IS NULL
        ORDER BY 
            ISNULL(NULLIF(landuse, ''), 'UNKNOWN'), 
            ISNULL(year, 0),
            id
        """
        
        cursor.execute(query)
        records = []
        
        count = 0
        while True:
            batch = cursor.fetchmany(5000)
            if not batch:
                break
                
            for row in batch:
                records.append({
                    'id': row[0],
                    'landuse': row[1] or 'UNKNOWN',
                    'year': row[2] or 0,
                    'awaiting_fileno': row[3],
                    'mls_fileno': row[4],
                    'number': row[5],
                    'current_group': row[6],
                    'current_sys_batch': row[7],
                    'current_shelf_rack': row[8]
                })
                
            count += len(batch)
            print(f"  Loaded {count:,} records...")
            
        cursor.close()
        print(f"✓ Total records loaded: {len(records):,}")
        return records
        
    def partition_and_assign_groups(self, records: List[Dict]) -> Dict[Tuple[str, int], List[Dict]]:
        """Partition records by landuse/year and assign group numbers"""
        print("Partitioning records and assigning group numbers...")
        
        partitions = defaultdict(list)
        
        # Group by landuse/year
        for record in records:
            key = (record['landuse'], record['year'])
            partitions[key].append(record)
            
        print(f"✓ Found {len(partitions)} landuse/year partitions")
        
        total_groups = 0
        
        # Sort each partition and assign group numbers
        for partition_key, partition_records in partitions.items():
            landuse, year = partition_key
            
            # Sort by extracted serial number
            partition_records.sort(key=lambda r: (
                self.extract_serial_number(r.get('number')) or 
                self.extract_serial_number(r.get('awaiting_fileno')) or
                self.extract_serial_number(r.get('mls_fileno')) or
                r['id']
            ))
            
            # Assign group numbers
            for i, record in enumerate(partition_records, 1):
                group_no = math.ceil(i / self.GROUP_SIZE)
                record['new_group'] = str(group_no)
                record['new_sys_batch'] = str(group_no)
                record['partition_position'] = i
                
            partition_groups = math.ceil(len(partition_records) / self.GROUP_SIZE)
            total_groups += partition_groups
            
            print(f"  {landuse} {year}: {len(partition_records):,} records → {partition_groups} groups")
            
        print(f"✓ Total groups required: {total_groups:,}")
        return dict(partitions), total_groups
        
    def get_available_shelf_labels(self) -> List[Dict]:
        """Get available shelf labels"""
        print("Checking available shelf labels...")
        
        cursor = self.conn.cursor()
        cursor.execute("""
        SELECT id, full_label, is_used
        FROM Rack_Shelf_Labels 
        WHERE ISNULL(is_used, 0) = 0
        ORDER BY id
        """)
        
        labels = []
        for row in cursor.fetchall():
            labels.append({
                'id': row[0],
                'full_label': row[1],
                'is_used': row[2] or 0
            })
            
        cursor.close()
        print(f"✓ Available shelf labels: {len(labels):,}")
        return labels
        
    def generate_shelf_labels(self, needed: int) -> List[Dict]:
        """Generate additional shelf labels if needed"""
        print(f"Generating {needed:,} additional shelf labels...")
        
        # Get existing labels to avoid duplicates
        cursor = self.conn.cursor()
        cursor.execute("SELECT full_label FROM Rack_Shelf_Labels")
        existing = {row[0].upper() for row in cursor.fetchall()}
        cursor.close()
        
        new_labels = []
        
        # Generate A1, A2, ..., Z999, AA1, AA2, etc.
        letters = list(string.ascii_uppercase)
        
        # Single letters first
        for letter in letters:
            for num in range(1, 1000):
                label = f"{letter}{num}"
                if label not in existing:
                    new_labels.append({
                        'rack': letter,
                        'shelf': str(num),
                        'full_label': label,
                        'is_used': 0
                    })
                    existing.add(label)
                    
                    if len(new_labels) >= needed:
                        break
            if len(new_labels) >= needed:
                break
                
        # Double letters if needed
        if len(new_labels) < needed:
            for first in letters:
                for second in letters:
                    for num in range(1, 1000):
                        label = f"{first}{second}{num}"
                        if label not in existing:
                            new_labels.append({
                                'rack': f"{first}{second}",
                                'shelf': str(num),
                                'full_label': label,
                                'is_used': 0
                            })
                            existing.add(label)
                            
                            if len(new_labels) >= needed:
                                break
                    if len(new_labels) >= needed:
                        break
                if len(new_labels) >= needed:
                    break
                    
        if len(new_labels) < needed:
            raise Exception(f"Could not generate enough unique shelf labels. Generated {len(new_labels)}, needed {needed}")
            
        # Insert new labels in batches (exclude id column - it's auto-increment)
        cursor = self.conn.cursor()
        for i in range(0, len(new_labels), 1000):
            batch = new_labels[i:i+1000]
            values = [(l['rack'], l['shelf'], l['full_label'], l['is_used']) for l in batch]
            
            # Don't specify id column - let it auto-increment
            cursor.executemany("""
            INSERT INTO Rack_Shelf_Labels (rack, shelf, full_label, is_used)
            VALUES (?, ?, ?, ?)
            """, values)
            
            print(f"  Inserted shelf labels batch {i//1000 + 1}/{math.ceil(len(new_labels)/1000)}")
            
        cursor.close()
        self.conn.commit()
        
        print(f"✓ Generated and inserted {len(new_labels):,} new shelf labels")
        return new_labels
        
    def assign_shelf_labels(self, partitions: Dict, total_groups: int) -> Dict[int, str]:
        """Assign shelf labels to groups"""
        print("Assigning shelf labels to groups...")
        
        # Get available labels
        available_labels = self.get_available_shelf_labels()
        
        # Generate more if needed
        if len(available_labels) < total_groups:
            needed = total_groups - len(available_labels)
            new_labels = self.generate_shelf_labels(needed)
            # Refresh available labels
            available_labels = self.get_available_shelf_labels()
            
        # Create group to label mapping
        group_to_label = {}
        label_to_id = {}
        
        label_index = 0
        
        for partition_key, partition_records in partitions.items():
            landuse, year = partition_key
            
            # Get unique group numbers in this partition
            groups_in_partition = sorted(set(r['new_group'] for r in partition_records))
            
            for group in groups_in_partition:
                if label_index >= len(available_labels):
                    raise Exception("Ran out of available shelf labels")
                    
                label = available_labels[label_index]
                group_key = f"{landuse}_{year}_{group}"
                group_to_label[group_key] = label['full_label']
                label_to_id[label['full_label']] = label['id']
                label_index += 1
                
        print(f"✓ Assigned {len(group_to_label)} group-to-label mappings")
        
        # Assign shelf_rack to each record
        for partition_records in partitions.values():
            for record in partition_records:
                group_key = f"{record['landuse']}_{record['year']}_{record['new_group']}"
                record['new_shelf_rack'] = group_to_label.get(group_key)
                
        return label_to_id
        
    def update_grouping_records(self, partitions: Dict):
        """Update grouping table with new metadata"""
        print("Updating grouping table...")
        
        # Flatten all records
        all_records = []
        for partition_records in partitions.values():
            all_records.extend(partition_records)
            
        # Filter records that need updates
        updates_needed = []
        for record in all_records:
            needs_update = (
                record.get('current_group') != record.get('new_group') or
                record.get('current_sys_batch') != record.get('new_sys_batch') or
                record.get('current_shelf_rack') != record.get('new_shelf_rack')
            )
            
            if needs_update:
                updates_needed.append(record)
                
        print(f"Records needing updates: {len(updates_needed):,}")
        
        if not updates_needed:
            print("✓ No updates needed")
            return 0
            
        # Update in batches
        cursor = self.conn.cursor()
        updated_count = 0
        
        for i in range(0, len(updates_needed), self.batch_size):
            batch = updates_needed[i:i + self.batch_size]
            
            # Prepare batch update
            update_data = []
            for record in batch:
                update_data.append((
                    record['new_group'],
                    record['new_sys_batch'],
                    record['new_shelf_rack'],
                    record['id']
                ))
                
            cursor.executemany("""
            UPDATE grouping 
            SET [group] = ?, sys_batch_no = ?, shelf_rack = ?
            WHERE id = ?
            """, update_data)
            
            updated_count += len(batch)
            progress = (i + len(batch)) / len(updates_needed) * 100
            
            print(f"  Updated batch {i//self.batch_size + 1}/{math.ceil(len(updates_needed)/self.batch_size)} "
                  f"({updated_count:,}/{len(updates_needed):,} records, {progress:.1f}%)")
                  
        cursor.close()
        self.conn.commit()
        
        print(f"✓ Updated {updated_count:,} grouping records")
        return updated_count
        
    def mark_labels_used(self, label_to_id: Dict[str, int]):
        """Mark assigned shelf labels as used"""
        print("Marking shelf labels as used...")
        
        if not label_to_id:
            print("✓ No labels to mark as used")
            return
            
        cursor = self.conn.cursor()
        label_ids = list(label_to_id.values())
        
        # Update in batches
        for i in range(0, len(label_ids), 1000):
            batch = label_ids[i:i + 1000]
            placeholders = ','.join('?' * len(batch))
            
            cursor.execute(f"""
            UPDATE Rack_Shelf_Labels 
            SET is_used = 1 
            WHERE id IN ({placeholders})
            """, batch)
            
            print(f"  Marked batch {i//1000 + 1}/{math.ceil(len(label_ids)/1000)} as used")
            
        cursor.close()
        self.conn.commit()
        
        print(f"✓ Marked {len(label_ids):,} shelf labels as used")
        
    def verify_assignments(self, partitions: Dict):
        """Verify the assignments are correct"""
        print("Verifying assignments...")
        
        cursor = self.conn.cursor()
        
        # Check group sizes
        cursor.execute("""
        SELECT landuse, year, [group], COUNT(*) as group_size
        FROM grouping 
        WHERE [group] IS NOT NULL AND deleted_at IS NULL
        GROUP BY landuse, year, [group]
        HAVING COUNT(*) > 100
        """)
        
        oversized_groups = cursor.fetchall()
        if oversized_groups:
            print(f"⚠️  Found {len(oversized_groups)} groups with >100 files:")
            for row in oversized_groups:
                print(f"    {row[0]} {row[1]} Group {row[2]}: {row[3]} files")
        else:
            print("✓ All groups have ≤100 files")
            
        # Check total assignments
        cursor.execute("""
        SELECT 
            COUNT(*) as total_records,
            COUNT([group]) as assigned_groups,
            COUNT(sys_batch_no) as assigned_sys_batch,
            COUNT(shelf_rack) as assigned_shelf_rack
        FROM grouping 
        WHERE deleted_at IS NULL
        """)
        
        row = cursor.fetchone()
        print(f"✓ Assignment summary:")
        print(f"    Total records: {row[0]:,}")
        print(f"    Assigned groups: {row[1]:,}")
        print(f"    Assigned sys batch: {row[2]:,}")
        print(f"    Assigned shelf rack: {row[3]:,}")
        
        cursor.close()
        
    def run(self, dry_run: bool = False):
        """Run the complete assignment process"""
        start_time = time.time()
        
        try:
            self.connect()
            
            # Step 1: Load data
            records = self.get_grouping_data()
            
            if not records:
                print("No records found to process")
                return
                
            # Step 2: Partition and assign groups
            partitions, total_groups = self.partition_and_assign_groups(records)
            
            # Step 3: Assign shelf labels
            label_to_id = self.assign_shelf_labels(partitions, total_groups)
            
            if dry_run:
                print("\n🔍 DRY RUN COMPLETE - No changes made")
                print(f"Would process {len(records):,} records across {total_groups:,} groups")
                return
                
            # Step 4: Update database
            updated_count = self.update_grouping_records(partitions)
            
            # Step 5: Mark labels as used
            self.mark_labels_used(label_to_id)
            
            # Step 6: Verify
            self.verify_assignments(partitions)
            
            elapsed = time.time() - start_time
            print(f"\n✅ ASSIGNMENT COMPLETE")
            print(f"   Processed: {len(records):,} records")
            print(f"   Updated: {updated_count:,} records")
            print(f"   Groups created: {total_groups:,}")
            print(f"   Time elapsed: {elapsed:.1f} seconds")
            
        except Exception as e:
            print(f"\n❌ ERROR: {e}")
            if self.conn:
                self.conn.rollback()
            raise
        finally:
            self.disconnect()

def main():
    # Connection string - adjust as needed
    CONNECTION_STRING = (
        "DRIVER={ODBC Driver 17 for SQL Server};"
        "SERVER=localhost;"
        "DATABASE=klas;"
        "Trusted_Connection=yes;"
    )
    
    import sys
    dry_run = "--dry-run" in sys.argv
    
    assigner = GroupingMetadataAssigner(CONNECTION_STRING, batch_size=10000)
    assigner.run(dry_run=dry_run)

if __name__ == "__main__":
    main()