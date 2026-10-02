#!/usr/bin/env python3
"""
Optimized Grouping Metadata Assignment Script

Assigns Group No, Sys Batch No, and Shelf/Rack values to grouping records
in chunks to handle large datasets efficiently.
"""

import pyodbc
import sys
import math
import re
from datetime import datetime, timezone

class OptimizedGroupingAssigner:
    SHELVES_PER_RACK = 48
    LAND_USE_PRIORITY = {
        "RESIDENTIAL": 0,
        "COMMERCIAL": 1,
        "AGRICULTURE": 2,
        "INDUSTRIAL": 3,
        "MIXED": 4,
    }

    def __init__(self, server="vmi2583396", database="klas"):
        self.server = server
        self.database = database
        self.connection = None
        self.label_queue = []
        self.label_index = 0
        self.reservation_tag = "grouping-assignment"
        self.dry_run_mode = False
        
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
        """Extract the trailing serial number component for sorting"""
        if not file_number:
            return 0

        match = re.search(r"(\d+)(?!.*\d)", file_number)
        if match:
            try:
                return int(match.group(1))
            except ValueError:
                return 0
        return 0

    def build_sort_key(self, file_number):
        """Create a tuple that enforces desired ordering for grouping"""
        is_converted = 1 if file_number and file_number.upper().startswith('CON-') else 0
        sanitized = file_number[4:] if is_converted else file_number
        serial = self.extract_serial_number(file_number)
        return (is_converted, serial, sanitized or "", file_number or "")
    
    def get_partition_counts(self):
        """Get count of records per partition for planning"""
        print("Analyzing partitions...")

        query = """
        SELECT
            landuse,
            year,
            COUNT(*) AS record_count
        FROM grouping WITH (NOLOCK)
        WHERE awaiting_fileno IS NOT NULL
            AND awaiting_fileno != ''
        GROUP BY landuse, year
        """

        cursor = self.connection.cursor()
        cursor.execute(query)
        partition_rows = cursor.fetchall()
        cursor.close()

        partitions = []
        total_records = 0
        total_groups = 0

        # Normalize ordering (year ascending, land use ascending for stability)
        partition_rows = sorted(
            partition_rows,
            key=lambda row: (
                row.year,
                self.LAND_USE_PRIORITY.get((row.landuse or "").upper(), 99),
                row.landuse,
            )
        )

        print("\nPartition Analysis:")
        print("Land Use | Year | Records | Groups")
        print("-" * 40)

        for row in partition_rows:
            land_use = row.landuse
            year = row.year
            count = row.record_count
            groups = math.ceil(count / 100)
            total_records += count
            total_groups += groups
            partitions.append((land_use, year, count))
            print(f"{land_use:8} | {year} | {count:7,} | {groups:6}")

        print("-" * 40)
        print(f"Total: {total_records:,} records, {total_groups:,} groups")

        return partitions, total_groups
    
    def process_partition(self, land_use, year, dry_run=False):
        """Process a single partition efficiently"""
        print(f"\nProcessing {land_use} {year}...")
        
        # Get records for this partition ordered by serial number
        query = """
        SELECT id, awaiting_fileno
        FROM grouping 
        WHERE landuse = ? 
        AND year = ?
        AND awaiting_fileno IS NOT NULL 
        AND awaiting_fileno != ''
        ORDER BY awaiting_fileno
        """
        
        cursor = self.connection.cursor()
        cursor.execute(query, land_use, year)
        records = cursor.fetchall()
        cursor.close()
        
        if not records:
            print(f"  No records found for {land_use} {year}")
            return 0
        
        print(f"  Found {len(records):,} records")
        
        # Sort by extracted serial number for proper grouping
        records_with_serial = [
            (r.id, r.awaiting_fileno, self.build_sort_key(r.awaiting_fileno))
            for r in records
        ]
        records_with_serial.sort(key=lambda x: x[2])
        
        # Determine number of groups and allocate shelf labels
        group_count = math.ceil(len(records_with_serial) / 100)
        labels = self.dequeue_labels(group_count)
        label_ids = [label["id"] for label in labels]
        label_strings = [label["label"] for label in labels]

        updates = []

        for i, (record_id, file_number, _) in enumerate(records_with_serial):
            group_num = (i // 100) + 1
            label_value = label_strings[group_num - 1]
            updates.append((record_id, group_num, group_num, label_value))

        print(f"  Assigned {len(updates):,} records to {group_count} groups")
        
        if not dry_run:
            cursor = self.connection.cursor()
            temp_table_created = False
            try:
                cursor.execute("""
                    CREATE TABLE #UpdateAssignments (
                        id INT PRIMARY KEY,
                        group_no INT NOT NULL,
                        sys_batch_no INT NOT NULL,
                        shelf NVARCHAR(16) NOT NULL
                    )
                """)
                temp_table_created = True

                cursor.fast_executemany = True
                cursor.executemany(
                    "INSERT INTO #UpdateAssignments (id, group_no, sys_batch_no, shelf) VALUES (?, ?, ?, ?)",
                    updates
                )

                cursor.execute("""
                    UPDATE g
                    SET g.[group] = ua.group_no,
                        g.sys_batch_no = ua.sys_batch_no,
                        g.shelf_rack = ua.shelf
                    FROM grouping g
                    JOIN #UpdateAssignments ua ON g.id = ua.id
                """)

                self.connection.commit()
            finally:
                if temp_table_created:
                    cursor.execute("DROP TABLE #UpdateAssignments")
                cursor.close()

            self.mark_labels_used(label_ids)
            print(f"  ✓ Completed {land_use} {year}")
        else:
            print(f"  [DRY RUN] Would update {len(updates):,} records")
        
        return group_count
    
    def ensure_sufficient_labels(self, total_groups_needed, dry_run=False):
        """Ensure we have enough shelf labels"""
        print(f"\nChecking shelf labels (need {total_groups_needed:,})...")
        
        cursor = self.connection.cursor()
        cursor.execute("SELECT COUNT(*) FROM Rack_Shelf_Labels")
        total_labels = cursor.fetchone()[0]
        cursor.close()
        
        print(f"Available labels: {total_labels:,}")
        
        if total_labels >= total_groups_needed:
            print("✓ Sufficient labels available")
            return True
        
        needed = total_groups_needed - total_labels
        print(f"Need to generate {needed:,} additional labels")
        
        if dry_run:
            print("[DRY RUN] Would generate additional labels")
            return True
        
        # Generate additional labels
        cursor = self.connection.cursor()
        
        # Get the last shelf/rack values
        cursor.execute("SELECT TOP 1 rack, shelf FROM Rack_Shelf_Labels ORDER BY id DESC")
        result = cursor.fetchone()
        last_rack = result.rack if result and result.rack else ""
        last_shelf = int(result.shelf) if result and result.shelf else 0
        
        labels_to_insert = []
        shelf_no = last_shelf
        rack_code = last_rack
        
        for _ in range(needed):
            shelf_no += 1
            if shelf_no > self.SHELVES_PER_RACK:
                shelf_no = 1
                rack_code = self.next_rack_code(rack_code)
            elif rack_code == "":
                rack_code = "A"
            
            full_label = f"{rack_code}{shelf_no}"
            labels_to_insert.append((rack_code, shelf_no, full_label, 0))
        
        # Insert in batches
        insert_query = "INSERT INTO Rack_Shelf_Labels (rack, shelf, full_label, is_used) VALUES (?, ?, ?, ?)"
        
        batch_size = 1000
        for i in range(0, len(labels_to_insert), batch_size):
            batch = labels_to_insert[i:i + batch_size]
            cursor.executemany(insert_query, batch)
            self.connection.commit()
            print(f"  Generated batch {i//batch_size + 1}/{math.ceil(len(labels_to_insert)/batch_size)}")
        
        cursor.close()
        print(f"✓ Generated {needed:,} additional labels")
        return True

    def reset_label_usage(self):
        """Reset all shelf label usage before reassignment"""
        cursor = self.connection.cursor()
        cursor.execute("UPDATE Rack_Shelf_Labels SET is_used = 0, reserved_by = NULL, reserved_at = NULL")
        self.connection.commit()
        cursor.close()

    def load_label_queue(self):
        """Load shelf labels into memory for sequential assignment"""
        cursor = self.connection.cursor()
        cursor.execute("SELECT id, full_label FROM Rack_Shelf_Labels ORDER BY id")
        rows = cursor.fetchall()
        cursor.close()
        self.label_queue = [{"id": row.id, "label": row.full_label} for row in rows]
        self.label_index = 0

    def dequeue_labels(self, count):
        """Retrieve the next set of shelf labels"""
        remaining = len(self.label_queue) - self.label_index
        if remaining < count:
            if self.dry_run_mode:
                self.extend_label_queue(count - remaining)
            else:
                raise ValueError("Not enough shelf labels available for assignment")
        labels = self.label_queue[self.label_index:self.label_index + count]
        self.label_index += count
        return labels

    def mark_labels_used(self, label_ids):
        """Mark supplied shelf labels as used"""
        if not label_ids:
            return
        now = datetime.now(timezone.utc)
        cursor = self.connection.cursor()
        params = [(1, self.reservation_tag, now, now, label_id) for label_id in label_ids]
        cursor.executemany(
            "UPDATE Rack_Shelf_Labels SET is_used = ?, reserved_by = ?, reserved_at = ?, updated_at = ? WHERE id = ?",
            params
        )
        self.connection.commit()
        cursor.close()

    def next_rack_code(self, current):
        """Compute the next rack code (A..Z, AA, AB, ...)."""
        if not current:
            return "A"

        # Convert to number (Excel-style)
        value = 0
        for char in current:
            value = value * 26 + (ord(char.upper()) - ord('A') + 1)
        value += 1

        # Convert back to letters
        result = ""
        while value > 0:
            value, remainder = divmod(value - 1, 26)
            result = chr(remainder + ord('A')) + result
        return result

    def parse_label(self, label):
        """Split a label like 'AA12' into (rack, shelf)."""
        rack_part = ""
        shelf_part = ""
        for char in label:
            if char.isalpha():
                rack_part += char
            else:
                shelf_part += char
        return rack_part, int(shelf_part) if shelf_part else 0

    def extend_label_queue(self, additional):
        """Extend label queue in-memory (dry-run only)."""
        if additional <= 0:
            return

        if self.label_queue:
            last_label = self.label_queue[-1]["label"]
            rack_code, shelf_no = self.parse_label(last_label)
        else:
            rack_code = ""
            shelf_no = 0

        new_entries = []
        for _ in range(additional):
            shelf_no += 1
            if shelf_no > self.SHELVES_PER_RACK:
                shelf_no = 1
                rack_code = self.next_rack_code(rack_code)
            elif rack_code == "":
                rack_code = "A"

            new_entries.append({"id": None, "label": f"{rack_code}{shelf_no}"})

        self.label_queue.extend(new_entries)
    
    def run(self, dry_run=False):
        """Run the complete assignment process"""
        if not self.connect():
            return False
        
        try:
            self.dry_run_mode = dry_run
            # Analyze partitions
            partitions, total_groups = self.get_partition_counts()
            
            # Ensure sufficient shelf labels
            if not self.ensure_sufficient_labels(total_groups, dry_run):
                return False

            if not dry_run:
                self.reset_label_usage()
            self.load_label_queue()
            
            print(f"\n{'='*50}")
            print(f"Starting partition processing...")
            print(f"{'='*50}")
            
            total_processed_groups = 0
            
            # Process each partition
            for land_use, year, count in partitions:
                groups_in_partition = self.process_partition(land_use, year, dry_run)
                total_processed_groups += groups_in_partition
                
                print(f"Progress: {total_processed_groups:,}/{total_groups:,} groups processed")
            
            print(f"\n{'='*50}")
            if dry_run:
                print(f"DRY RUN COMPLETE")
                print(f"Would process {total_processed_groups:,} groups across {len(partitions)} partitions")
            else:
                print(f"ASSIGNMENT COMPLETE!")
                print(f"Processed {total_processed_groups:,} groups across {len(partitions)} partitions")
            print(f"{'='*50}")
            
            return True
            
        except Exception as e:
            print(f"✗ Error during processing: {e}")
            return False
        finally:
            self.dry_run_mode = False
            self.disconnect()

def main():
    dry_run = '--dry-run' in sys.argv
    
    if dry_run:
        print("Running in DRY RUN mode - no database changes will be made")
    else:
        print("Running in LIVE mode - database will be updated")
    
    assigner = OptimizedGroupingAssigner()
    success = assigner.run(dry_run=dry_run)
    
    if success:
        print("\n✓ Process completed successfully")
        sys.exit(0)
    else:
        print("\n✗ Process failed")
        sys.exit(1)

if __name__ == "__main__":
    main()