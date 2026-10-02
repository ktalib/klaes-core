"""
Streamlined Python File Number Generator
Only inserts essential fields for maximum speed
"""

import pandas as pd
import pyodbc
import time
from datetime import datetime
import numpy as np

class StreamlinedFileNumberGenerator:
    
    def __init__(self):
        # Database connection settings (from Laravel .env)
        self.server = 'VMI2583396'
        self.database = 'klas'
        self.username = 'klas'
        self.password = 'YourStrongPassword123!'
        self.driver = '{ODBC Driver 17 for SQL Server}'
        
        # File formats with land use mapping
        self.file_formats = [
            {'prefix': 'RES', 'landuse': 'RESIDENTIAL'},
            {'prefix': 'COM', 'landuse': 'COMMERCIAL'},
            {'prefix': 'AG', 'landuse': 'AGRICULTURE'},
            {'prefix': 'RES-RC', 'landuse': 'RESIDENTIAL'},
            {'prefix': 'COM-RC', 'landuse': 'COMMERCIAL'},
            {'prefix': 'AG-RC', 'landuse': 'AGRICULTURE'},
            {'prefix': 'CON-RES', 'landuse': 'RESIDENTIAL'},
            {'prefix': 'CON-COM', 'landuse': 'COMMERCIAL'},
            {'prefix': 'CON-AG', 'landuse': 'AGRICULTURE'},
            {'prefix': 'CON-RES-RC', 'landuse': 'RESIDENTIAL'},
            {'prefix': 'CON-COM-RC', 'landuse': 'COMMERCIAL'},
            {'prefix': 'CON-AG-RC', 'landuse': 'AGRICULTURE'}
        ]
        
        self.stats = {
            'total_inserted': 0,
            'start_time': None,
            'sheet_times': []
        }
    
    def connect_database(self):
        """Create optimized database connection"""
        connection_string = (
            f"DRIVER={self.driver};"
            f"SERVER={self.server};"
            f"DATABASE={self.database};"
            f"UID={self.username};"
            f"PWD={self.password};"
            "TrustServerCertificate=yes;"
            "Connection Timeout=300;"
        )
        return pyodbc.connect(connection_string)
    
    def generate_sheet_data(self, prefix, landuse):
        """Generate data for one sheet using pandas vectorization"""
        print(f"  📊 Generating {prefix} data...")
        
        # Create all year-number combinations efficiently
        years = np.arange(1981, 2026)  # 1981-2025 (45 years)
        numbers = np.arange(1, 5001)   # 1-5000 numbers per year
        
        # Create meshgrid for all combinations
        year_grid, number_grid = np.meshgrid(years, numbers, indexing='ij')
        
        # Flatten arrays
        years_flat = year_grid.flatten()
        numbers_flat = number_grid.flatten()
        
        # Generate file numbers vectorized
        file_numbers = np.array([f"{prefix}-{year}-{number}" 
                               for year, number in zip(years_flat, numbers_flat)])
        
        # Create streamlined DataFrame with ONLY required columns
        df = pd.DataFrame({
            'awaiting_fileno': file_numbers,
            'number': numbers_flat.astype(str),
            'year': years_flat,
            'created_by': 'python_generator',
            'landuse': landuse,
            'created_at': datetime.now()
        })
        
        print(f"    ✅ Generated {len(df):,} records")
        return df
    
    def bulk_insert_fast(self, df, connection):
        """Ultra-fast bulk insert with only essential columns"""
        try:
            print(f"    💾 Bulk inserting {len(df):,} records...")
            start_time = time.time()
            
            # Get cursor for raw SQL execution
            cursor = connection.cursor()
            
            # Prepare bulk insert SQL with only required columns
            sql = """
            INSERT INTO grouping (awaiting_fileno, number, year, created_by, landuse, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
            """
            
            # Prepare data tuples for executemany
            data_tuples = [
                (
                    row['awaiting_fileno'],
                    row['number'], 
                    int(row['year']),
                    row['created_by'],
                    row['landuse'],
                    row['created_at']
                )
                for _, row in df.iterrows()
            ]
            
            # Execute bulk insert
            cursor.executemany(sql, data_tuples)
            connection.commit()
            
            insert_time = time.time() - start_time
            records_per_second = len(df) / insert_time if insert_time > 0 else 0
            
            print(f"    ✅ Inserted in {insert_time:.1f}s ({records_per_second:,.0f} records/sec)")
            cursor.close()
            return len(df)
            
        except Exception as e:
            print(f"    ❌ Bulk insert failed: {str(e)}")
            connection.rollback()
            raise e
    
    def run_generation(self):
        """Main generation process"""
        print("🚀 STREAMLINED PYTHON FILE NUMBER GENERATOR")
        print("=" * 60)
        print("📋 Target: 12 sheets × 225,000 records = 2,700,000 total")
        print("🎯 Columns: id, awaiting_fileno, number, year, created_by, landuse, created_at")
        print("⚡ Method: Pandas vectorization + optimized SQL bulk insert")
        print("🕐 Expected: 2-5 minutes total\n")
        
        # Credentials are now set from Laravel .env values
        print(f"🔐 Using credentials: {self.username}@{self.server}/{self.database}")
        print("🔗 Connection: SQL Server with Laravel credentials")
        
        self.stats['start_time'] = time.time()
        
        try:
            # Test database connection
            print("🔍 Testing database connection...")
            connection = self.connect_database()
            cursor = connection.cursor()
            cursor.execute("SELECT COUNT(*) FROM grouping")
            initial_count = cursor.fetchone()[0]
            print(f"  Current records in table: {initial_count:,}")
            cursor.close()
            
            # Process each sheet
            for i, format_info in enumerate(self.file_formats, 1):
                sheet_start = time.time()
                prefix = format_info['prefix']
                landuse = format_info['landuse']
                
                print(f"\n📋 Sheet {i}/12: {prefix} → {landuse}")
                
                # Generate data with pandas
                df = self.generate_sheet_data(prefix, landuse)
                
                # Bulk insert to database
                inserted = self.bulk_insert_fast(df, connection)
                self.stats['total_inserted'] += inserted
                
                # Track sheet performance
                sheet_time = time.time() - sheet_start
                self.stats['sheet_times'].append({
                    'sheet': prefix,
                    'records': inserted,
                    'time': sheet_time,
                    'speed': inserted / sheet_time if sheet_time > 0 else 0
                })
                
                print(f"  ✅ {prefix} completed: {inserted:,} records in {sheet_time:.1f}s")
                
                # Memory cleanup
                del df
            
            connection.close()
            self.print_final_report()
            return True
            
        except Exception as e:
            print(f"❌ Generation failed: {str(e)}")
            return False
    
    def print_final_report(self):
        """Print comprehensive final report"""
        total_time = time.time() - self.stats['start_time']
        avg_speed = self.stats['total_inserted'] / total_time if total_time > 0 else 0
        
        print("\n" + "=" * 60)
        print("🎉 STREAMLINED GENERATION COMPLETE!")
        print("=" * 60)
        
        print(f"📊 FINAL STATISTICS:")
        print(f"  Total Records Inserted: {self.stats['total_inserted']:,}")
        print(f"  Total Time: {total_time/60:.2f} minutes ({total_time:.1f} seconds)")
        print(f"  Average Speed: {avg_speed:,.0f} records/second")
        
        print(f"\n📋 SHEET PERFORMANCE:")
        total_sheet_time = 0
        for sheet_stat in self.stats['sheet_times']:
            total_sheet_time += sheet_stat['time']
            print(f"  {sheet_stat['sheet']:>12}: {sheet_stat['records']:>7,} records in {sheet_stat['time']:>5.1f}s ({sheet_stat['speed']:>6,.0f} rec/s)")
        
        print(f"\n🎯 VERIFICATION:")
        print(f"  Expected total: 2,700,000")
        print(f"  Actual total:   {self.stats['total_inserted']:,}")
        match = self.stats['total_inserted'] == 2700000
        print(f"  Perfect match:  {'✅ YES' if match else '❌ NO'}")
        
        if match:
            print(f"\n🚀 SUCCESS: All 2.7M file numbers generated successfully!")
            print(f"🔍 Verify with: SELECT COUNT(*) FROM grouping;")
            print(f"🏷️  Check distribution: SELECT landuse, COUNT(*) FROM grouping GROUP BY landuse;")
        
        print(f"\n⚡ PERFORMANCE HIGHLIGHT:")
        print(f"  Completed 2.7M records in {total_time/60:.2f} minutes")
        print(f"  That's {avg_speed:,.0f} records per second!")
        if total_time < 300:  # Less than 5 minutes
            print(f"  🏆 EXCELLENT: Under 5 minutes!")
        elif total_time < 600:  # Less than 10 minutes  
            print(f"  🎯 GREAT: Under 10 minutes!")
        else:
            print(f"  ✅ GOOD: Completed successfully!")

if __name__ == "__main__":
    print("🐍 Streamlined Python File Number Generator")
    print("📝 Only essential columns: awaiting_fileno, number, year, created_by, landuse, created_at")
    print("🔐 Using Laravel .env SQL Server credentials")
    print()
    
    generator = StreamlinedFileNumberGenerator()
    
    print(f"🚀 Starting generation...")
    print("⏱️  Estimated time: 2-5 minutes")
    print("💾 Memory usage: ~50-100 MB")
    print("🔄 Press Ctrl+C to cancel\n")
    
    success = generator.run_generation()
    
    if success:
        print("\n✅ MISSION ACCOMPLISHED!")
        print("🎯 Your 2.7M file numbers are ready in the database!")
    else:
        print("\n❌ Generation failed. Check error messages above.")