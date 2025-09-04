#!/bin/bash
#
# LIGHTBOT Database Backup Script
#
# This script creates complete backups of the LIGHTBOT database
# including schema and data with timestamp and compression.
#
# Usage: ./backup-database.sh [database_name]
# Default database: lightbot
#
# Author: Generated for LIGHTBOT project
# Date: 2025-09-04

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$SCRIPT_DIR/../backups"
DB_NAME="${1:-lightbot}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="lightbot_backup_${TIMESTAMP}"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

# Create backup directory
mkdir -p "$BACKUP_DIR"

log_info "Starting database backup for '$DB_NAME'..."
log_info "Backup directory: $BACKUP_DIR"

# Create full backup (schema + data)
log_info "Creating full backup..."
mysqldump -u root -p \
    --single-transaction \
    --routines \
    --triggers \
    --complete-insert \
    --hex-blob \
    --add-drop-database \
    --databases "$DB_NAME" > "$BACKUP_DIR/${BACKUP_FILE}_full.sql"

# Create schema-only backup
log_info "Creating schema-only backup..."
mysqldump -u root -p \
    --no-data \
    --routines \
    --triggers \
    --single-transaction \
    "$DB_NAME" > "$BACKUP_DIR/${BACKUP_FILE}_schema.sql"

# Create data-only backup
log_info "Creating data-only backup..."
mysqldump -u root -p \
    --no-create-info \
    --complete-insert \
    --single-transaction \
    "$DB_NAME" > "$BACKUP_DIR/${BACKUP_FILE}_data.sql"

# Compress backups
log_info "Compressing backups..."
gzip "$BACKUP_DIR/${BACKUP_FILE}_full.sql"
gzip "$BACKUP_DIR/${BACKUP_FILE}_schema.sql"
gzip "$BACKUP_DIR/${BACKUP_FILE}_data.sql"

# Create backup info file
cat > "$BACKUP_DIR/${BACKUP_FILE}_info.txt" << EOF
LIGHTBOT Database Backup Information
====================================

Database: $DB_NAME
Backup Date: $(date)
Backup Files:
  - ${BACKUP_FILE}_full.sql.gz (Complete backup)
  - ${BACKUP_FILE}_schema.sql.gz (Schema only)
  - ${BACKUP_FILE}_data.sql.gz (Data only)

File Sizes:
$(ls -lh "$BACKUP_DIR"/${BACKUP_FILE}*.gz | awk '{print "  - "$9": "$5}')

MySQL Version: $(mysql --version)
Server: $(hostname)

Restore Instructions:
1. Full restore:
   gunzip < ${BACKUP_FILE}_full.sql.gz | mysql -u root -p

2. Schema only:
   gunzip < ${BACKUP_FILE}_schema.sql.gz | mysql -u root -p new_database_name

3. Data only (requires existing schema):
   gunzip < ${BACKUP_FILE}_data.sql.gz | mysql -u root -p existing_database_name
EOF

# Cleanup old backups (keep last 7 days)
log_info "Cleaning up old backups (keeping last 7 days)..."
find "$BACKUP_DIR" -name "lightbot_backup_*.gz" -mtime +7 -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "lightbot_backup_*_info.txt" -mtime +7 -delete 2>/dev/null || true

# Summary
log_success "Backup completed successfully!"
echo
echo "Backup files created:"
echo "  - Full backup: $BACKUP_DIR/${BACKUP_FILE}_full.sql.gz"
echo "  - Schema only: $BACKUP_DIR/${BACKUP_FILE}_schema.sql.gz"
echo "  - Data only: $BACKUP_DIR/${BACKUP_FILE}_data.sql.gz"
echo "  - Info file: $BACKUP_DIR/${BACKUP_FILE}_info.txt"
echo
echo "Total backup size: $(du -sh "$BACKUP_DIR" | cut -f1)"