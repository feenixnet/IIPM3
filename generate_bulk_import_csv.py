#!/usr/bin/env python3
"""
CSV Generator for Bulk Import Template
Generates a CSV file with sample data for the bulk import functionality.
"""

import csv
import random
from datetime import datetime

def generate_sample_data():
    """Generate sample data for bulk import CSV"""
    
    # Required columns
    required_columns = [
        'first_name',
        'last_name', 
        'email',
        'member_type'
    ]
    
    # Optional columns
    optional_columns = [
        'user_phone',
        'user_mobile',
        'user_designation',
        'city_or_town',
        'user_payment_method',
        'Address_1',
        'Address_2',
        'Address_3',
        'Address_1_pers',
        'Address_2_pers',
        'Address_3_pers',
        'user_name_login'
    ]
    
    # Sample data pools
    first_names = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Lisa', 'James', 'Maria']
    last_names = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez']
    designations = ['Manager', 'Developer', 'Analyst', 'Coordinator', 'Specialist', 'Director', 'Consultant', 'Supervisor']
    cities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego']
    payment_methods = ['Credit Card', 'Bank Transfer', 'PayPal', 'Check', 'Cash']
    
    # Generate sample records (max 10)
    records = []
    for i in range(10):
        first_name = random.choice(first_names)
        last_name = random.choice(last_names)
        email = f"{first_name.lower()}.{last_name.lower()}{i+1}@example.com"
        
        record = {
            # Required fields
            'first_name': first_name,
            'last_name': last_name,
            'email': email,
            'member_type': 'organisation',
            
            # Optional fields (some may be empty)
            'user_phone': f"+1-{random.randint(200, 999)}-{random.randint(100, 999)}-{random.randint(1000, 9999)}" if random.random() > 0.3 else '',
            'user_mobile': f"+1-{random.randint(200, 999)}-{random.randint(100, 999)}-{random.randint(1000, 9999)}" if random.random() > 0.3 else '',
            'user_designation': random.choice(designations) if random.random() > 0.2 else '',
            'city_or_town': random.choice(cities) if random.random() > 0.2 else '',
            'user_payment_method': random.choice(payment_methods) if random.random() > 0.3 else '',
            'Address_1': f"{random.randint(100, 9999)} Main St" if random.random() > 0.3 else '',
            'Address_2': f"Apt {random.randint(1, 50)}" if random.random() > 0.5 else '',
            'Address_3': random.choice(cities) if random.random() > 0.4 else '',
            'Address_1_pers': f"{random.randint(100, 9999)} Home Ave" if random.random() > 0.4 else '',
            'Address_2_pers': f"Unit {random.randint(1, 20)}" if random.random() > 0.6 else '',
            'Address_3_pers': random.choice(cities) if random.random() > 0.5 else '',
            'user_name_login': f"{first_name.lower()}{last_name.lower()}{i+1}" if random.random() > 0.2 else ''
        }
        
        records.append(record)
    
    return records, required_columns + optional_columns

def create_csv_file(filename='bulk_import_sample.csv'):
    """Create CSV file with sample data"""
    
    records, columns = generate_sample_data()
    
    with open(filename, 'w', newline='', encoding='utf-8') as csvfile:
        writer = csv.DictWriter(csvfile, fieldnames=columns)
        
        # Write header
        writer.writeheader()
        
        # Write data rows
        writer.writerows(records)
    
    print(f"CSV file '{filename}' generated successfully!")
    print(f"Generated {len(records)} sample records with the following columns:")
    print(f"Required: {', '.join(columns[:4])}")
    print(f"Optional: {', '.join(columns[4:])}")
    
    return filename

if __name__ == "__main__":
    # Generate the CSV file
    filename = create_csv_file()
    
    # Display first few rows as preview
    print("\nPreview of generated data:")
    print("-" * 80)
    
    with open(filename, 'r', encoding='utf-8') as csvfile:
        reader = csv.reader(csvfile)
        for i, row in enumerate(reader):
            if i < 4:  # Show header + first 3 data rows
                print(f"Row {i}: {', '.join(row[:6])}...")  # Show first 6 columns
            else:
                break
