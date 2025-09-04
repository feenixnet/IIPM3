#!/bin/bash

# Script to find large files in the repository
echo "Finding files larger than 100MB..."

# Find files larger than 100MB
find . -type f -size +100M -not -path "./.git/*" | while read file; do
    size=$(du -h "$file" | cut -f1)
    echo "Large file found: $file ($size)"
done

echo ""
echo "Finding files larger than 500MB..."

# Find files larger than 500MB
find . -type f -size +500M -not -path "./.git/*" | while read file; do
    size=$(du -h "$file" | cut -f1)
    echo "Very large file found: $file ($size)"
done

echo ""
echo "Repository size:"
du -sh .git
