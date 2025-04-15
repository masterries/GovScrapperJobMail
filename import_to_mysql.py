#!/usr/bin/env python3
"""
JSON EDA and MySQL Importer
---------------------------
First performs an exploratory data analysis and then imports data into MySQL
with improved field naming and lineage tracking.
"""

import os
import sys
import json
import re
import pymysql
from pymysql.cursors import DictCursor
from collections import Counter
from datetime import datetime

# Configuration
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'db.riespatrick.de'),
    'database': os.getenv('DB_NAME', 'deine_datenbank'),
    'user': os.getenv('DB_USER', 'testjobs'),
    'password': os.getenv('DB_PASSWORD', 'dein_passwort'),
    'charset': 'utf8mb4',
    'cursorclass': DictCursor
}

# JSON file
JSON_FILE = os.getenv('JSON_FILE', './jobs_all_processed.json')
TABLE_NAME = os.getenv('TABLE_NAME', 'jobs')  # Name of the table to be created

# Field mapping to English-friendly names
FIELD_MAPPING = {
    'Administration/Organization': 'organization',
    'Application Deadline': 'application_deadline',
    'Conditions d\'admission': 'admission_conditions',
    'Contract Type': 'contract_type',
    'Documents à fournir': 'required_documents',
    'Dépot de candidature': 'application_submission',
    'Détail du poste': 'job_details',
    'Education Level': 'education_level',
    'Full Description': 'full_description',
    'Group Classification': 'group_classification',
    'Informations générales': 'general_information',
    'Job Category': 'job_category',
    'Link': 'link',
    'Location': 'location',
    'Ministry': 'ministry',
    'Missions': 'missions',
    'Nationality': 'nationality',
    'Number of Vacancies': 'vacancy_count',
    'Postuler': 'how_to_apply',
    'Profil': 'profile',
    'Qui recrute ?': 'recruiter',
    'Salary Group': 'salary_group',
    'Status': 'status',
    'Task': 'task',
    'Title': 'title',
    'adding_date': 'created_at',
    'updated_date': 'updated_at'
}

# Fields that should always be treated as TEXT
TEXT_FIELDS = [
    'admission_conditions', 'required_documents', 'job_details', 'full_description',
    'general_information', 'missions', 'how_to_apply', 'profile', 'recruiter'
]

# Fields that should be used in the unique constraint for duplicate detection
UNIQUE_FIELDS = ['link']

def load_json_data(file_path):
    """Loads data from the JSON file."""
    try:
        with open(file_path, 'r', encoding='utf-8') as file:
            return json.load(file)
    except FileNotFoundError:
        print(f"❌ Error: File '{file_path}' not found.")
        sys.exit(1)
    except json.JSONDecodeError:
        print(f"❌ Error: File '{file_path}' contains invalid JSON.")
        sys.exit(1)

def extract_job_id_from_url(url):
    """Extracts the unique ID from the job URL."""
    # Pattern to match the numerical ID at the end of the URL
    pattern = r'-(\d+)\.html$'
    match = re.search(pattern, url)
    if match:
        return int(match.group(1))
    return None

def preprocess_data(data):
    """Preprocesses the data, adding job_id from URL and mapping field names."""
    processed_data = []
    current_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    
    for item in data:
        processed_item = {}
        
        # Extract job_id from Link field if available, but don't rely on it for uniqueness
        if 'Link' in item:
            job_id = extract_job_id_from_url(item['Link'])
            if job_id:
                processed_item['extracted_id'] = job_id
        
        # Map old field names to new ones
        for old_name, value in item.items():
            # Use the mapping if available, otherwise sanitize the field name
            if old_name in FIELD_MAPPING:
                new_name = FIELD_MAPPING[old_name]
            else:
                # Sanitize field name by replacing special characters and spaces with underscores
                new_name = re.sub(r'[^a-zA-Z0-9]', '_', old_name).lower()
                # Ensure no double underscores
                new_name = re.sub(r'_+', '_', new_name)
                # Remove leading/trailing underscores
                new_name = new_name.strip('_')
                
            processed_item[new_name] = value
        
        # Add lineage information
        processed_item['source_file'] = os.path.basename(JSON_FILE)
        processed_item['imported_at'] = current_time
        processed_item['added_at'] = current_time  # When the record was added to the database
        
        processed_data.append(processed_item)
    
    return processed_data

def analyze_json_structure(data):
    """Analyzes the structure of the JSON data."""
    if not isinstance(data, list):
        print("⚠️ JSON data is not an array. Converting to an array...")
        data = [data]
    
    total_records = len(data)
    print(f"📊 Total number of records: {total_records}")
    
    # Collect all unique keys
    all_keys = set()
    for item in data:
        all_keys.update(item.keys())
    
    print(f"🔑 Found fields: {len(all_keys)}")
    print("   " + ", ".join(sorted(all_keys)))
    
    # Analyze how many records have each field
    field_stats = {}
    value_types = {}
    max_lengths = {}
    
    for key in all_keys:
        field_count = sum(1 for item in data if key in item)
        field_stats[key] = field_count
        
        # Analyze data types
        types_counter = Counter()
        lengths = []
        
        for item in data:
            if key in item:
                value = item[key]
                types_counter[type(value).__name__] += 1
                
                # Measure length for strings
                if isinstance(value, str):
                    lengths.append(len(value))
        
        value_types[key] = dict(types_counter)
        
        if lengths:
            max_lengths[key] = max(lengths)
    
    # Output results
    print("\n📋 Field statistics:")
    print(f"{'Field':<30} {'Completeness':<15} {'Types':<30} {'Max Length':<10}")
    print("-" * 85)
    
    for key in sorted(all_keys):
        completeness = f"{field_stats[key]}/{total_records} ({field_stats[key]/total_records*100:.1f}%)"
        types = ', '.join(f"{t}:{c}" for t, c in value_types[key].items())
        max_len = max_lengths.get(key, 'N/A')
        
        print(f"{key:<30} {completeness:<15} {types:<30} {max_len:<10}")
    
    return {
        'total_records': total_records,
        'all_keys': all_keys,
        'field_stats': field_stats,
        'value_types': value_types,
        'max_lengths': max_lengths
    }

def determine_column_type(key):
    """Determines the optimal MySQL data type based on the field name."""
    # Check if it's a text field
    if key in TEXT_FIELDS:
        return 'TEXT'
    
    # Special cases
    if key == 'extracted_id':
        return 'INT'
    elif key == 'link':
        return 'VARCHAR(255) UNIQUE'  # Make link unique
    elif key in ['imported_at', 'created_at', 'updated_at', 'added_at']:
        return 'DATETIME'
    elif key == 'vacancy_count':
        return 'INT'
    
    # Default to VARCHAR(255) for other fields
    return 'VARCHAR(255)'

def process_batch(cursor, table_name, batch):
    """Process a batch of records with INSERT IGNORE to handle duplicates."""
    for item_columns, values in batch:
        placeholders = ', '.join(['%s'] * len(item_columns))
        
        # Use INSERT IGNORE to skip duplicates based on unique constraints
        insert_sql = f"INSERT IGNORE INTO `{table_name}` (`{'`, `'.join(item_columns)}`) VALUES ({placeholders})"
        
        try:
            cursor.execute(insert_sql, values)
        except Exception as e:
            print(f"⚠️ Error inserting a record: {e}")
            # Continue despite error

def create_table_with_all_columns(connection, data, analysis, table_name):
    """Creates a table with all found columns."""
    columns = []
    
    # ID column as primary key
    columns.append("`id` INT AUTO_INCREMENT PRIMARY KEY")
    
    # All other columns based on the analysis
    for key in sorted(analysis['all_keys']):
        if key.lower() == 'id':  # Skip if already added
            continue
            
        col_type = determine_column_type(key)
        
        # Escape for MySQL column names - handle special characters
        escaped_key = f"`{key}`"
        columns.append(f"{escaped_key} {col_type}")
    
    # Add lineage columns
    if 'source_file' not in analysis['all_keys']:
        columns.append("`source_file` VARCHAR(255)")
    if 'imported_at' not in analysis['all_keys']:
        columns.append("`imported_at` DATETIME")
    if 'added_at' not in analysis['all_keys']:
        columns.append("`added_at` DATETIME")
    
    with connection.cursor() as cursor:
        # Check if table already exists
        cursor.execute(f"SHOW TABLES LIKE '{table_name}'")
        table_exists = cursor.fetchone()
        
        # If table doesn't exist, create it
        if not table_exists:
            create_table_sql = f"CREATE TABLE `{table_name}` ({', '.join(columns)})"
            print(f"🔧 Creating table with SQL command:\n{create_table_sql}")
            cursor.execute(create_table_sql)
            print(f"✅ Table '{table_name}' successfully created")
        else:
            print(f"✅ Table '{table_name}' already exists, will use it for import")
            
            # Check if we need to add any missing columns
            cursor.execute(f"DESCRIBE `{table_name}`")
            existing_columns = [col['Field'].lower() for col in cursor.fetchall()]
            
            for column_def in columns:
                column_parts = column_def.split()
                column_name = column_parts[0].replace('`', '')
                if column_name.lower() not in existing_columns and column_name.lower() != 'id':
                    try:
                        add_column_sql = f"ALTER TABLE `{table_name}` ADD COLUMN {column_def}"
                        print(f"🔧 Adding missing column: {column_name}")
                        cursor.execute(add_column_sql)
                    except pymysql.err.OperationalError as e:
                        if "Duplicate column name" in str(e):
                            print(f"⚠️ Column {column_name} already exists with a different case")
                        else:
                            print(f"⚠️ Error adding column {column_name}: {e}")
        
        # Insert data in batches with duplicate handling
        inserted_count = 0
        skipped_count = 0
        batch_size = 50  # Lower batch size to reduce memory usage
        current_batch = []
        
        print(f"🔄 Preparing to import {len(data)} records...")
        
        # First, collect existing links to avoid unnecessary insertion attempts
        existing_links = set()
        if 'link' in analysis['all_keys']:
            try:
                cursor.execute(f"SELECT link FROM `{table_name}` WHERE link IS NOT NULL")
                results = cursor.fetchall()
                existing_links = {row['link'] for row in results if row['link']}
                print(f"📊 Found {len(existing_links)} existing records in database")
            except Exception as e:
                print(f"⚠️ Could not fetch existing links: {e}")
                # Continue anyway - we'll rely on the database's UNIQUE constraint
        
        for item in data:
            # Skip if link already exists in database
            if 'link' in item and item['link'] in existing_links:
                skipped_count += 1
                continue
                
            # Only columns that exist in the current record
            item_columns = [key for key in item.keys() if key.lower() != 'id']
            
            if not item_columns:
                continue  # Skip if no valid columns are present
            
            # Prepare the values
            values = []
            for col in item_columns:
                value = item[col]
                # Serialize JSON data
                if isinstance(value, (dict, list)):
                    value = json.dumps(value)
                values.append(value)
            
            # Add to current batch
            current_batch.append((item_columns, values))
            
            # Process batch if it reaches the batch size
            if len(current_batch) >= batch_size:
                process_batch(cursor, table_name, current_batch)
                inserted_count += len(current_batch)
                current_batch = []
                
                # Show status every 200 records
                if inserted_count % 200 == 0:
                    print(f"   {inserted_count}/{len(data) - skipped_count} records imported...")
                    connection.commit()  # Commit periodically to avoid large transactions
        
        # Process remaining records
        if current_batch:
            process_batch(cursor, table_name, current_batch)
            inserted_count += len(current_batch)
        
        connection.commit()
        print(f"✅ {inserted_count} new records imported, {skipped_count} duplicates skipped")
        
        # Ensure we have an index on the link field for better performance
        try:
            cursor.execute(f"CREATE UNIQUE INDEX IF NOT EXISTS idx_link ON `{table_name}` (link)")
            print("✅ Unique index on link ensured")
        except pymysql.err.InternalError as e:
            if "Duplicate key name" not in str(e):
                raise
            print("✅ Unique index on link already exists")
        # Older MySQL versions don't support IF NOT EXISTS for indexes
        except pymysql.err.OperationalError:
            try:
                cursor.execute(f"SHOW INDEX FROM `{table_name}` WHERE Column_name = 'link'")
                if not cursor.fetchone():
                    cursor.execute(f"CREATE UNIQUE INDEX idx_link ON `{table_name}` (link)")
                    print("✅ Unique index on link created")
                else:
                    print("✅ Unique index on link already exists")
            except Exception as idx_err:
                print(f"⚠️ Note: Could not verify or create index on link: {idx_err}")
        
        return True

def main():
    """Main function to run the analysis and import."""
    # Check if we're in non-interactive mode (for GitHub Actions)
    auto_mode = os.getenv('AUTO_MODE', 'false').lower() == 'true'
    
    print(f"🔄 Loading JSON data from {JSON_FILE}...")
    raw_data = load_json_data(JSON_FILE)
    
    print(f"🔄 Preprocessing data...")
    data = preprocess_data(raw_data)
    
    print("\n📊 Performing EDA (Exploratory Data Analysis)...")
    analysis = analyze_json_structure(data)
    
    try:
        # Configure database connection with optimized settings
        config = DB_CONFIG.copy()
        config['connect_timeout'] = 30  # Increase timeout for large datasets
        config['autocommit'] = False  # We'll handle transactions manually
        
        print(f"\n🔄 Connecting to MySQL database...")
        connection = pymysql.connect(**config)
        
        if not auto_mode:
            proceed = input("\n⚠️ Do you want to proceed with the import? (y/n): ")
            if proceed.lower() != 'y':
                print("Import aborted.")
                return
        else:
            print("\n⚠️ Running in automatic mode - proceeding with import...")
        
        # Set longer timeout for operations
        with connection.cursor() as cursor:
            cursor.execute("SET SESSION wait_timeout = 28800")  # 8 hours
        
        success = create_table_with_all_columns(connection, data, analysis, TABLE_NAME)
        
        if success:
            print(f"🎉 Import completed!")
            
            # Show structure of the created table
            with connection.cursor() as cursor:
                cursor.execute(f"DESCRIBE `{TABLE_NAME}`")
                columns = cursor.fetchall()
                
                print(f"\n📋 Structure of the created table '{TABLE_NAME}':")
                for column in columns:
                    print(f"   - {column['Field']} ({column['Type']})")
                
                # Index recommendations
                print("\n💡 Recommended indexes:")
                print("   - CREATE INDEX idx_organization ON jobs(organization);")
                print("   - CREATE INDEX idx_location ON jobs(location);")
                print("   - CREATE INDEX idx_job_category ON jobs(job_category);")
                print("   - CREATE INDEX idx_created_at ON jobs(created_at);")
                print("   - CREATE INDEX idx_added_at ON jobs(added_at);")
                
                # Show usage example
                print("\n📊 Sample queries:")
                print("   - SELECT COUNT(*) FROM jobs;")
                print("   - SELECT * FROM jobs WHERE link LIKE '%313905.html';")
                print("   - SELECT * FROM jobs WHERE added_at > '2025-04-01' LIMIT 10;")
                print("   - SELECT organization, COUNT(*) FROM jobs GROUP BY organization ORDER BY COUNT(*) DESC LIMIT 10;")
        
    except Exception as e:
        print(f"❌ Error: {e}")
        sys.exit(1)  # Exit with error code for GitHub Actions
    finally:
        if 'connection' in locals() and connection.open:
            connection.close()
            print("🔒 Database connection closed")

if __name__ == "__main__":
    main()