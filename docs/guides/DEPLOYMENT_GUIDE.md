# USER ACTIVITY LOG SYSTEM - DEPLOYMENT GUIDE

## Table of Contents

1. [Deployment Overview](#deployment-overview)
2. [Pre-Deployment Checklist](#pre-deployment-checklist)
3. [Development Environment Setup](#development-environment-setup)
4. [Staging Environment Setup](#staging-environment-setup)
5. [Production Environment Setup](#production-environment-setup)
6. [Database Migrations](#database-migrations)
7. [Configuration Management](#configuration-management)
8. [Security Setup](#security-setup)
9. [Performance Optimization](#performance-optimization)
10. [Verification & Testing](#verification--testing)
11. [Deployment Procedures](#deployment-procedures)
12. [Rollback Procedures](#rollback-procedures)
13. [Post-Deployment](#post-deployment)
14. [Troubleshooting](#troubleshooting)

---

## 1. Deployment Overview

### 1.1 Deployment Environments

```
Development
├── Local Machine or Homestead
├── SQLite/Local SQL Server
├── Debugging Enabled
└── Performance: Not Required

Staging
├── AWS EC2 t3.medium (2 vCPU, 4GB RAM)
├── SQL Server 2019 RDS
├── Production-like Configuration
└── Performance Testing: Required

Production
├── AWS Auto Scaling Group (3+ instances)
├── Multi-AZ SQL Server 2019 RDS
├── Load Balancer (ALB)
├── CloudFront CDN
└── Performance: SLA 99.9% uptime
```

### 1.2 Key Requirements

| Component | Dev | Staging | Production |
|-----------|-----|---------|-----------|
| PHP | 8.0+ | 8.1+ | 8.2+ |
| Laravel | 9.x | 9.x | 9.x |
| SQL Server | 2016+ | 2019+ | 2019+ |
| Redis | Optional | Required | Required (Cluster) |
| Disk Space | 5GB | 50GB | 500GB |
| Memory | 2GB | 4GB | 16GB+ |
| CPU Cores | 1 | 2 | 4+ |

---

## 2. Pre-Deployment Checklist

### 2.1 Code Preparation

- [ ] All tests passing (200/200 tests)
- [ ] Code coverage > 85%
- [ ] No critical security issues
- [ ] CHANGELOG.md updated
- [ ] Version bumped in `config/app.php`
- [ ] No debug code left in production files
- [ ] All secrets removed from code
- [ ] Environment-specific configs in `.env` files

### 2.2 Database Preparation

- [ ] All migrations created and tested
- [ ] Database backup created
- [ ] Rollback plan documented
- [ ] Performance indexes verified
- [ ] Foreign key constraints validated
- [ ] Seeder data prepared (if needed)

### 2.3 Infrastructure Preparation

- [ ] Load balancer configured
- [ ] SSL/TLS certificates ready
- [ ] CDN configured
- [ ] DNS records prepared
- [ ] Monitoring/alerting configured
- [ ] Backup procedures tested
- [ ] Disaster recovery plan reviewed

### 2.4 Documentation Preparation

- [ ] Deployment guide reviewed
- [ ] Runbook created
- [ ] Team training completed
- [ ] Support procedures documented
- [ ] Incident response plan ready

### 2.5 Sign-Off

- [ ] Development lead approval
- [ ] QA lead approval
- [ ] Security team approval
- [ ] Operations lead approval
- [ ] Deployment window approved

---

## 3. Development Environment Setup

### 3.1 Initial Setup

**Prerequisites**:
- PHP 8.0+
- Composer 2.0+
- Node.js 14+ (for npm)
- Git
- Docker (optional)
- Laravel Homestead (optional)

**Step 1: Clone Repository**:
```bash
cd ~/projects
git clone https://github.com/edms/user-activity-log.git
cd user-activity-log
git checkout develop
```

**Step 2: Install Dependencies**:
```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Generate application key
php artisan key:generate
```

**Step 3: Database Setup**:
```bash
# Create database
php artisan db:create

# Run migrations
php artisan migrate --database=sqlsrv

# Seed data (optional)
php artisan db:seed --database=sqlsrv
```

**Step 4: Build Assets**:
```bash
# Development build
npm run dev

# Watch for changes
npm run watch

# Production build (when ready)
npm run prod
```

### 3.2 Environment Configuration

**Create `.env` file**:
```bash
cp .env.example .env
```

**Configure `.env`**:
```env
APP_NAME="User Activity Log"
APP_ENV=local
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=sqlsrv
DB_HOST=localhost
DB_PORT=1433
DB_DATABASE=edms_local
DB_USERNAME=sa
DB_PASSWORD=Your_Password_123

# Cache Configuration
CACHE_DRIVER=file
CACHE_PREFIX=dev_cache_

# Session Configuration
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Queue Configuration
QUEUE_CONNECTION=sync

# Mail Configuration (for testing)
MAIL_MAILER=log
MAIL_FROM_ADDRESS="dev@edms.local"
MAIL_FROM_NAME="EDMS Local"
```

### 3.3 Verify Development Setup

```bash
# Test artisan commands work
php artisan list

# Test database connection
php artisan tinker

# Test routes
php artisan route:list | grep api/activity

# Run tests
php artisan test

# Start development server
php artisan serve

# Access application
# http://localhost:8000
```

---

## 4. Staging Environment Setup

### 4.1 AWS EC2 Instance Setup

**Launch EC2 Instance**:
```bash
# Instance type: t3.medium (2 vCPU, 4GB RAM)
# OS: Ubuntu 20.04 LTS
# Security group: Custom (HTTP, HTTPS, SSH)
# Storage: 50GB gp3 EBS volume
```

**Initial SSH Connection**:
```bash
ssh -i key-pair.pem ubuntu@instance-ip-address

# Update system
sudo apt update
sudo apt upgrade -y

# Install PHP and dependencies
sudo apt install -y php8.1 \
  php8.1-fpm \
  php8.1-cli \
  php8.1-common \
  php8.1-curl \
  php8.1-mbstring \
  php8.1-pdo \
  php8.1-sqlsrv \
  php8.1-pdo-sqlsrv \
  php8.1-redis \
  php8.1-opcache \
  composer \
  nginx \
  git

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_16.x | sudo -E bash -
sudo apt install -y nodejs
```

### 4.2 RDS SQL Server Setup

**Create RDS Instance**:
```bash
# Engine: SQL Server 2019 Express
# Instance class: db.t3.small
# Storage: 20GB gp2
# Multi-AZ: No (for staging)
# Public accessibility: No
# Database name: edms_staging
```

**Security Group**:
- Inbound: Port 1433 from EC2 instance security group
- Outbound: All ports to anywhere

### 4.3 Nginx Configuration

**Create Nginx config** `/etc/nginx/sites-available/edms-staging`:
```nginx
server {
    listen 80;
    server_name staging.edms.local;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name staging.edms.local;
    
    # SSL Certificates
    ssl_certificate /etc/ssl/certs/staging.crt;
    ssl_certificate_key /etc/ssl/private/staging.key;
    
    # Document root
    root /var/www/edms/public;
    index index.php;
    
    # Charset
    charset utf-8;
    
    # Logging
    access_log /var/log/nginx/edms-staging-access.log;
    error_log /var/log/nginx/edms-staging-error.log;
    
    # Performance
    client_max_body_size 100M;
    keepalive_timeout 65;
    gzip on;
    gzip_types text/plain text/css text/xml text/javascript 
               application/x-javascript application/xml+rss;
    
    # Rewrite rules
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP-FPM
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # No direct access to hidden files
    location ~ /\. {
        deny all;
    }
}
```

**Enable Site**:
```bash
sudo ln -s /etc/nginx/sites-available/edms-staging /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx
```

### 4.4 Application Deployment

**Clone Application**:
```bash
# As ubuntu user
cd /var/www
sudo git clone https://github.com/edms/user-activity-log.git edms
sudo chown -R ubuntu:www-data edms
cd edms
```

**Install Dependencies**:
```bash
composer install --optimize-autoloader --no-dev
npm ci
npm run prod
php artisan optimize
```

**Configure Environment**:
```bash
# Create .env for staging
cp .env.staging .env

# Edit configuration
nano .env

# Generate key
php artisan key:generate
```

**Set Permissions**:
```bash
# Cache and storage directories
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Log directory
sudo chown -R www-data:www-data storage/logs
sudo chmod -R 775 storage/logs
```

### 4.5 Redis Setup

**Install and Configure Redis**:
```bash
sudo apt install -y redis-server

# Configure
sudo nano /etc/redis/redis.conf

# Set maxmemory
maxmemory 2gb
maxmemory-policy allkeys-lru

# Start Redis
sudo systemctl restart redis-server
sudo systemctl enable redis-server

# Test connection
redis-cli ping
```

### 4.6 Database Migrations

```bash
# Run migrations
php artisan migrate --database=sqlsrv --force

# Verify tables created
php artisan tinker
# > \DB::select('SELECT name FROM sys.tables')

# Run seeders (if needed)
php artisan db:seed --database=sqlsrv
```

---

## 5. Production Environment Setup

### 5.1 AWS Architecture

**Components**:
```
Internet → Route53 (DNS)
    ↓
CloudFront (CDN)
    ↓
Application Load Balancer
    ├→ EC2 Instance 1 (Web + API)
    ├→ EC2 Instance 2 (Web + API)
    └→ EC2 Instance 3 (Web + API)
    
Shared Resources:
├→ RDS SQL Server 2019 (Multi-AZ)
├→ ElastiCache Redis Cluster
├→ S3 (File Storage)
├→ CloudWatch (Monitoring)
└→ SNS/SQS (Notifications)
```

### 5.2 EC2 Instances

**Launch Configuration**:
```bash
# Instance type: t3.large (2 vCPU, 8GB RAM)
# Number of instances: 3 (one per AZ)
# OS: Ubuntu 20.04 LTS
# IAM Role: EDMS-App-Role
# Security group: EDMS-App-SG
# Storage: 100GB gp3 EBS volume
# Monitoring: CloudWatch detailed monitoring
```

**Launch Template**:
```bash
# User data script to configure each instance automatically
#!/bin/bash
set -e

# Update system
apt update && apt upgrade -y

# Install dependencies
apt install -y php8.1-fpm php8.1-cli php8.1-common \
  php8.1-curl php8.1-mbstring php8.1-pdo \
  php8.1-sqlsrv php8.1-pdo-sqlsrv php8.1-redis \
  php8.1-opcache composer nginx git awscli

# Create application directory
mkdir -p /var/www/edms
cd /var/www/edms

# Clone from git (using deployment key)
git clone --branch main https://github.com/edms/user-activity-log.git .

# Install dependencies
composer install --optimize-autoloader --no-dev
npm ci && npm run prod

# Copy .env from S3 (secrets stored securely)
aws s3 cp s3://edms-secrets/prod-.env .env

# Set permissions
chown -R www-data:www-data /var/www/edms
chmod -R 775 storage bootstrap/cache

# Generate optimized class loader
php artisan optimize

# Verify deployment
php artisan route:list | head -10
```

### 5.3 RDS SQL Server Setup

**Create Multi-AZ Cluster**:
```bash
# Engine: SQL Server 2019 Enterprise
# Instance class: db.r5.2xlarge (8 vCPU, 64GB RAM)
# Multi-AZ: Yes (automatic failover)
# Storage: 500GB io1 with 3000 IOPS
# Backup: 35-day retention
# Encryption: Enabled at rest
# Performance Insights: Enabled
```

**Database Configuration**:
```sql
-- Create database
CREATE DATABASE [edms_production]
ON PRIMARY (
    NAME = 'edms_production',
    FILENAME = 'S:\Data\edms_production.mdf',
    SIZE = 5GB,
    FILEGROWTH = 1GB
)
LOG ON (
    NAME = 'edms_production_log',
    FILENAME = 'L:\Logs\edms_production_log.ldf',
    SIZE = 2GB,
    FILEGROWTH = 500MB
)
WITH ENCRYPTION = ON;
```

### 5.4 ElastiCache Redis Cluster

**Create Cluster**:
```bash
# Cluster mode: Enabled (for horizontal scaling)
# Engine: Redis 6.x
# Node type: cache.r6g.xlarge
# Number of shards: 3
# Replicas per shard: 1 (automatic failover)
# Subnet group: EDMS-Cache-Subnet-Group
# Security group: EDMS-Cache-SG
# Encryption: In-transit and at-rest
# Automatic failover: Enabled
```

**Configuration**:
```
maxmemory-policy: allkeys-lru (evict least used keys)
timeout: 0 (no client timeout)
tcp-keepalive: 300
```

### 5.5 Load Balancer Setup

**Application Load Balancer**:
```bash
# Type: Application Load Balancer
# Scheme: Internet-facing
# IP address type: IPv4
# Subnets: 3 (one per AZ)
# Security groups: EDMS-ALB-SG
```

**Target Groups**:
```bash
# Create target group for app instances
# Protocol: HTTP
# Port: 80
# VPC: EDMS-VPC
# Health check path: /health
# Health check interval: 30 seconds
# Healthy threshold: 2
# Unhealthy threshold: 3
```

**Listeners**:
```
Listener 1:
- Port: 80
- Protocol: HTTP
- Action: Redirect to HTTPS

Listener 2:
- Port: 443
- Protocol: HTTPS
- Certificate: AWS Certificate Manager cert
- Action: Forward to target group
```

### 5.6 CloudFront Distribution

**Create Distribution**:
```bash
# Origin: ALB DNS name
# Protocol: HTTPS only
# Cache behavior:
#   - Default TTL: 3600 seconds
#   - Max TTL: 86400 seconds
#   - Compress: Yes
# Viewer protocol policy: Redirect HTTP to HTTPS
# Cache policy: CachingOptimized
# Origin request policy: AllViewerExceptHostHeader
```

**Cache Invalidation**:
```bash
# Invalidate paths after deployment
aws cloudfront create-invalidation \
  --distribution-id E1234ABCD \
  --paths "/*"
```

---

## 6. Database Migrations

### 6.1 Pre-Migration Checklist

- [ ] Database backup created
- [ ] Backup verified (tested restore)
- [ ] Maintenance window scheduled
- [ ] Rollback plan documented
- [ ] Team notified
- [ ] Downtime window published

### 6.2 Running Migrations

**Safe Migration**:
```bash
# Connect to production server
ssh -i prod-key.pem ubuntu@prod-instance

cd /var/www/edms

# Check migration status
php artisan migrate:status --database=sqlsrv

# Run pending migrations
php artisan migrate --database=sqlsrv --force

# Verify migrations completed
php artisan migrate:status --database=sqlsrv | grep Yes
```

**Large Table Migrations**:
```bash
# For tables with millions of rows, use online approach
# SQL Server Enterprise allows online index creation

php artisan migrate --database=sqlsrv --force --step

# Check progress
php artisan tinker
# > DB::select('SELECT * FROM sys.dm_exec_requests')
```

### 6.3 Post-Migration Verification

```bash
# Verify table structure
php artisan tinker
DB::select("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'dbo'")

# Verify indexes
DB::select("SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID('audit_logs')")

# Verify constraints
DB::select("SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
           WHERE TABLE_NAME = 'audit_logs'")

# Check data integrity
DB::select("SELECT COUNT(*) as total FROM audit_logs")
```

### 6.4 Migration Rollback (if needed)

```bash
# Rollback last migration batch
php artisan migrate:rollback --database=sqlsrv

# Rollback specific migration
php artisan migrate:rollback --database=sqlsrv --step=3

# Restore from backup (if rollback insufficient)
# Contact DBA for point-in-time recovery
```

---

## 7. Configuration Management

### 7.1 Environment Variables

**Production `.env` Template**:
```env
# Application
APP_NAME="User Activity Log"
APP_ENV=production
APP_KEY=base64:PRODUCTION_KEY_HERE
APP_DEBUG=false
APP_URL=https://api.edms.local

# Database
DB_CONNECTION=sqlsrv
DB_HOST=edms-prod.abcdef.us-east-1.rds.amazonaws.com
DB_PORT=1433
DB_DATABASE=edms_production
DB_USERNAME=edms_admin
DB_PASSWORD=${DB_PASSWORD}

# Cache
CACHE_DRIVER=redis
CACHE_PREFIX=prod_cache_
REDIS_HOST=edms-redis.abc123.ng.0001.use1.cache.amazonaws.com
REDIS_PORT=6379
REDIS_PASSWORD=${REDIS_PASSWORD}

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_DOMAIN=.edms.local

# Queue
QUEUE_CONNECTION=redis
QUEUE_WAIT=5

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=${MAIL_PASSWORD}
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@edms.local
MAIL_FROM_NAME="EDMS"

# AWS S3
AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID}
AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY}
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=edms-reports-prod

# Monitoring
APP_LOG=stack
LOG_CHANNEL=stack
LOG_LEVEL=notice

# Security
SANCTUM_STATEFUL_DOMAINS=edms.local,.edms.local
SESSION_SECURE_COOKIES=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

### 7.2 Secrets Management

**Use AWS Secrets Manager**:
```bash
# Store secrets securely
aws secretsmanager create-secret \
  --name edms/prod/database/password \
  --secret-string "YourPassword123"

aws secretsmanager create-secret \
  --name edms/prod/redis/password \
  --secret-string "YourRedisPassword"

# Retrieve secrets in application
$dbPassword = retrieveSecret('edms/prod/database/password');
```

**Retrieve in Application**:
```php
// Create custom configuration provider
$secretsManager = new \Aws\SecretsManager\SecretsManagerClient([
    'region' => 'us-east-1'
]);

$result = $secretsManager->getSecretValue([
    'SecretId' => 'edms/prod/database/password'
]);

$password = $result['SecretString'];
```

---

## 8. Security Setup

### 8.1 SSL/TLS Certificates

**AWS Certificate Manager**:
```bash
# Request certificate for main domain and subdomains
aws acm request-certificate \
  --domain-name edms.local \
  --subject-alternative-names api.edms.local staging.edms.local \
  --validation-method DNS \
  --region us-east-1
```

**Nginx SSL Configuration**:
```nginx
# Use strong SSL settings
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256';
ssl_prefer_server_ciphers on;
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 10m;

# HSTS header
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

### 8.2 Security Headers

```nginx
# In Nginx configuration
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
```

### 8.3 Firewall & Security Groups

**Application Security Group**:
```
Inbound Rules:
- HTTP (80) from ALB SG
- HTTPS (443) from ALB SG
- SSH (22) from Admin CIDR block

Outbound Rules:
- All traffic to RDS SG (port 1433)
- All traffic to Redis SG (port 6379)
- All traffic to internet (443)
```

**Database Security Group**:
```
Inbound Rules:
- SQL Server (1433) from App SG

Outbound Rules:
- None (internal only)
```

### 8.4 IP Whitelisting

**Admin Access**:
```
SSH (22):
- Office IP: 203.0.113.0/32
- VPN Gateway: 198.51.100.0/24

API Requests:
- Internal: 10.0.0.0/8
- Partner A: 192.0.2.0/24
- Partner B: 198.51.100.0/25
```

---

## 9. Performance Optimization

### 9.1 PHP Configuration

**FPM Settings** in `/etc/php/8.1/fpm/pool.d/www.conf`:
```ini
pm = dynamic
pm.max_children = 100
pm.start_servers = 20
pm.min_spare_servers = 10
pm.max_spare_servers = 30
pm.process_idle_timeout = 10s

# Memory limits
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 100M
post_max_size = 100M
```

**OPcache Configuration** in `/etc/php/8.1/mods-available/opcache.ini`:
```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=512
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

### 9.2 Database Query Optimization

**Index Creation**:
```sql
-- Create composite indexes for common queries
CREATE NONCLUSTERED INDEX idx_audit_user_date 
ON audit_logs (user_id, created_at DESC)
INCLUDE (action, resource_type, resource_id);

CREATE NONCLUSTERED INDEX idx_activity_status_lastseen
ON user_activity_logs (status, last_seen_at DESC)
INCLUDE (user_id, device, browser);

-- Update index statistics
UPDATE STATISTICS audit_logs;
UPDATE STATISTICS user_activity_logs;
```

**Query Plan Analysis**:
```sql
-- Enable query execution plans
SET STATISTICS IO ON;
SET STATISTICS TIME ON;

-- Run query to analyze
SELECT * FROM audit_logs 
WHERE user_id = 5 AND created_at > DATEADD(DAY, -30, GETDATE());

-- Review execution plan
```

### 9.3 Caching Strategy

**Application-Level Caching**:
```php
// Cache analytics results for 1 hour
Cache::remember('analytics_30day', 3600, function () {
    return $this->calculateAnalytics(30);
});

// Cache user permissions
Cache::remember("user_permissions_{$userId}", 3600, function () use ($userId) {
    return User::find($userId)->permissions;
});

// Cache audit statistics (6 hours)
Cache::remember('audit_stats', 21600, function () {
    return AuditLog::getStatistics();
});
```

**Cache Invalidation**:
```php
// Invalidate on data change
Cache::forget('analytics_30day');
Cache::forget("user_permissions_{$userId}");

// Implement cache tags for related items
Cache::tags(['reports'])->put('report_list', $data, 3600);
Cache::tags(['reports'])->flush(); // Flush all report cache
```

---

## 10. Verification & Testing

### 10.1 Smoke Tests

**Basic Connectivity**:
```bash
# Test API endpoints
curl -H "Authorization: Bearer $TOKEN" \
  https://api.edms.local/api/activity-analytics/sessions \
  -d '{"period_days": 1}' \
  -H "Content-Type: application/json"

# Expected: 200 OK with data

# Test health endpoint
curl https://api.edms.local/health

# Expected: 200 OK
```

**Database Connectivity**:
```bash
ssh ubuntu@prod-instance

php artisan tinker

# Test queries
DB::select("SELECT COUNT(*) as count FROM audit_logs")
DB::select("SELECT TOP 1 * FROM user_activity_logs")

# Check indexes
DB::select("SELECT * FROM sys.indexes WHERE object_id = OBJECT_ID('audit_logs')")
```

### 10.2 Performance Tests

**Load Testing**:
```bash
# Using Apache Bench
ab -n 1000 -c 50 https://api.edms.local/health

# Using wrk
wrk -t4 -c100 -d30s --script post.lua https://api.edms.local/api/activity-analytics/sessions

# Expected: < 500ms response time, 99% success rate
```

**Database Performance**:
```sql
-- Verify query performance
DBCC FREEPROCCACHE;
SET STATISTICS TIME ON;

SELECT TOP 100 * FROM audit_logs 
WHERE user_id IN (1, 2, 3, 4, 5)
AND created_at > DATEADD(DAY, -30, GETDATE())
ORDER BY created_at DESC;

-- Should complete in < 100ms
```

### 10.3 Functional Testing

**Run Test Suite**:
```bash
# Run all tests
php artisan test

# Expected: 200/200 passing

# Run integration tests only
php artisan test --filter Integration

# Run feature tests only
php artisan test --filter Feature

# Check code coverage
php artisan test --coverage --coverage-html=coverage

# Expected: > 85% coverage
```

---

## 11. Deployment Procedures

### 11.1 Blue-Green Deployment

**Step 1: Prepare Blue Environment** (current production)
```bash
# Already running, serve traffic

# Note current version
curl https://api.edms.local/version
# Returns: { "version": "1.0.0" }
```

**Step 2: Deploy to Green Environment**
```bash
# SSH to new EC2 instance (green)
ssh -i prod-key.pem ubuntu@green-instance

# Deploy new version
cd /var/www/edms
git pull origin main
git checkout v1.1.0

# Install dependencies
composer install --optimize-autoloader --no-dev
npm ci && npm run prod

# Update configuration
php artisan optimize:clear
php artisan optimize

# Run database migrations
php artisan migrate --database=sqlsrv --force

# Verify application
php artisan route:list | grep api/activity
curl http://localhost/health
```

**Step 3: Test Green Environment**
```bash
# Direct traffic to green via host file
sudo nano /etc/hosts
# Add: 127.0.0.1 green.edms.local

# Run full test suite
php artisan test

# Run performance tests
ab -n 100 -c 10 http://green.edms.local/health
```

**Step 4: Switch Traffic**
```bash
# Update load balancer to point to green instances
aws elbv2 register-targets \
  --target-group-arn arn:aws:elasticloadbalancing:... \
  --targets Id=i-green1 Id=i-green2 Id=i-green3

# Monitor metrics
watch 'aws cloudwatch get-metric-statistics ...'

# After 5 minutes of monitoring, declare green as production
```

**Step 5: Keep Blue as Rollback**
```bash
# Keep running for quick rollback
# If issues arise, switch back immediately
aws elbv2 register-targets \
  --target-group-arn arn:aws:elasticloadbalancing:... \
  --targets Id=i-blue1 Id=i-blue2 Id=i-blue3
```

### 11.2 Canary Deployment

**Phase 1: Route 10% of traffic**
```bash
# Update load balancer weighted routing
aws elbv2 modify-target-group \
  --target-group-arn arn:aws:elasticloadbalancing:...

# Send 10% to new version, 90% to current
# Monitor error rates and performance
```

**Phase 2: Route 50% of traffic**
```bash
# After 30 minutes with no issues, increase to 50%
# Continue monitoring
```

**Phase 3: Route 100% of traffic**
```bash
# After another 30 minutes, route all traffic to new version
```

---

## 12. Rollback Procedures

### 12.1 Immediate Rollback

**Switch traffic back to previous version**:
```bash
# If deployment is blue-green:
aws elbv2 register-targets \
  --target-group-arn arn:aws:elasticloadbalancing:... \
  --targets Id=i-blue1 Id=i-blue2 Id=i-blue3

# If using canary, reduce traffic to new version to 0%
aws elbv2 modify-target-group --weight 0

# Verify traffic restored
curl https://api.edms.local/version
```

### 12.2 Database Rollback

**Rollback migrations**:
```bash
# If migration caused issues
php artisan migrate:rollback --database=sqlsrv --force

# Verify
php artisan migrate:status --database=sqlsrv
```

**Restore from backup** (point-in-time recovery):
```bash
# Use RDS automated backup
aws rds restore-db-instance-from-db-snapshot \
  --db-instance-identifier edms-prod-restore \
  --db-snapshot-identifier edms-prod-backup-2025-01-15-09-00

# Test on restore instance first
# Then swap with production
```

### 12.3 Communication

**Notify Stakeholders**:
1. Slack notification: "#operations" channel
2. Email to management with status
3. Update status page
4. Post-incident review after 24 hours

---

## 13. Post-Deployment

### 13.1 Verification Steps

- [ ] All endpoints responding (< 500ms)
- [ ] Database queries performing well
- [ ] Error logs clean (no critical errors)
- [ ] Cache hit rate > 80%
- [ ] No security vulnerabilities detected
- [ ] All scheduled jobs running
- [ ] Email notifications working
- [ ] Monitoring/alerting active

### 13.2 Documentation

- [ ] Deployment log updated
- [ ] Known issues documented
- [ ] Configuration changes recorded
- [ ] Performance metrics captured
- [ ] Release notes published

### 13.3 Handover

- [ ] Operations team briefed
- [ ] Support team trained
- [ ] Documentation available
- [ ] Escalation procedures clear

---

## 14. Troubleshooting

### 14.1 Common Deployment Issues

**Issue: Nginx returns 502 Bad Gateway**
```bash
# Check PHP-FPM status
sudo systemctl status php8.1-fpm

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm

# Check Nginx configuration
sudo nginx -t

# Review error logs
tail -f /var/log/nginx/error.log
```

**Issue: Database connection timeout**
```bash
# Check security group rules
aws ec2 describe-security-groups --group-ids sg-xxxxxxxxx

# Test connection directly
sqlcmd -S edms-prod.abc.us-east-1.rds.amazonaws.com \
  -U edms_admin -P 'password' -d edms_production

# Check RDS status
aws rds describe-db-instances --db-instance-identifier edms-prod
```

**Issue: Redis connection refused**
```bash
# Test Redis connection
redis-cli -h edms-redis.abc.ng.0001.use1.cache.amazonaws.com ping

# Check ElastiCache cluster status
aws elasticache describe-clusters --cache-cluster-id edms-redis

# Check security group
aws ec2 describe-security-groups --group-ids sg-redis-sg
```

### 14.2 Performance Issues

**Slow API responses**:
```bash
# Check database slow query log
SELECT * FROM sys.dm_exec_query_stats 
ORDER BY total_elapsed_time DESC

# Check Redis memory usage
redis-cli info memory

# Check application logs
tail -f storage/logs/laravel.log

# Check server resources
top
free -h
df -h
```

### 14.3 Monitoring & Alerting

**CloudWatch Metrics**:
```bash
# Monitor key metrics
aws cloudwatch get-metric-statistics \
  --namespace AWS/ApplicationELB \
  --metric-name TargetResponseTime \
  --start-time 2025-01-15T00:00:00Z \
  --end-time 2025-01-15T12:00:00Z \
  --period 300 \
  --statistics Average

# Set up alarms
aws cloudwatch put-metric-alarm \
  --alarm-name edms-response-time \
  --metric-name TargetResponseTime \
  --threshold 1000 \
  --comparison-operator GreaterThanThreshold
```

---

## Deployment Checklist

```
PRE-DEPLOYMENT:
☐ Code freeze 24 hours
☐ All tests passing (200/200)
☐ Code review completed
☐ Database backup verified
☐ Team notified
☐ Rollback plan reviewed
☐ Monitoring configured

DEPLOYMENT:
☐ Deploy to blue/green environment
☐ Run migrations
☐ Verify application startup
☐ Run smoke tests
☐ Monitor error logs
☐ Switch traffic (10% → 50% → 100%)

POST-DEPLOYMENT:
☐ Monitor for 30 minutes
☐ Verify all endpoints
☐ Check database performance
☐ Review error logs
☐ Confirm email notifications
☐ Communicate completion
☐ Schedule post-incident review
```

---

*Generated: November 10, 2025*
*Version: 1.0*
*Status: Complete & Production-Ready*
