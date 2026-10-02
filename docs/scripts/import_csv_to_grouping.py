import pandas as pd
import pymssql
import os
from dotenv import load_dotenv
from datetime import datetime
import glob

# Load environment variables from Laravel .env file
load_dotenv()

# Database configuration from Laravel .env
DB_HOST = os.getenv('DB_HOST', 'localhost')
DB_PORT = int(os.getenv('DB_PORT', 1433))
DB_DATABASE = os.getenv('DB_DATABASE')
DB_USERNAME = os.getenv('DB_USERNAME')
DB_PASSWORD = os.getenv('DB_PASSWORD')

# Land use mapping based on file prefix
LANDUSE_MAPPING = {
    'AG': 'AGRICULTURE',
    'COM': 'COMMERCIAL', 
    'CON': 'CONSTRUCTION',
    'RES': 'RESIDENTIAL',
    'IND': 'INDUSTRIAL',
    'MIX': 'MIXED'
}

def get_landuse_from_fileno(fileno):
    """Extract land use from file number prefix"""
    if not fileno:
        return None
    
    # Extract prefix before first hyphen
    prefix = fileno.split('-')[0].upper()
    return LANDUSE_MAPPING.get(prefix, 'OTHER')

def connect_to_database():
    """Create database connection"""
    try:
        conn = pymssql.connect(
            server=DB_HOST,
            port=DB_PORT,
            user=DB_USERNAME,
            password=DB_PASSWORD,
            database=DB_DATABASE,
            as_dict=True
        )
        print(f"✓ Connected to database: {DB_DATABASE}")
        return conn
    except Exception as e:
        print(f"✗ Database connection failed: {e}")
        return None

def process_csv_file(filepath, conn):
    """Process a single CSV file and insert data"""
    filename = os.path.basename(filepath)
    print(f"\n📁 Processing: {filename}")
    
    try:
        # Read CSV file
        df = pd.read_csv(filepath)
        print(f"   📊 Loaded {len(df)} records")
        
        # Process each row
        cursor = conn.cursor()
        batch_size = 1000
        inserted_count = 0
        skipped_count = 0
        
        for i in range(0, len(df), batch_size):
            batch = df.iloc[i:i+batch_size]
            
            # Prepare batch insert
            values = []
            for _, row in batch.iterrows():
                fileno = str(row['FileNo']).strip('"')
                year = int(row['Year'])
                landuse = get_landuse_from_fileno(fileno)
                
                # Check if record already exists
                cursor.execute(
                    "SELECT id FROM grouping WHERE pseudo_fileno = %s",
                    (fileno,)
                )
                if cursor.fetchone():
                    skipped_count += 1
                    continue
                
                values.append((
                    fileno,          # pseudo_fileno
                    None,            # mls_fileno
                    0,               # mapping (default 0)
                    None,            # group
                    None,            # batch_no
                    None,            # mdc_batch_no
                    None,            # sys_batch_no
                    None,            # shelf_rack
                    None,            # date
                    'CSV Import',    # created_by
                    None,            # indexed_by
                    None,            # date_index
                    year,            # year
                    landuse,         # landuse
                    datetime.now(),  # created_at
                    datetime.now(),  # updated_at
                    None,            # updated_by
                    None             # deleted_at
                ))
            
            # Batch insert
            if values:
                cursor.executemany("""
                    INSERT INTO grouping (
                        pseudo_fileno, mls_fileno, mapping, [group], batch_no,
                        mdc_batch_no, sys_batch_no, shelf_rack, date, created_by,
                        indexed_by, date_index, year, landuse, created_at,
                        updated_at, updated_by, deleted_at
                    ) VALUES (
                        %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,
                        %s, %s, %s, %s, %s, %s, %s, %s
                    )
                """, values)
                
                inserted_count += len(values)
                conn.commit()
                
                print(f"   ✓ Inserted batch {i//batch_size + 1}: {len(values)} records")
        
        cursor.close()
        print(f"   📈 Summary - Inserted: {inserted_count}, Skipped: {skipped_count}")
        
        return inserted_count, skipped_count
        
    except Exception as e:
        print(f"   ✗ Error processing {filename}: {e}")
        return 0, 0

def main():
    """Main import function"""
    print("🚀 Starting CSV Import to Grouping Table")
    print("=" * 50)
    
    # Database connection
    conn = connect_to_database()
    if not conn:
        return
    
    # Find all CSV files in public/import directory
    import_dir = "public/import"
    csv_pattern = os.path.join(import_dir, "*.csv")
    csv_files = glob.glob(csv_pattern)
    
    if not csv_files:
        print(f"✗ No CSV files found in {import_dir}")
        return
    
    print(f"📂 Found {len(csv_files)} CSV files:")
    for file in csv_files:
        print(f"   - {os.path.basename(file)}")
    
    # Process each file
    total_inserted = 0
    total_skipped = 0
    
    for csv_file in csv_files:
        inserted, skipped = process_csv_file(csv_file, conn)
        total_inserted += inserted
        total_skipped += skipped
    
    # Final summary
    print("\n" + "=" * 50)
    print("📊 FINAL SUMMARY")
    print("=" * 50)
    print(f"✅ Total Records Inserted: {total_inserted:,}")
    print(f"⏭️  Total Records Skipped: {total_skipped:,}")
    print(f"📁 Files Processed: {len(csv_files)}")
    
    # Show count by land use
    cursor = conn.cursor()
    cursor.execute("""
        SELECT landuse, COUNT(*) as count 
        FROM grouping 
        WHERE created_by = 'CSV Import'
        GROUP BY landuse
        ORDER BY count DESC
    """)
    
    print(f"\n📈 Records by Land Use:")
    for row in cursor.fetchall():
        print(f"   {row['landuse']}: {row['count']:,}")
    
    cursor.close()
    conn.close()
    print(f"\n🎉 Import completed successfully!")

if __name__ == "__main__":
    main()