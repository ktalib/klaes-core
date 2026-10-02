#!/usr/bin/env python3
"""
Fix Rack_Shelf_Labels table structure and run grouping metadata assignment
"""

import pyodbc
import sys

def fix_rack_shelf_labels_table():
    """Drop and recreate the Rack_Shelf_Labels table with proper structure"""
    
    CONNECTION_STRING = (
        "DRIVER={ODBC Driver 17 for SQL Server};"
        "SERVER=localhost;"
        "DATABASE=klas;"
        "Trusted_Connection=yes;"
    )
    
    print("Connecting to SQL Server...")
    conn = pyodbc.connect(CONNECTION_STRING)
    conn.autocommit = True
    cursor = conn.cursor()
    
    try:
        # Check if table exists and get current data count
        cursor.execute("""
        SELECT COUNT(*) FROM Rack_Shelf_Labels WHERE ISNULL(is_used, 0) = 0
        """)
        available_count = cursor.fetchone()[0]
        print(f"Current available labels: {available_count}")
        
        # Backup existing data
        print("Backing up existing rack/shelf labels...")
        cursor.execute("""
        SELECT rack, shelf, full_label, ISNULL(is_used, 0) as is_used,
               reserved_by, reserved_at
        FROM Rack_Shelf_Labels
        ORDER BY id
        """)
        
        existing_labels = cursor.fetchall()
        print(f"Backed up {len(existing_labels)} existing labels")
        
        # Drop the table
        print("Dropping existing Rack_Shelf_Labels table...")
        cursor.execute("DROP TABLE IF EXISTS Rack_Shelf_Labels")
        
        # Create new table with proper auto-increment
        print("Creating new Rack_Shelf_Labels table...")
        cursor.execute("""
        CREATE TABLE Rack_Shelf_Labels (
            id INT IDENTITY(1,1) PRIMARY KEY,
            rack NVARCHAR(10) NOT NULL,
            shelf NVARCHAR(10) NOT NULL,
            full_label NVARCHAR(20) NOT NULL UNIQUE,
            is_used BIT NOT NULL DEFAULT 0,
            reserved_by NVARCHAR(255) NULL,
            reserved_at DATETIME2 NULL,
            created_at DATETIME2 DEFAULT GETUTCDATE(),
            updated_at DATETIME2 DEFAULT GETUTCDATE()
        )
        """)
        
        # Restore existing data
        if existing_labels:
            print(f"Restoring {len(existing_labels)} labels...")
            
            for i in range(0, len(existing_labels), 1000):
                batch = existing_labels[i:i+1000]
                
                cursor.executemany("""
                INSERT INTO Rack_Shelf_Labels (rack, shelf, full_label, is_used, reserved_by, reserved_at)
                VALUES (?, ?, ?, ?, ?, ?)
                """, batch)
                
                print(f"  Restored batch {i//1000 + 1}")
        
        # Verify restoration
        cursor.execute("SELECT COUNT(*) FROM Rack_Shelf_Labels")
        total_count = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) FROM Rack_Shelf_Labels WHERE is_used = 0")
        available_after = cursor.fetchone()[0]
        
        print(f"✓ Table recreated successfully")
        print(f"  Total labels: {total_count}")
        print(f"  Available labels: {available_after}")
        
        # Generate some initial labels if we have very few
        if available_after < 100:
            print("Generating initial set of labels...")
            
            import string
            initial_labels = []
            
            for letter in string.ascii_uppercase[:10]:  # A-J
                for num in range(1, 101):  # 1-100
                    label = f"{letter}{num}"
                    initial_labels.append((letter, str(num), label, 0, None, None))
                    
            cursor.executemany("""
            INSERT INTO Rack_Shelf_Labels (rack, shelf, full_label, is_used, reserved_by, reserved_at)
            VALUES (?, ?, ?, ?, ?, ?)
            """, initial_labels)
            
            print(f"✓ Generated {len(initial_labels)} initial labels")
        
    except Exception as e:
        print(f"❌ Error fixing table: {e}")
        raise
    finally:
        cursor.close()
        conn.close()
        print("✓ Database connection closed")

if __name__ == "__main__":
    fix_rack_shelf_labels_table()
    
    # Now run the grouping assignment
    print("\n" + "="*50)
    print("Starting grouping metadata assignment...")
    print("="*50)
    
    import subprocess
    
    # Determine if this is a dry run
    dry_run = "--dry-run" in sys.argv
    
    # Run the grouping assignment script
    cmd = [
        "C:/Users/Administrator/Documents/app/.venv/Scripts/python.exe",
        "assign_grouping_metadata.py"
    ]
    
    if dry_run:
        cmd.append("--dry-run")
        
    result = subprocess.run(cmd, cwd="C:/Users/Administrator/Documents/app")
    
    if result.returncode == 0:
        print("\n✅ All operations completed successfully!")
    else:
        print(f"\n❌ Grouping assignment failed with exit code: {result.returncode}")