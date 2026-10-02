"""
CSV Import Script for Grouping Table
====================================

This script imports CSV data from public/import directory into the grouping table.
It maps file number prefixes to land use types and handles duplicate checking.

Usage: python import_csv_to_grouping_simple.py
"""

import pandas as pd
import os
import glob
from datetime import datetime
import logging

# Set up logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('csv_import.log'),
        logging.StreamHandler()
    ]
)

# Land use mapping based on file prefix
LANDUSE_MAPPING = {
    'AG': 'AGRICULTURE',
    'COM': 'COMMERCIAL', 
    'CON': 'CONSTRUCTION',
    'RES': 'RESIDENTIAL',
    'IND': 'INDUSTRIAL',
    'MIX': 'MIXED',
    'AG-RC': 'AGRICULTURE',  # Handle compound prefixes
    'COM-RC': 'COMMERCIAL',
    'CON-COM': 'CONSTRUCTION_COMMERCIAL',
    'CON-RES': 'CONSTRUCTION_RESIDENTIAL',
    'RES-RC': 'RESIDENTIAL'
}

def get_landuse_from_fileno(fileno):
    """Extract land use from file number prefix"""
    if not fileno:
        return 'OTHER'
    
    # Remove quotes and clean
    fileno = str(fileno).strip('"').strip()
    
    # Try compound prefixes first (e.g., AG-RC, COM-RC)
    for prefix in sorted(LANDUSE_MAPPING.keys(), key=len, reverse=True):
        if fileno.upper().startswith(prefix + '-'):
            return LANDUSE_MAPPING[prefix]
    
    # Fallback to simple prefix
    parts = fileno.split('-')
    if parts:
        prefix = parts[0].upper()
        return LANDUSE_MAPPING.get(prefix, 'OTHER')
    
    return 'OTHER'

def process_csv_files():
    """Process all CSV files and generate SQL insert statements"""
    
    # Find all CSV files
    import_dir = "public/import"
    csv_pattern = os.path.join(import_dir, "*.csv")
    csv_files = glob.glob(csv_pattern)
    
    if not csv_files:
        logging.error(f"No CSV files found in {import_dir}")
        return
    
    logging.info(f"Found {len(csv_files)} CSV files")
    
    all_data = []
    total_records = 0
    
    # Process each file
    for csv_file in csv_files:
        filename = os.path.basename(csv_file)
        logging.info(f"Processing: {filename}")
        
        try:
            # Read CSV
            df = pd.read_csv(csv_file)
            logging.info(f"  Loaded {len(df)} records from {filename}")
            
            # Process records
            for _, row in df.iterrows():
                fileno = str(row['FileNo']).strip('"').strip()
                year = int(row['Year'])
                landuse = get_landuse_from_fileno(fileno)
                
                record = {
                    'pseudo_fileno': fileno,
                    'year': year,
                    'landuse': landuse,
                    'source_file': filename
                }
                
                all_data.append(record)
                total_records += 1
            
        except Exception as e:
            logging.error(f"Error processing {filename}: {e}")
    
    # Create summary DataFrame
    summary_df = pd.DataFrame(all_data)
    
    # Remove duplicates based on pseudo_fileno
    initial_count = len(summary_df)
    summary_df = summary_df.drop_duplicates(subset=['pseudo_fileno'], keep='first')
    final_count = len(summary_df)
    duplicates_removed = initial_count - final_count
    
    logging.info(f"Total records processed: {initial_count:,}")
    logging.info(f"Duplicates removed: {duplicates_removed:,}")
    logging.info(f"Final unique records: {final_count:,}")
    
    # Show statistics by land use
    landuse_stats = summary_df['landuse'].value_counts()
    logging.info("Records by Land Use:")
    for landuse, count in landuse_stats.items():
        logging.info(f"  {landuse}: {count:,}")
    
    # Generate SQL insert file
    generate_sql_inserts(summary_df)
    
    # Save processed data to CSV for review
    output_file = 'processed_grouping_data.csv'
    summary_df.to_csv(output_file, index=False)
    logging.info(f"Processed data saved to: {output_file}")
    
    return summary_df

def generate_sql_inserts(df):
    """Generate SQL INSERT statements"""
    
    sql_file = 'grouping_inserts.sql'
    
    with open(sql_file, 'w') as f:
        f.write("-- SQL INSERT statements for grouping table\n")
        f.write(f"-- Generated on: {datetime.now()}\n")
        f.write(f"-- Total records: {len(df):,}\n\n")
        
        f.write("-- Check if data already exists\n")
        f.write("SELECT COUNT(*) as existing_count FROM grouping WHERE created_by = 'CSV Import';\n\n")
        
        f.write("-- Insert data in batches\n")
        f.write("BEGIN TRANSACTION;\n\n")
        
        batch_size = 1000
        for i in range(0, len(df), batch_size):
            batch = df.iloc[i:i+batch_size]
            
            f.write(f"-- Batch {i//batch_size + 1}\n")
            f.write("INSERT INTO grouping (\n")
            f.write("    pseudo_fileno, mls_fileno, mapping, [group], batch_no,\n")
            f.write("    mdc_batch_no, sys_batch_no, shelf_rack, date, created_by,\n")
            f.write("    indexed_by, date_index, year, landuse, created_at, updated_at\n")
            f.write(") VALUES\n")
            
            values = []
            for _, row in batch.iterrows():
                fileno = row['pseudo_fileno'].replace("'", "''")  # Escape quotes
                landuse = row['landuse'].replace("'", "''")
                year = row['year']
                
                value = f"('{fileno}', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'CSV Import', NULL, NULL, {year}, '{landuse}', GETDATE(), GETDATE())"
                values.append(value)
            
            f.write(",\n".join(values))
            f.write(";\n\n")
        
        f.write("-- Commit transaction\n")
        f.write("COMMIT TRANSACTION;\n\n")
        
        f.write("-- Verify import\n")
        f.write("SELECT \n")
        f.write("    landuse,\n")
        f.write("    COUNT(*) as count,\n")
        f.write("    MIN(year) as min_year,\n")
        f.write("    MAX(year) as max_year\n")
        f.write("FROM grouping \n")
        f.write("WHERE created_by = 'CSV Import'\n")
        f.write("GROUP BY landuse\n")
        f.write("ORDER BY count DESC;\n")
    
    logging.info(f"SQL INSERT statements saved to: {sql_file}")

def main():
    """Main function"""
    logging.info("=" * 60)
    logging.info("CSV IMPORT TO GROUPING TABLE")
    logging.info("=" * 60)
    
    try:
        # Process CSV files
        df = process_csv_files()
        
        if df is not None and not df.empty:
            logging.info("=" * 60)
            logging.info("IMPORT PREPARATION COMPLETED")
            logging.info("=" * 60)
            logging.info("Files generated:")
            logging.info("  1. processed_grouping_data.csv - Review data before import")
            logging.info("  2. grouping_inserts.sql - SQL statements for database")
            logging.info("  3. csv_import.log - Detailed log file")
            logging.info("")
            logging.info("Next steps:")
            logging.info("  1. Review the processed_grouping_data.csv file")
            logging.info("  2. Execute the grouping_inserts.sql file in your database")
            logging.info("  3. Or use the Laravel import method (see import_via_laravel.php)")
        
    except Exception as e:
        logging.error(f"Fatal error: {e}")
        raise

if __name__ == "__main__":
    main()