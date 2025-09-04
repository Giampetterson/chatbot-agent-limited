#!/bin/bash
#
# LIGHTBOT Database Initialization Script
# 
# This script initializes the MySQL database for LIGHTBOT RENTRI360.it
# It creates the database, user, and applies the schema.
#
# Usage: ./init-database.sh [environment]
# Environment: development, staging, production (default: development)
#
# Author: Generated for LIGHTBOT project
# Date: 2025-09-04

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCHEMA_FILE="$SCRIPT_DIR/../schema/lightbot_schema.sql"
ENV="${1:-development}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
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

# Banner
echo "=============================================="
echo "  LIGHTBOT Database Initialization Script"
echo "=============================================="
echo "Environment: $ENV"
echo "Schema file: $SCHEMA_FILE"
echo "=============================================="
echo

# Check if schema file exists
if [ ! -f "$SCHEMA_FILE" ]; then
    log_error "Schema file not found: $SCHEMA_FILE"
    exit 1
fi

# Environment-specific configuration
case "$ENV" in
    "development")
        DB_NAME="lightbot_dev"
        DB_USER="lightbot_dev"
        DB_PASSWORD="dev_password_$(date +%s)"
        ;;
    "staging")
        DB_NAME="lightbot_staging"
        DB_USER="lightbot_staging"
        DB_PASSWORD="staging_password_$(openssl rand -hex 16)"
        ;;
    "production")
        DB_NAME="lightbot"
        DB_USER="lightbot_user"
        # Use existing password or generate secure one
        DB_PASSWORD="${LIGHTBOT_DB_PASSWORD:-$(openssl rand -hex 32)}"
        ;;
    *)
        log_error "Invalid environment: $ENV. Use: development, staging, production"
        exit 1
        ;;
esac

log_info "Database configuration:"
echo "  - Database: $DB_NAME"
echo "  - User: $DB_USER"
echo "  - Password: [HIDDEN]"
echo

# Check MySQL connection
log_info "Checking MySQL connection..."
if ! mysql -u root -p -e "SELECT 1;" >/dev/null 2>&1; then
    log_error "Cannot connect to MySQL as root. Please check your MySQL installation and credentials."
    exit 1
fi
log_success "MySQL connection verified"

# Check if database already exists
log_info "Checking if database exists..."
if mysql -u root -p -e "USE $DB_NAME;" 2>/dev/null; then
    log_warning "Database '$DB_NAME' already exists!"
    read -p "Do you want to drop and recreate it? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        log_info "Dropping existing database..."
        mysql -u root -p -e "DROP DATABASE IF EXISTS $DB_NAME;"
        log_success "Database dropped"
    else
        log_error "Database initialization cancelled"
        exit 1
    fi
fi

# Create database
log_info "Creating database '$DB_NAME'..."
mysql -u root -p << EOF
CREATE DATABASE $DB_NAME 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci
    COMMENT 'LIGHTBOT RENTRI360.it - AI Assistant Database';
EOF
log_success "Database '$DB_NAME' created"

# Create or update user
log_info "Setting up database user '$DB_USER'..."
mysql -u root -p << EOF
-- Create user (ignore if exists)
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';

-- Grant privileges
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';

-- Additional privileges for monitoring
GRANT SELECT ON information_schema.* TO '$DB_USER'@'localhost';

-- Flush privileges
FLUSH PRIVILEGES;
EOF
log_success "User '$DB_USER' configured with privileges"

# Apply schema
log_info "Applying database schema..."
# Modify schema file to use correct database name
sed "s/Database: lightbot/Database: $DB_NAME/g" "$SCHEMA_FILE" | \
mysql -u root -p "$DB_NAME"
log_success "Schema applied successfully"

# Test connection with new user
log_info "Testing connection with application user..."
if mysql -u "$DB_USER" -p"$DB_PASSWORD" -e "USE $DB_NAME; SHOW TABLES;" >/dev/null 2>&1; then
    log_success "Application user connection verified"
else
    log_error "Failed to connect with application user"
    exit 1
fi

# Generate .env configuration
ENV_FILE="$SCRIPT_DIR/../.env.$ENV"
log_info "Generating environment configuration: $ENV_FILE"

cat > "$ENV_FILE" << EOF
# LIGHTBOT Database Configuration - $ENV
# Generated: $(date)
# Environment: $ENV

# Database Configuration
DB_HOST=localhost
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASSWORD=$DB_PASSWORD

# Rate Limiting Configuration  
RATE_LIMIT_MAX_MESSAGES=999999
RATE_LIMIT_GRACE_PERIOD_MINUTES=1
RATE_LIMIT_MESSAGE="Servizio temporaneamente non disponibile. Riprova più tardi."

# Environment
ENVIRONMENT=$ENV
DEBUG=$( [ "$ENV" = "development" ] && echo "true" || echo "false" )

# Security (generate your own values)
ADMIN_SECRET=$(openssl rand -hex 32)

# AI APIs (add your keys)
OPENAI_API_KEY=sk-proj-your_openai_key_here
AI_API_URL=https://your-custom-ai-api.com/api/v1/chat/completions
AI_API_KEY=your_ai_api_key_here

# Telegram Bot (add your tokens)
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_BOT_USERNAME=YourBotUsername
EOF

log_success "Environment file created: $ENV_FILE"

# Verify installation
log_info "Verifying installation..."
TABLES=$(mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SHOW TABLES;" 2>/dev/null | wc -l)
if [ "$TABLES" -gt 1 ]; then  # Headers count as 1
    log_success "Installation verified: $((TABLES-1)) tables created"
else
    log_error "Installation verification failed: no tables found"
    exit 1
fi

# Final instructions
echo
echo "=============================================="
log_success "Database initialization completed!"
echo "=============================================="
echo
log_info "Next steps:"
echo "  1. Copy the generated environment file:"
echo "     cp $ENV_FILE /var/www/lightbot.rentri360.it/.env"
echo
echo "  2. Update API keys and tokens in the .env file"
echo
echo "  3. Test the application:"
echo "     curl -X POST https://your-domain.com/chat.php \\"
echo "       -H 'Content-Type: application/json' \\"
echo "       -H 'X-User-ID: fp_test_\$(date +%s)' \\"
echo "       -d '{\"content\":\"test message\"}'"
echo
echo "  4. Monitor logs:"
echo "     tail -f /var/www/lightbot.rentri360.it/logs/lightbot.log"
echo
log_warning "Important: Store the database credentials securely!"
echo "Database: $DB_NAME"
echo "User: $DB_USER"
echo "Password: [Check $ENV_FILE]"
echo
echo "=============================================="