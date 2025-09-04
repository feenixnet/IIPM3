#!/bin/bash

# Script to remove large files from Git history
echo "This script will help you remove large files from Git history."
echo "WARNING: This will rewrite Git history and may affect other collaborators."
echo ""

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    echo "Error: Not in a Git repository"
    exit 1
fi

# Find large files
echo "Finding large files (>100MB)..."
large_files=$(find . -type f -size +100M -not -path "./.git/*")

if [ -z "$large_files" ]; then
    echo "No large files found."
    exit 0
fi

echo "Large files found:"
echo "$large_files"
echo ""

# Ask for confirmation
read -p "Do you want to remove these files from Git history? (y/N): " confirm
if [[ $confirm != [yY] ]]; then
    echo "Operation cancelled."
    exit 0
fi

# Remove files from Git history
echo "Removing large files from Git history..."
echo "$large_files" | while read file; do
    if [ -n "$file" ]; then
        echo "Removing $file from Git history..."
        git filter-branch --force --index-filter \
        "git rm --cached --ignore-unmatch '$file'" \
        --prune-empty --tag-name-filter cat -- --all
    fi
done

# Clean up
echo "Cleaning up..."
git for-each-ref --format='delete %(refname)' refs/original | git update-ref --stdin
git reflog expire --expire=now --all
git gc --prune=now --aggressive

echo "Cleanup complete!"
echo "You may need to force push: git push --force-with-lease"
