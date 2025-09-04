# 🗄️ LIGHTBOT Database Setup Guide

**Complete setup and recovery instructions for LIGHTBOT RENTRI360.it database**

---

## 📋 Overview

This guide provides comprehensive instructions for setting up, backing up, and restoring the LIGHTBOT database. The database uses **MySQL/MariaDB** with **UTF8MB4** encoding and contains user rate limiting and activity logging functionality.

### Database Structure

```
lightbot/
├── user_limits         # User rate limiting and fingerprinting
├── activity_log        # System activity and error tracking
└── [future tables]     # Extensible for additional features
```

---

## 🚀 Quick Setup (New Installation)

### Prerequisites

- **MySQL 5.7+** or **MariaDB 10.3+**
- **PHP 8.4+** with mysqli/PDO extensions
- **Root access** to MySQL server
- **Git repository** cloned to server

### 1-Command Setup

```bash
cd /var/www/lightbot.rentri360.it/database/scripts
./init-database.sh production
```

This script will:
- ✅ Create database with proper charset
- ✅ Create application user with minimal privileges
- ✅ Apply complete schema
- ✅ Generate .env configuration file
- ✅ Verify installation

---

## 🔧 Manual Setup (Step by Step)

### Step 1: Create Database

```bash
mysql -u root -p
```

```sql
-- Create database with UTF8MB4 support
CREATE DATABASE lightbot 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci
    COMMENT 'LIGHTBOT RENTRI360.it - AI Assistant Database';

-- Create application user
CREATE USER 'lightbot_user'@'localhost' IDENTIFIED BY 'your_secure_password';

-- Grant necessary privileges
GRANT ALL PRIVILEGES ON lightbot.* TO 'lightbot_user'@'localhost';
GRANT SELECT ON information_schema.* TO 'lightbot_user'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;
```

### Step 2: Apply Schema

```bash
cd /var/www/lightbot.rentri360.it/database/schema
mysql -u root -p lightbot < lightbot_schema.sql
```

### Step 3: Verify Installation

```bash
mysql -u lightbot_user -p lightbot -e "SHOW TABLES;"
```

Expected output:
```
+-------------------+
| Tables_in_lightbot|
+-------------------+
| activity_log      |
| user_limits       |
+-------------------+
```

### Step 4: Configure Application

Create or update `.env` file in project root:

```bash
# Database Configuration
DB_HOST=localhost
DB_NAME=lightbot
DB_USER=lightbot_user
DB_PASSWORD=your_secure_password

# Rate Limiting
RATE_LIMIT_MAX_MESSAGES=999999
RATE_LIMIT_GRACE_PERIOD_MINUTES=1
```

---

## 📊 Database Schema Details

### Table: `user_limits`

**Purpose**: Rate limiting and user fingerprinting

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT AUTO_INCREMENT | Primary key |
| `user_id` | VARCHAR(128) | Unique fingerprint ID |
| `user_id_hash` | VARCHAR(32) | Shortened hash for display |
| `count` | INT DEFAULT 0 | Messages sent by user |
| `max_count` | INT DEFAULT 999999 | User-specific limit |
| `first_message` | TIMESTAMP | First message timestamp |
| `last_message` | TIMESTAMP | Last activity timestamp |
| `created` | TIMESTAMP | Record creation time |
| `is_blocked` | TINYINT(1) | Permanent block flag |
| `total_attempts` | INT DEFAULT 0 | Total attempts including blocked |
| `grace_period_start` | TIMESTAMP NULL | Grace period start time |
| `metadata` | LONGTEXT JSON | Additional user metadata |

**Indexes**:
- `PRIMARY KEY (id)`
- `UNIQUE KEY user_id (user_id)`
- `KEY idx_user_id (user_id)`
- `KEY idx_last_message (last_message)`
- `KEY idx_is_blocked (is_blocked)`

### Table: `activity_log`

**Purpose**: System activity tracking and debugging

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT AUTO_INCREMENT | Primary key |
| `timestamp` | TIMESTAMP | Activity timestamp |
| `user_id` | VARCHAR(128) | User fingerprint |
| `action` | VARCHAR(50) | Action performed |
| `component` | VARCHAR(30) | System component |
| `ip_address` | VARCHAR(45) | Client IP address |
| `processing_time_ms` | INT | Processing time in milliseconds |
| `error_message` | TEXT | Error details if applicable |

**Indexes**:
- `PRIMARY KEY (id)`
- `KEY idx_timestamp (timestamp)`
- `KEY idx_user_id (user_id)`
- `KEY idx_action (action)`

---

## 🔄 Environment-Specific Setup

### Development Environment

```bash
./init-database.sh development
```

Creates:
- Database: `lightbot_dev`
- User: `lightbot_dev` 
- Config: `.env.development`
- Debug mode enabled

### Staging Environment

```bash
./init-database.sh staging
```

Creates:
- Database: `lightbot_staging`
- User: `lightbot_staging`
- Config: `.env.staging`
- Production-like configuration

### Production Environment

```bash
./init-database.sh production
```

Creates:
- Database: `lightbot` (default)
- User: `lightbot_user`
- Config: `.env.production`
- Security hardening enabled

---

## 💾 Backup & Recovery

### Create Backup

```bash
cd /var/www/lightbot.rentri360.it/database/scripts
./backup-database.sh lightbot
```

Creates three backup types:
- **Full backup**: Schema + data
- **Schema only**: Structure without data
- **Data only**: Data without structure

### Restore from Backup

```bash
cd /var/www/lightbot.rentri360.it/database/scripts

# Restore full backup
./restore-database.sh ../backups/lightbot_backup_20250904_120000_full.sql.gz

# Restore to different database
./restore-database.sh backup_file.sql.gz lightbot_restored
```

### Manual Backup Commands

```bash
# Complete backup
mysqldump -u root -p --single-transaction --routines --triggers \
  --complete-insert --hex-blob --add-drop-database \
  --databases lightbot | gzip > lightbot_full_$(date +%Y%m%d).sql.gz

# Schema only
mysqldump -u root -p --no-data --routines --triggers \
  lightbot | gzip > lightbot_schema_$(date +%Y%m%d).sql.gz

# Data only  
mysqldump -u root -p --no-create-info --complete-insert \
  lightbot | gzip > lightbot_data_$(date +%Y%m%d).sql.gz
```

---

## 🔍 Monitoring & Maintenance

### Database Statistics Query

```sql
SELECT 
    'user_limits' as table_name,
    COUNT(*) as total_users,
    COUNT(CASE WHEN is_blocked = 1 THEN 1 END) as blocked_users,
    COUNT(CASE WHEN count >= max_count THEN 1 END) as users_at_limit,
    ROUND(AVG(count), 2) as avg_messages_per_user,
    MAX(count) as max_messages_single_user
FROM user_limits

UNION ALL

SELECT 
    'activity_log' as table_name,
    COUNT(*) as total_entries,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(CASE WHEN timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h_entries,
    COUNT(CASE WHEN error_message IS NOT NULL THEN 1 END) as error_entries,
    ROUND(AVG(processing_time_ms), 2) as avg_processing_time_ms
FROM activity_log;
```

### Table Sizes Query

```sql
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb,
    table_rows,
    engine,
    table_collation
FROM information_schema.tables 
WHERE table_schema = 'lightbot'
ORDER BY (data_length + index_length) DESC;
```

### Cleanup Old Data

```sql
-- Remove activity logs older than 30 days
DELETE FROM activity_log 
WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Optimize tables after cleanup
OPTIMIZE TABLE user_limits, activity_log;
```

---

## 🚨 Troubleshooting

### Common Issues

#### 1. Connection Failed
```bash
# Check MySQL service
systemctl status mysql

# Test connection
mysql -u lightbot_user -p lightbot -e "SELECT 1;"

# Check user privileges
mysql -u root -p -e "SHOW GRANTS FOR 'lightbot_user'@'localhost';"
```

#### 2. Character Encoding Issues
```sql
-- Check database charset
SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME 
FROM information_schema.SCHEMATA 
WHERE SCHEMA_NAME = 'lightbot';

-- Should return: utf8mb4, utf8mb4_unicode_ci
```

#### 3. Schema Mismatch
```bash
# Compare with reference schema
mysqldump -u root -p --no-data lightbot > current_schema.sql
diff current_schema.sql /var/www/lightbot.rentri360.it/database/schema/lightbot_schema.sql
```

#### 4. Performance Issues
```sql
-- Check slow queries
SHOW FULL PROCESSLIST;

-- Check table locks
SHOW OPEN TABLES WHERE In_use > 0;

-- Analyze table performance
ANALYZE TABLE user_limits, activity_log;
```

### Recovery Procedures

#### Complete Database Loss
```bash
# 1. Reinstall MySQL if needed
# 2. Restore from latest backup
cd /var/www/lightbot.rentri360.it/database/scripts
./restore-database.sh ../backups/latest_full_backup.sql.gz lightbot

# 3. Verify application connectivity
curl -X POST https://lightbot.rentri360.it/chat.php \
  -H "Content-Type: application/json" \
  -H "X-User-ID: fp_test_$(date +%s)" \
  -d '{"content":"test"}'
```

#### Corrupted Tables
```sql
-- Check table integrity
CHECK TABLE user_limits, activity_log;

-- Repair if needed
REPAIR TABLE user_limits, activity_log;

-- Optimize after repair
OPTIMIZE TABLE user_limits, activity_log;
```

---

## 📝 Migration Guide

### Upgrading from File-Based Storage

If migrating from the old JSON file system:

```bash
# 1. Backup existing data
cp /var/www/lightbot.rentri360.it/private/user_limits.json user_limits_backup.json

# 2. Setup new database
./init-database.sh production

# 3. Import old data (custom script needed)
# Note: Migration script not included - would need to be developed based on JSON structure
```

### Schema Updates

To add new columns or tables:

```sql
-- Example: Add new column
ALTER TABLE user_limits 
ADD COLUMN new_column VARCHAR(255) NULL COMMENT 'Description';

-- Example: Add new index  
CREATE INDEX idx_new_column ON user_limits(new_column);

-- Example: Create new table
CREATE TABLE new_feature (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(128) NOT NULL,
    data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES user_limits(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🔐 Security Considerations

### Database Security
- ✅ Use strong passwords (32+ characters)
- ✅ Limit user privileges to minimum required
- ✅ Enable SSL for remote connections
- ✅ Regular security updates
- ✅ Backup encryption in production

### Access Control
```sql
-- Create read-only user for monitoring
CREATE USER 'lightbot_readonly'@'localhost' IDENTIFIED BY 'readonly_password';
GRANT SELECT ON lightbot.* TO 'lightbot_readonly'@'localhost';

-- Create backup user with minimal privileges
CREATE USER 'lightbot_backup'@'localhost' IDENTIFIED BY 'backup_password';
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON lightbot.* TO 'lightbot_backup'@'localhost';
```

### Regular Maintenance
```bash
# Weekly maintenance script
mysql -u root -p << 'EOF'
USE lightbot;
ANALYZE TABLE user_limits, activity_log;
OPTIMIZE TABLE user_limits, activity_log;
DELETE FROM activity_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY);
EOF
```

---

## 📞 Support & Resources

### Quick Reference Commands

```bash
# Database status
systemctl status mysql

# Connect to database
mysql -u lightbot_user -p lightbot

# Check application logs
tail -f /var/www/lightbot.rentri360.it/logs/lightbot.log

# Test API connectivity
curl -X POST https://lightbot.rentri360.it/chat.php \
  -H "Content-Type: application/json" \
  -H "X-User-ID: fp_test_$(date +%s)" \
  -d '{"content":"test database connection"}'
```

### File Locations

```
database/
├── schema/
│   ├── lightbot_schema.sql           # Complete schema definition
│   └── lightbot_sample_data.sql      # Sample data for reference
├── scripts/
│   ├── init-database.sh              # Database initialization
│   ├── backup-database.sh            # Backup creation
│   └── restore-database.sh           # Restore from backup
├── docs/
│   └── DATABASE_SETUP.md             # This documentation
├── backups/                          # Automatic backup storage
└── logs/                             # Database operation logs
```

### Related Documentation
- **Main README**: `/var/www/lightbot.rentri360.it/README.md`
- **Developer Guide**: `/var/www/lightbot.rentri360.it/DEVELOPER_GUIDE.md`
- **Project Context**: `/var/www/lightbot.rentri360.it/CONTESTO_PROGETTO.md`

---

## 📊 Performance Tuning

### MySQL Configuration Recommendations

Add to `/etc/mysql/mysql.conf.d/lightbot.cnf`:

```ini
[mysqld]
# LIGHTBOT specific optimizations

# Character set
character_set_server = utf8mb4
collation_server = utf8mb4_unicode_ci

# Buffer pool (adjust based on available RAM)
innodb_buffer_pool_size = 256M
innodb_buffer_pool_instances = 2

# Query cache (for read-heavy workload)
query_cache_type = 1
query_cache_size = 64M

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2

# Connections
max_connections = 200
wait_timeout = 600

# InnoDB optimizations
innodb_log_file_size = 64M
innodb_file_per_table = 1
innodb_flush_log_at_trx_commit = 2
```

### Index Optimization

```sql
-- Monitor index usage
SELECT 
    s.table_name,
    s.index_name,
    s.column_name,
    s.cardinality,
    ROUND(((s.cardinality / t.table_rows) * 100), 2) AS selectivity
FROM 
    information_schema.statistics s
    JOIN information_schema.tables t ON s.table_schema = t.table_schema 
        AND s.table_name = t.table_name
WHERE 
    s.table_schema = 'lightbot'
ORDER BY 
    s.table_name, s.index_name;
```

---

*🗄️ Database setup completed with comprehensive backup and recovery procedures*

**Last updated**: 2025-09-04  
**Version**: 1.0  
**Compatibility**: MySQL 5.7+, MariaDB 10.3+, PHP 8.4+