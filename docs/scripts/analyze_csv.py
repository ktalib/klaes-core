"""
Quick test script to analyze CSV files before import
"""

import pandas as pd
import os
import glob

def analyze_csv_files():
    """Analyze all CSV files in the import directory"""
    
    import_dir = "public/import"
    csv_files = glob.glob(os.path.join(import_dir, "*.csv"))
    
    if not csv_files:
        print(f"No CSV files found in {import_dir}")
        return
    
    print(f"Found {len(csv_files)} CSV files")
    print("=" * 60)
    
    total_records = 0
    landuse_counts = {}
    
    for csv_file in csv_files:
        filename = os.path.basename(csv_file)
        print(f"\n📁 {filename}")
        
        try:
            # Read just the first few rows to check structure
            df_sample = pd.read_csv(csv_file, nrows=5)
            df_full = pd.read_csv(csv_file)
            
            print(f"   Columns: {list(df_sample.columns)}")
            print(f"   Records: {len(df_full):,}")
            print(f"   Sample data:")
            
            for i, row in df_sample.iterrows():
                fileno = str(row['FileNo']).strip('"')
                prefix = fileno.split('-')[0].upper()
                print(f"     {row['Year']} | {row['Number']} | {fileno} | Prefix: {prefix}")
                
                # Count land use types
                if prefix not in landuse_counts:
                    landuse_counts[prefix] = 0
                landuse_counts[prefix] += len(df_full)
            
            total_records += len(df_full)
            
        except Exception as e:
            print(f"   Error: {e}")
    
    print("\n" + "=" * 60)
    print("SUMMARY")
    print("=" * 60)
    print(f"Total files: {len(csv_files)}")
    print(f"Total records: {total_records:,}")
    print(f"\nLand use distribution:")
    
    for prefix, count in sorted(landuse_counts.items()):
        print(f"  {prefix}: {count:,}")

if __name__ == "__main__":
    analyze_csv_files()