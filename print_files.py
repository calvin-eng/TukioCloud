#!/usr/bin/env python3
import os

# Read and display a file
def read_file(path):
    with open(path, 'r') as f:
        return f.read()

# Check current directory
print("Checking current directory and files...")
print()

# Check Template model
model_path = os.path.join('app', 'Models', 'Template.php')
if os.path.exists(model_path):
    print("=== Template Model ===")
    print(read_file(model_path))
    print()
else:
    print(f"Template model not found at {model_path}")

# Check the templates migration
migrations_dir = os.path.join('database', 'migrations')
if os.path.exists(migrations_dir):
    files = os.listdir(migrations_dir)
    template_files = []
    for f in files:
        if f.endswith('.php') and 'template' in f.lower():
            template_files.append(f)
    
    if template_files:
        latest_template = sorted(template_files)[-1]
        latest_path = os.path.join(migrations_dir, latest_template)
        print(f"=== {latest_template} ===")
        print(read_file(latest_path))
    else:
        print("No template migration files found")
else:
    print(f"Migrations directory not found at {migrations_dir}")
else:
    print(f"template not found at {template}")

# Check CardGenerationService
print("\n=== CardGenerationService (font fields) ===")
card_path = os.path.join('app', 'Services', 'CardGenerationService.php')
if os.path.exists(card_path):
    print(read_file(card_path))
else:
    print(f"CardGenerationService not found at {card_path}")