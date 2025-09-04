#!/bin/bash

# Pre-commit hook to prevent large files from being committed
MAX_FILE_SIZE=104857600  # 100MB in bytes

echo "Checking for large files..."

# Check staged files
large_files=$(git diff --cached --name-only | xargs -I {} find {} -type f -size +${MAX_FILE_SIZE}c 2>/dev/null)

if [ -n "$large_files" ]; then
    echo "Error: Large files detected in commit:"
    echo "$large_files"
    echo ""
    echo "Files larger than 100MB are not allowed."
    echo "Please remove these files or add them to .gitignore"
    exit 1
fi

echo "No large files detected. Proceeding with commit."
exit 0
