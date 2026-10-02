"""
Python Direct File Number Generator
High-performance version using pandas bulk operations
"""

import pandas as pd
import pyodbc
import time
from datetime import datetime
import numpy as np

class PythonFileNumberGenerator:
    
    def __init__(self):
        # Database connection settings
        self.server = 'VMI2583396'
        self.database = 'klas'
        self.username = 'sa'
        self.password = ''  # SET THIS
        self.driver = '{ODBC Driver 17 for SQL Server}'
        
        # File formats matching the JavaScript/PHP versions
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
            'total_generated': 0,
            'total_inserted': 0,
            'start_time': None,
            'sheet_times': []
        }
    
    def connect_database(self):
        """Create database connection with optimized settings"""
        connection_string = (
            f"DRIVER={self.driver};"
            f"SERVER={self.server};"
            f"DATABASE={self.database};"
            f"UID={self.username};"
            f"PWD={self.password};"
            "TrustServerCertificate=yes;"
            "MARS_Connection=yes;"  # Multiple Active Result Sets
            "Connection Timeout=300;"  # 5 minutes
        )
        
        return pyodbc.connect(connection_string, autocommit=False)
    
    def generate_sheet_data(self, prefix, landuse):
        """Generate all data for a single sheet using pandas vectorization"""
        print(f"  📊 Generating data for {prefix}...")
        
        # Create year and number arrays
        years = np.arange(1981, 2026)  # 1981-2025 (45 years)
        numbers = np.arange(1, 5001)   # 1-5000 (5000 numbers)
        
        # Create meshgrid for all combinations
        year_grid, number_grid = np.meshgrid(years, numbers, indexing='ij')
        
        # Flatten to create arrays
        years_flat = year_grid.flatten()
        numbers_flat = number_grid.flatten()
        
        # Generate file numbers using vectorized string operations
        file_numbers = np.array([f"{prefix}-{year}-{number}" 
                               for year, number in zip(years_flat, numbers_flat)])
        
        # Create DataFrame
        df = pd.DataFrame({
            'awaiting_fileno': file_numbers,
            'number': numbers_flat.astype(str),
            'year': years_flat,
            'landuse': landuse,
            'mls_fileno': None,
            'mapping': 0,
            'group': None,
            'batch_no': f'PY_GEN_{datetime.now().strftime("%Y%m%d_%H%M%S")}',
            'mdc_batch_no': None,
            'sys_batch_no': None,
            'shelf_rack': None,
            'date': None,
            'created_by': 'python_generator',
            'indexed_by': None,
            'date_index': None,
            'created_at': datetime.now(),
            'updated_at': datetime.now(),
            'updated_by': 'python_generator',
            'deleted_at': None,
            'deleted_by': None
        })
        
        return df
    
    def bulk_insert_dataframe(self, df, connection):
        """Ultra-fast bulk insert using pandas to_sql"""
        try:
            print(f"    💾 Inserting {len(df):,} records...")
            start_time = time.time()
            
            # Use pandas to_sql for maximum speed
            # Note: This requires sqlalchemy for best performance
            df.to_sql(
                name='grouping',
                con=connection,
                if_exists='append',
                index=False,
                method='multi',  # Use executemany for speed
                chunksize=10000  # Insert in 10K chunks
            )
            
            insert_time = time.time() - start_time
            records_per_second = len(df) / insert_time if insert_time > 0 else 0
            
            print(f"    ✅ Inserted in {insert_time:.1f}s ({records_per_second:,.0f} records/sec)")
            return len(df)
            
        except Exception as e:
            print(f"    ❌ Bulk insert failed: {str(e)}")
            # Fallback to batch insert
            return self.batch_insert_fallback(df, connection)
    
    def batch_insert_fallback(self, df, connection):
        """Fallback batch insert method if bulk insert fails"""
        cursor = connection.cursor()
        batch_size = 5000
        inserted = 0
        
        try:
            sql = """
            INSERT INTO grouping (
                awaiting_fileno, number, year, landuse, mls_fileno, mapping,
                [group], batch_no, mdc_batch_no, sys_batch_no, shelf_rack,
                date, created_by, indexed_by, date_index,
                created_at, updated_at, updated_by, deleted_at, deleted_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """
            
            for start_idx in range(0, len(df), batch_size):
                end_idx = min(start_idx + batch_size, len(df))
                batch_df = df.iloc[start_idx:end_idx]
                
                batch_data = []
                for _, row in batch_df.iterrows():
                    batch_data.append((
                        row['awaiting_fileno'], row['number'], row['year'], row['landuse'],
                        row['mls_fileno'], row['mapping'], row['group'], row['batch_no'],
                        row['mdc_batch_no'], row['sys_batch_no'], row['shelf_rack'],
                        row['date'], row['created_by'], row['indexed_by'], row['date_index'],
                        row['created_at'], row['updated_at'], row['updated_by'],
                        row['deleted_at'], row['deleted_by']
                    ))
                
                cursor.executemany(sql, batch_data)
                connection.commit()
                inserted += len(batch_data)
                
                if start_idx % (batch_size * 10) == 0:  # Progress every 50K
                    progress = (start_idx / len(df)) * 100
                    print(f"    Progress: {start_idx:,} / {len(df):,} ({progress:.1f}%)")
            
            return inserted
            
        except Exception as e:
            connection.rollback()
            raise e
        finally:
            cursor.close()
    
    def generate_all_data(self):
        """Generate and insert all file numbers"""
        print("🚀 PYTHON HIGH-PERFORMANCE FILE NUMBER GENERATOR")
        print("=" * 60)
        print(f"Target: {len(self.file_formats)} sheets × 225,000 records = 2,700,000 total")
        print("Method: Pandas vectorization + bulk SQL operations")
        print("Expected speed: 5,000-15,000 records/second\n")
        
        self.stats['start_time'] = time.time()
        
        try:
            # Test connection first
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
                
                # Generate data using pandas
                df = self.generate_sheet_data(prefix, landuse)
                self.stats['total_generated'] += len(df)
                
                # Insert into database
                inserted = self.bulk_insert_dataframe(df, connection)
                self.stats['total_inserted'] += inserted
                
                sheet_time = time.time() - sheet_start
                self.stats['sheet_times'].append({
                    'sheet': prefix,
                    'records': inserted,
                    'time': sheet_time,
                    'speed': inserted / sheet_time if sheet_time > 0 else 0
                })
                
                print(f"  ✅ {prefix}: {inserted:,} records in {sheet_time:.1f}s")
                
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
        print("🎉 PYTHON GENERATION COMPLETE!")
        print("=" * 60)
        
        print(f"📊 FINAL STATISTICS:")
        print(f"  Total Records Generated: {self.stats['total_generated']:,}")
        print(f"  Total Records Inserted:  {self.stats['total_inserted']:,}")
        print(f"  Total Time: {total_time/60:.1f} minutes ({total_time:.0f} seconds)")
        print(f"  Average Speed: {avg_speed:,.0f} records/second")
        print(f"  Success Rate: {(self.stats['total_inserted']/self.stats['total_generated'])*100:.1f}%")
        
        print(f"\n📋 SHEET PERFORMANCE:")
        for sheet_stat in self.stats['sheet_times']:
            print(f"  {sheet_stat['sheet']:>12}: {sheet_stat['records']:>7,} records in {sheet_stat['time']:>5.1f}s ({sheet_stat['speed']:>5,.0f} rec/s)")
        
        print(f"\n🔍 VERIFICATION:")
        print(f"  Expected total: 2,700,000")
        print(f"  Actual total:   {self.stats['total_inserted']:,}")
        print(f"  Match: {'✅ YES' if self.stats['total_inserted'] == 2700000 else '❌ NO'}")

def estimate_python_performance():
    """Estimate Python performance vs PHP"""
    print("📊 PYTHON vs PHP PERFORMANCE COMPARISON")
    print("=" * 50)
    
    print("\n🐍 PYTHON ADVANTAGES:")
    print("  ✅ Pandas vectorization: 10-100x faster data generation")
    print("  ✅ NumPy arrays: Efficient memory usage")
    print("  ✅ Bulk SQL operations: 5-10x faster database writes")
    print("  ✅ to_sql() method: Optimized for large datasets")
    print("  ✅ Less overhead: Direct memory-to-database transfer")
    
    print("\n⚡ ESTIMATED PERFORMANCE:")
    print("  PHP Speed:    390-557 records/second")
    print("  Python Speed: 5,000-15,000 records/second")
    print("  Improvement:  10-25x faster")
    
    print("\n⏱️  ESTIMATED TIMES:")
    print("  PHP Time:    1h 55m - 2h 53m")
    print("  Python Time: 3-9 minutes")
    print("  Time Saved:  1h 46m - 2h 50m")
    
    print("\n💾 MEMORY USAGE:")
    print("  PHP:    <10 MB (batch processing)")
    print("  Python: 50-100 MB (vectorized operations)")
    print("  Trade-off: More memory for much faster speed")
    
    print("\n🚀 RECOMMENDATION:")
    print("  Use Python for: Maximum speed, one-time generation")
    print("  Use PHP for: Lower memory, Laravel integration")

if __name__ == "__main__":
    # Show performance comparison first
    estimate_python_performance()
    
    print("\n" + "=" * 50)
    response = input("Generate file numbers with Python? (y/n): ").lower()
    
    if response == 'y':
        generator = PythonFileNumberGenerator()
        
        # Check if password is set
        if not generator.password:
            print("❌ ERROR: Database password not configured!")
            print("Edit the script and set self.password = 'your_password'")
        else:
            success = generator.generate_all_data()
            if success:
                print("\n✅ SUCCESS: All file numbers generated!")
            else:
                print("\n❌ FAILED: Check errors above.")
    else:
        print("Generation cancelled.")