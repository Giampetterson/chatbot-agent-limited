#!/bin/bash
#
# LIGHTBOT Database Restore Script
#
# This script restores LIGHTBOT database from backup files
# with verification and safety checks.
#
# Usage: ./restore-database.sh <backup_file> [target_database]
# 
# Examples:
#   ./restore-database.sh ../backups/lightbot_backup_20250904_120000_full.sql.gz
#   ./restore-database.sh schema_only.sql lightbot_test
#
# Author: Generated for LIGHTBOT project
# Date: 2025-09-04

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_FILE="$1"
TARGET_DB="${2:-lightbot_restored_$(date +%Y%m%d_%H%M%S)}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Usage function
usage() {
    echo "Usage: $0 <backup_file> [target_database]"
    echo
    echo "Arguments:"
    echo "  backup_file     Path to backup file (.sql or .sql.gz)"
    echo "  target_database Target database name (optional)"
    echo
    echo "Examples:"
    echo "  $0 ../backups/lightbot_backup_20250904_120000_full.sql.gz"
    echo "  $0 schema_only.sql lightbot_test"
    echo
    echo "Available backups:"
    if [ -d "$SCRIPT_DIR/../backups" ]; then
        ls -la "$SCRIPT_DIR/../backups/"*.gz 2>/dev/null | tail -5 || echo "  No backup files found"
    else
        echo "  Backup directory not found"
    fi
}

# Validate arguments
if [ -z "$BACKUP_FILE" ]; then
    log_error "Backup file not specified"
    usage
    exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
    log_error "Backup file not found: $BACKUP_FILE"
    exit 1
fi

echo "=============================================="
echo "  LIGHTBOT Database Restore Script"
echo "=============================================="
echo "Backup file: $BACKUP_FILE"
echo "Target database: $TARGET_DB"
echo "=============================================="
echo

# Check MySQL connection
log_info "Checking MySQL connection..."
if ! mysql -u root -p -e "SELECT 1;" >/dev/null 2>&1; then
    log_error "Cannot connect to MySQL as root"
    exit 1
fi
log_success "MySQL connection verified"

# Check if target database exists
log_info "Checking target database..."
if mysql -u root -p -e "USE $TARGET_DB;" 2>/dev/null; then
    log_warning "Target database '$TARGET_DB' already exists!"
    echo
    echo "Options:"
    echo "  1. Drop and recreate database"
    echo "  2. Restore into existing database (may cause conflicts)"
    echo "  3. Cancel restore"
    echo
    read -p "Choose option (1-3): " -n 1 -r
    echo
    
    case $REPLY in
        1)
            log_info "Dropping existing database..."
            mysql -u root -p -e "DROP DATABASE $TARGET_DB;"
            log_success "Database dropped"
            ;;
        2)
            log_warning "Proceeding with restore into existing database"
            ;;
        3)
            log_error "Restore cancelled by user"
            exit 1
            ;;
        *)
            log_error "Invalid option"
            exit 1
            ;;
    esac
fi

# Create database if it doesn't exist
log_info "Creating target database if not exists..."
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS $TARGET_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
log_success "Target database ready"

# Determine file type and restore method
log_info "Analyzing backup file..."
if [[ "$BACKUP_FILE" == *.gz ]]; then
    log_info "Detected compressed backup file"
    RESTORE_CMD="gunzip < '$BACKUP_FILE' | mysql -u root -p '$TARGET_DB'"
elif [[ "$BACKUP_FILE" == *.sql ]]; then
    log_info "Detected SQL backup file"
    RESTORE_CMD="mysql -u root -p '$TARGET_DB' < '$BACKUP_FILE'"
else
    log_error "Unsupported file format. Use .sql or .sql.gz files"
    exit 1
fi

# Show file information
log_info "Backup file information:"
echo "  - File: $(basename "$BACKUP_FILE")"
echo "  - Size: $(ls -lh "$BACKUP_FILE" | awk '{print $5}')"
echo "  - Modified: $(ls -l "$BACKUP_FILE" | awk '{print $6, $7, $8}')"

# Confirmation
echo
log_warning "This will restore the backup into database '$TARGET_DB'"
read -p "Are you sure you want to continue? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    log_error "Restore cancelled by user"
    exit 1
fi

# Perform restore
log_info "Starting database restore..."
START_TIME=$(date +%s)

if [[ "$BACKUP_FILE" == *.gz ]]; then
    gunzip < "$BACKUP_FILE" | mysql -u root -p "$TARGET_DB"
else
    mysql -u root -p "$TARGET_DB" < "$BACKUP_FILE"
fi

END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

log_success "Database restore completed in ${DURATION} seconds"

# Verify restore
log_info "Verifying restore..."
TABLE_COUNT=$(mysql -u root -p "$TARGET_DB" -e "SHOW TABLES;" 2>/dev/null | wc -l)
if [ "$TABLE_COUNT" -gt 1 ]; then
    log_success "Restore verified: $((TABLE_COUNT-1)) tables found"
else
    log_error "Restore verification failed: no tables found"
    exit 1
fi

# Show table information
log_info "Restored tables:"
mysql -u root -p "$TARGET_DB" -e "
SELECT 
    TABLE_NAME as 'Table',
    TABLE_ROWS as 'Rows',
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as 'Size_MB'
FROM 
    information_schema.TABLES 
WHERE 
    TABLE_SCHEMA = '$TARGET_DB'
ORDER BY 
    (DATA_LENGTH + INDEX_LENGTH) DESC;
" 2>/dev/null

# Create restore log
RESTORE_LOG="$SCRIPT_DIR/../logs/restore_$(date +%Y%m%d_%H%M%S).log"
mkdir -p "$(dirname "$RESTORE_LOG")"

cat > "$RESTORE_LOG" << EOF
LIGHTBOT Database Restore Log
=============================

Restore Date: $(date)
Source File: $BACKUP_FILE
Target Database: $TARGET_DB
Duration: ${DURATION} seconds
Tables Restored: $((TABLE_COUNT-1))
Status: SUCCESS

Restored by: $(whoami)
Server: $(hostname)
MySQL Version: $(mysql --version)

File Information:
- Source: $(ls -l "$BACKUP_FILE")
EOF

log_success "Restore log created: $RESTORE_LOG"

echo
echo "=============================================="
log_success "Database restore completed successfully!"
echo "=============================================="
echo
log_info "Database connection details:"
echo "  - Database: $TARGET_DB"
echo "  - Tables: $((TABLE_COUNT-1))"
echo "  - Duration: ${DURATION} seconds"
echo
log_info "Next steps:"
echo "  1. Test the database connection:"
echo "     mysql -u root -p $TARGET_DB"
echo
echo "  2. Update application .env file if needed:"
echo "     DB_NAME=$TARGET_DB"
echo
echo "  3. Verify application functionality"
echo
echo "=============================================="