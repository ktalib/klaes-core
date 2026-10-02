# USER ACTIVITY LOG SYSTEM - OPERATIONS MANUAL

## Table of Contents

1. [Operations Overview](#operations-overview)
2. [Daily Operations](#daily-operations)
3. [Monitoring & Alerts](#monitoring--alerts)
4. [Backup & Recovery](#backup--recovery)
5. [Incident Response](#incident-response)
6. [Database Maintenance](#database-maintenance)
7. [Cache Management](#cache-management)
8. [Log Management](#log-management)
9. [Performance Monitoring](#performance-monitoring)
10. [User Management](#user-management)
11. [Security Operations](#security-operations)
12. [Escalation Procedures](#escalation-procedures)
13. [Runbooks](#runbooks)
14. [SLA & Support](#sla--support)

---

## 1. Operations Overview

### 1.1 Critical System Components

| Component | Status Check | SLA | Owner |
|-----------|--------------|-----|-------|
| Application Servers | /health endpoint | 99.9% | DevOps |
| Database (RDS) | CloudWatch metrics | 99.99% | DBA |
| Redis Cache | Connection test | 99.95% | DevOps |
| Load Balancer | Target health | 99.99% | DevOps |
| CDN (CloudFront) | Cache hit ratio | 99.9% | DevOps |

### 1.2 Operational Shifts

```
Shift 1: 06:00 - 14:00 (Primary)
Shift 2: 14:00 - 22:00 (Secondary)
Shift 3: 22:00 - 06:00 (Night Watch)

On-Call Rotation:
- Week 1: Developer A
- Week 2: Developer B
- Week 3: DevOps Lead
- Week 4: Database Admin
```

### 1.3 Contact Information

```
Primary Contact: DevOps Lead
- Slack: @devops-lead
- Phone: +1-xxx-xxx-xxxx
- Email: devops@edms.local

Secondary Contact: On-Call Engineer
- Slack: @on-call
- PagerDuty: edms-team

Database Admin: DBA Team
- Slack: @dba-team
- Email: dba@edms.local

Network Admin: Infrastructure Team
- Slack: @infrastructure
- Email: infrastructure@edms.local
```

---

## 2. Daily Operations

### 2.1 Start-of-Shift Checklist

**Time: Each shift change (06:00, 14:00, 22:00)**

```bash
# 1. Check Dashboard Status
# - Log into CloudWatch
# - Review alerts from previous shift
# - Check error rate < 0.1%

# 2. Verify Application Health
curl -H "Authorization: Bearer $TOKEN" \
  https://api.edms.local/health

# Expected: 200 OK with {"status": "healthy"}

# 3. Check Database Status
# - Log into AWS RDS console
# - Verify "Available" status
# - Check CPU < 30%, disk < 80%

# 4. Check Redis Cluster
# - Log into ElastiCache console
# - Verify all nodes in "available" state
# - Check memory usage < 80%

# 5. Review Error Logs
tail -n 100 /var/log/nginx/edms-prod-error.log
tail -n 100 storage/logs/laravel.log

# 6. Check Backup Status
# - Verify automated backup completed
# - Check backup is > 80% of database size

# 7. Review Handover Notes
cat /var/operations/shift-handover.txt

# 8. Update Team Channel
# Post in #operations Slack channel
```

### 2.2 Hourly Monitoring

**Time: Every hour during operational hours**

```bash
#!/bin/bash
# File: /usr/local/bin/hourly-health-check.sh

TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
LOG_FILE="/var/log/hourly-health-check.log"

# Check API response time
RESPONSE_TIME=$(curl -s -o /dev/null -w '%{time_total}' \
  https://api.edms.local/health)

echo "[$TIMESTAMP] API Response: ${RESPONSE_TIME}s" >> $LOG_FILE

# Check database connections
DB_CONNECTIONS=$(aws cloudwatch get-metric-statistics \
  --namespace AWS/RDS \
  --metric-name DatabaseConnections \
  --start-time $(date -u -d '5 minutes ago' +%Y-%m-%dT%H:%M:%S) \
  --end-time $(date -u +%Y-%m-%dT%H:%M:%S) \
  --period 300 \
  --statistics Average \
  --query 'Datapoints[0].Average' \
  --output text)

echo "[$TIMESTAMP] DB Connections: $DB_CONNECTIONS" >> $LOG_FILE

# Check Redis memory
REDIS_MEMORY=$(redis-cli -h $REDIS_HOST INFO memory | grep used_memory_human)
echo "[$TIMESTAMP] Redis: $REDIS_MEMORY" >> $LOG_FILE

# If any metric is abnormal, send alert
if (( $(echo "$RESPONSE_TIME > 1.0" | bc -l) )); then
  echo "ALERT: Slow response time at $TIMESTAMP" >> $LOG_FILE
  # Send to monitoring system
fi
```

### 2.3 Daily Reports

**Time: 09:00 and 17:00**

```bash
# Daily activity report
php artisan activity:daily-report

# Generates:
# - Number of active users
# - Total sessions
# - Top resources accessed
# - Error count
# - Performance metrics

# Email report to operations team
# File: /storage/reports/daily-report-$(date +%Y-%m-%d).txt
```

### 2.4 End-of-Shift Checklist

**Time: 2 hours before shift change**

```bash
# 1. Review Shift Activity Log
tail -n 50 /var/log/operations-shift.log

# 2. Check for Any Active Incidents
ps aux | grep incident
# Should be empty if all incidents resolved

# 3. Verify Backups Completed
ls -lah /backups/
# Should have today's backup

# 4. Document Any Issues
cat >> /var/operations/shift-handover.txt << EOF
[$(date)] Shift Handover Notes:
- Incident XYZ resolved at HH:MM
- Performance optimization applied to endpoint /api/reports
- Database maintenance: reindexed table audit_logs
- Next shift action: Monitor query performance
EOF

# 5. Notify Next Shift
# Send Slack message with handover notes
# Update status dashboard

# 6. Archive Shift Logs
tar -czf /var/log/archives/shift-logs-$(date +%Y-%m-%d-%H).tar.gz \
  /var/log/*-shift.log
```

---

## 3. Monitoring & Alerts

### 3.1 CloudWatch Dashboard

**Access Dashboard**:
```
AWS Console → CloudWatch → Dashboards → EDMS-Production
```

**Key Metrics to Monitor**:

| Metric | Threshold | Warning | Critical |
|--------|-----------|---------|----------|
| API Response Time | < 500ms | > 700ms | > 1500ms |
| Error Rate | < 0.1% | > 0.5% | > 2% |
| CPU Utilization | < 50% | > 70% | > 90% |
| Memory Usage | < 60% | > 80% | > 90% |
| Database Connections | < 50 | > 75 | > 100 |
| Cache Hit Ratio | > 80% | < 60% | < 30% |
| Disk Usage | < 80% | > 85% | > 95% |

### 3.2 Alert Configuration

**High Priority Alerts** (Immediate Response):
```
1. Application Error Rate > 2%
   → PagerDuty notification
   → Trigger incident response

2. Database CPU > 90%
   → SlackAlert to @dba-team
   → Check slow queries

3. Disk Usage > 95%
   → Urgent notification
   → Start cleanup procedures

4. API Response Time > 1.5s
   → Slack notification
   → Investigate performance
```

**Medium Priority Alerts** (Within 15 minutes):
```
1. Memory Usage > 80%
   → Notification to @devops-team
   → Monitor trend

2. Cache Hit Ratio < 60%
   → Log warning
   → Analyze cache patterns

3. Error Rate > 0.5% but < 2%
   → Review error logs
   → Investigate if trending
```

**Low Priority Alerts** (Check during shift):
```
1. Backup not completed
   → Notify @dba-team
   → Verify backup job running

2. Log rotation needed
   → Review disk space
   → Manual rotation if needed

3. Minor performance degradation
   → Monitor trends
   → Plan optimization
```

### 3.3 Alert Response Workflow

```
Alert Triggered
    ↓
Acknowledge in PagerDuty
    ↓
Review Alert Details
    ↓
Check Dashboard
    ↓
Investigate Root Cause
    ├→ Application Issue → Check logs, restart service
    ├→ Database Issue → Check slow queries, run analysis
    ├→ Performance Issue → Check metrics, scaling
    └→ Infrastructure Issue → Check resources, alerts
    ↓
Implement Fix
    ↓
Monitor for 10 minutes
    ↓
Resolve Alert
    ↓
Post-Incident Review (if major)
```

---

## 4. Backup & Recovery

### 4.1 Automated Backups

**Daily Automated Backup Schedule**:
```
Time: 22:00 UTC (Off-peak hours)
Retention: 35 days
Frequency: Daily (full backup)
Type: Amazon RDS automated backup
```

**Backup Verification**:
```bash
# Check latest backup
aws rds describe-db-snapshots \
  --db-instance-identifier edms-prod \
  --query 'DBSnapshots[0].[DBSnapshotIdentifier,SnapshotCreateTime,PercentProgress]' \
  --output table

# Verify backup size
aws s3 ls s3://edms-backups/rds/ | tail -5

# Expected: Each backup > 500MB (size of production DB)
```

### 4.2 Manual Backups

**Create Manual Backup**:
```bash
# Before major changes
aws rds create-db-snapshot \
  --db-instance-identifier edms-prod \
  --db-snapshot-identifier edms-prod-manual-$(date +%Y-%m-%d-%H%M%S)

# Monitor backup creation
aws rds describe-db-snapshots \
  --db-snapshot-identifier edms-prod-manual-2025-01-15-090000 \
  --query 'DBSnapshots[0].[Status,PercentProgress]' \
  --output table

# Wait for status: available
```

### 4.3 Restore from Backup

**Restore to New Instance** (for testing):
```bash
# Create restore instance (don't use for production)
aws rds restore-db-instance-from-db-snapshot \
  --db-instance-identifier edms-prod-restore \
  --db-snapshot-identifier edms-prod-backup-2025-01-15-22-00 \
  --db-instance-class db.r5.2xlarge \
  --publicly-accessible false

# Wait for restoration (20-30 minutes)
aws rds describe-db-instances \
  --db-instance-identifier edms-prod-restore \
  --query 'DBInstances[0].DBInstanceStatus'

# Test connection
sqlcmd -S edms-prod-restore.xxx.us-east-1.rds.amazonaws.com \
  -U edms_admin -P 'password' -d edms_production -Q "SELECT COUNT(*) FROM audit_logs"
```

**Production Restore Procedure** (in case of data loss):
```bash
# ONLY if database corruption or major data loss

# 1. Notify all stakeholders
slack-notify "#operations" \
  "🚨 Database recovery in progress - NO DATA CHANGES"

# 2. Create point-in-time restore target
RECOVERY_TIME="2025-01-15 14:00:00"
aws rds restore-db-instance-to-point-in-time \
  --source-db-instance-identifier edms-prod \
  --target-db-instance-identifier edms-prod-recovery \
  --restore-time "$RECOVERY_TIME" \
  --copy-tags-to-snapshot

# 3. Wait for restore to complete
# Estimated time: 20-40 minutes

# 4. Verify data integrity
ssh ubuntu@app-server
php artisan tinker
DB::select("SELECT COUNT(*) FROM audit_logs")

# 5. Failover: Swap recovery instance with production
# Create DNS alias or update RDS endpoint
# Update application connection strings

# 6. Notify completion
slack-notify "#operations" \
  "✅ Database recovery complete - Service restored"

# 7. Post-incident review
# Schedule meeting for 24 hours later
```

### 4.4 Application Data Backup

**Export Critical Data**:
```bash
# Daily export of audit logs and reports
php artisan activity:export-critical-data

# Files created:
# - storage/backups/audit-logs-$(date +%Y-%m-%d).sql
# - storage/backups/reports-$(date +%Y-%m-%d).sql
# - storage/backups/user-activity-$(date +%Y-%m-%d).sql

# Upload to S3
aws s3 cp storage/backups/ \
  s3://edms-backups/daily-exports/ \
  --recursive \
  --sse AES256
```

---

## 5. Incident Response

### 5.1 Incident Classification

| Severity | Response Time | Example | Escalation |
|----------|----------------|---------|------------|
| Critical | < 5 min | Database down, 404 errors for all users | VP ops, VP eng |
| High | < 15 min | 50% of users affected, API errors | Engineering lead |
| Medium | < 1 hour | <10% affected, degraded performance | On-call engineer |
| Low | < 4 hours | Minor issues, notification only | Support team |

### 5.2 Incident Response Plan

**CRITICAL: Database Down**
```
1. Page on-call team immediately
   pagerduty-trigger "Database Down" CRITICAL

2. Acknowledge incident
   slack-post "#incidents" "🚨 CRITICAL: Database Down (Database)"

3. Assess status
   aws rds describe-db-instances --db-instance-identifier edms-prod
   
4. If restarting:
   aws rds reboot-db-instance --db-instance-identifier edms-prod
   
5. If failover needed:
   aws rds failover-db-cluster \
     --db-cluster-identifier edms-prod-cluster

6. Notify users
   - Update status page
   - Send email notification
   - Post in comms channel

7. Post-incident (within 24 hours)
   - Document root cause
   - Create action items
   - Share with team
```

**HIGH: API errors for 50%+ users**
```
1. Alert on-call engineer
2. Check error logs for pattern
   tail -f /var/log/nginx/error.log | grep -i error
3. Review recent deployments
   git log --oneline -10
4. Check server resources
   top
   df -h
5. If recent deploy, rollback
   git revert HEAD
   php artisan optimize
   supervisorctl restart laravel-worker
6. Verify functionality restored
   curl https://api.edms.local/health
7. Monitor for 30 minutes
```

**MEDIUM: Degraded Performance (<500ms target violated)**
```
1. Log issue in tracking system
2. Gather metrics
   - API response times
   - Database query times
   - Cache hit rate
3. Identify slow queries
   SELECT * FROM sys.dm_exec_requests
4. Review recent changes
   git log --oneline -5
5. If needed, scale resources
   - Add more EC2 instances
   - Increase cache size
6. Implement fix (next business day if possible)
```

### 5.3 Incident Post-Mortem

**Post-Incident Review Template**:
```markdown
# Post-Incident Report: [Incident Name]

## Incident Timeline
- 14:30 UTC: Alert triggered
- 14:35 UTC: Incident acknowledged
- 14:45 UTC: Root cause identified
- 15:00 UTC: Fix implemented
- 15:05 UTC: Service restored

## Root Cause
[Description]

## Impact
- Duration: 35 minutes
- Users affected: 1,200 (80%)
- Revenue impact: $X,000

## Contributing Factors
1. [Factor 1]
2. [Factor 2]

## Resolution
[Description of fix]

## Action Items (for prevention)
1. [ ] Implement monitoring for [metric]
2. [ ] Add automated [test]
3. [ ] Update runbook for [scenario]

## Assigned To & Due Date
- Person A: Item 1 - Due: 2025-01-22
- Person B: Item 2 - Due: 2025-01-22
```

---

## 6. Database Maintenance

### 6.1 Daily Database Tasks

**Time: 23:00 UTC (after automated backup)**

```bash
#!/bin/bash
# File: /usr/local/bin/daily-db-maintenance.sh

# 1. Check database integrity
sqlcmd -S $DB_HOST -U $DB_USER -P $DB_PASS -d edms_production \
  -Q "DBCC CHECKDB (edms_production, REPAIR_ALLOW_DATA_LOSS)" \
  > /var/log/db-integrity-check-$(date +%Y-%m-%d).log

# 2. Update index statistics
sqlcmd -S $DB_HOST -U $DB_USER -P $DB_PASS -d edms_production << EOF
EXEC sp_MSForEachTable 'UPDATE STATISTICS ? (ALL)'
EOF

# 3. Rebuild fragmented indexes
sqlcmd -S $DB_HOST -U $DB_USER -P $DB_PASS -d edms_production << EOF
DECLARE @ObjectId INT, @IndexId INT
SELECT object_id, index_id INTO #FragmentedIndexes
FROM sys.dm_db_index_physical_stats(DB_ID(), NULL, NULL, NULL, 'LIMITED')
WHERE avg_fragmentation_in_percent > 10

DECLARE index_cursor CURSOR FOR
SELECT object_id, index_id FROM #FragmentedIndexes

OPEN index_cursor
FETCH NEXT FROM index_cursor INTO @ObjectId, @IndexId

WHILE @@FETCH_STATUS = 0
BEGIN
  ALTER INDEX ALL ON (SELECT OBJECT_NAME(@ObjectId))
  REORGANIZE
  
  FETCH NEXT FROM index_cursor INTO @ObjectId, @IndexId
END

CLOSE index_cursor
DEALLOCATE index_cursor
EOF

# 4. Clean up old logs
DELETE FROM audit_logs WHERE created_at < DATEADD(DAY, -90, GETDATE())
DELETE FROM activity_logs WHERE created_at < DATEADD(DAY, -180, GETDATE())

# 5. Archive old reports
php artisan reports:archive-old --older-than=90

# 6. Verify backup completed
aws rds describe-db-snapshots \
  --db-instance-identifier edms-prod \
  --query 'DBSnapshots[0].Status'
```

### 6.2 Weekly Database Tasks

**Time: Sunday 02:00 UTC**

```sql
-- Full database consistency check
DBCC CHECKDB (edms_production);

-- Shrink transaction log if > 5GB
DBCC SHRINKFILE (edms_production_log, 1000);

-- Rebuild all indexes
ALTER INDEX ALL ON audit_logs REBUILD;
ALTER INDEX ALL ON user_activity_logs REBUILD;
ALTER INDEX ALL ON reports REBUILD;

-- Update all statistics
EXEC sp_updatestats;
```

### 6.3 Monthly Database Tasks

**Time: First Saturday of month 03:00 UTC**

```sql
-- Full index maintenance
-- Rebuild if fragmentation > 30%, reorganize if 10-30%

DECLARE @TableName VARCHAR(255)
DECLARE @IndexName VARCHAR(255)
DECLARE @Fragmentation DECIMAL(5,2)

DECLARE index_cursor CURSOR FOR
SELECT OBJECT_NAME(ps.object_id), i.name, ps.avg_fragmentation_in_percent
FROM sys.dm_db_index_physical_stats(DB_ID(), NULL, NULL, NULL, 'LIMITED') ps
JOIN sys.indexes i ON ps.object_id = i.object_id AND ps.index_id = i.index_id
WHERE ps.avg_fragmentation_in_percent > 10

OPEN index_cursor
FETCH NEXT FROM index_cursor INTO @TableName, @IndexName, @Fragmentation

WHILE @@FETCH_STATUS = 0
BEGIN
  IF @Fragmentation > 30
    EXEC sp_executesql N'ALTER INDEX [' + @IndexName + '] ON [' + @TableName + '] REBUILD'
  ELSE
    EXEC sp_executesql N'ALTER INDEX [' + @IndexName + '] ON [' + @TableName + '] REORGANIZE'
  
  FETCH NEXT FROM index_cursor INTO @TableName, @IndexName, @Fragmentation
END

CLOSE index_cursor
DEALLOCATE index_cursor
```

---

## 7. Cache Management

### 7.1 Daily Cache Monitoring

**Check Cache Health**:
```bash
# Check Redis memory usage
redis-cli -h $REDIS_HOST INFO memory

# Expected output:
# used_memory_human: 2.5GB
# maxmemory: 4GB
# used_memory_percent: 62.5%

# Check key count
redis-cli -h $REDIS_HOST DBSIZE
# Expected: < 1,000,000 keys

# Check eviction rate
redis-cli -h $REDIS_HOST INFO stats | grep evicted_keys
# Expected: 0 (if > 0, increase memory)

# Check connected clients
redis-cli -h $REDIS_HOST INFO clients | grep connected_clients
# Expected: < 50
```

### 7.2 Cache Invalidation

**Manual Cache Clear**:
```bash
# Clear all cache
php artisan cache:clear

# Clear specific tag
php artisan cache:forget report_*

# Clear all keys matching pattern
redis-cli -h $REDIS_HOST KEYS "analytics_*" | xargs redis-cli -h $REDIS_HOST DEL

# Flush entire Redis (CAUTION - affects all apps using Redis)
redis-cli -h $REDIS_HOST FLUSHALL
```

**Scheduled Cache Cleanup**:
```bash
# File: /usr/local/bin/cache-cleanup.sh
# Runs daily at 03:00

#!/bin/bash

# Clear stale cache entries
redis-cli -h $REDIS_HOST KEYS "test_*" | xargs redis-cli -h $REDIS_HOST DEL

# Remove expired sessions
redis-cli -h $REDIS_HOST EVAL "
  local keys = redis.call('keys', ARGV[1])
  for i=1,#keys do
    redis.call('del', keys[i])
  end
  return #keys
" 0 "session:*"

# Archive old analytics cache
php artisan cache:cleanup-analytics
```

### 7.3 Cache Performance Tuning

**Monitor Cache Efficiency**:
```bash
# Track hit vs miss ratio
redis-cli -h $REDIS_HOST INFO stats | grep -E "hits|misses"

# Calculation: hits / (hits + misses) = hit ratio
# Expected: > 80%

# If hit ratio < 80%:
# 1. Increase cache retention time
# 2. Cache more frequently accessed data
# 3. Increase Redis memory
```

---

## 8. Log Management

### 8.1 Log Locations

| Log | Location | Retention | Size |
|-----|----------|-----------|------|
| Access | /var/log/nginx/edms-prod-access.log | 30 days | 500MB/day |
| Error | /var/log/nginx/edms-prod-error.log | 30 days | 50MB/day |
| PHP-FPM | /var/log/php8.1-fpm.log | 14 days | 100MB/day |
| Laravel | /storage/logs/laravel.log | 90 days | 200MB/day |
| System | /var/log/syslog | 30 days | 300MB/day |
| Database | /var/log/rds-slow-queries.log | 7 days | 100MB/day |

### 8.2 Log Rotation

**Automatic Log Rotation** (via logrotate):

```
File: /etc/logrotate.d/edms

/var/log/nginx/edms-prod-*.log
/storage/logs/laravel.log {
    daily                      # Rotate daily
    rotate 30                  # Keep 30 files
    compress                   # Gzip old logs
    delaycompress              # Don't compress yesterday's
    missingok                  # Don't error if missing
    notifempty                 # Don't rotate if empty
    create 0640 www-data www-data
    sharedscripts              # Run postrotate once
    postrotate
        nginx -s reload > /dev/null 2>&1
        systemctl reload php8.1-fpm > /dev/null 2>&1
    endscript
}
```

**Manual Log Rotation** (if needed):

```bash
# Manual rotation
logrotate -f /etc/logrotate.d/edms

# Verify
ls -lah /var/log/nginx/ | tail -10

# Archive old logs to S3
tar -czf /var/log/archives/logs-$(date +%Y-%m-%d).tar.gz \
  /var/log/*.log.1 \
  /storage/logs/*.log.1

aws s3 cp /var/log/archives/logs-*.tar.gz \
  s3://edms-logs/archived/ \
  --sse AES256
```

### 8.3 Log Monitoring

**Real-time Log Monitoring**:
```bash
# Watch for errors
tail -f /var/log/nginx/edms-prod-error.log | grep -i error

# Monitor specific pattern
tail -f /storage/logs/laravel.log | grep "ERROR\|CRITICAL"

# Count errors by type
grep "ERROR" /storage/logs/laravel.log | \
  awk '{print $NF}' | \
  sort | uniq -c | sort -rn | head -10

# Errors per hour
grep "ERROR" /storage/logs/laravel.log | \
  awk '{print substr($0,1,13)}' | \
  sort | uniq -c
```

---

## 9. Performance Monitoring

### 9.1 Performance Metrics Dashboard

**Key Metrics to Track**:
```
API Response Times:
- GET /health: < 50ms
- POST /api/activity-analytics/sessions: < 500ms
- GET /api/activity-reports: < 300ms
- POST /api/activity-reports/generate: < 2s

Database Metrics:
- Query response time: < 100ms (95th percentile)
- Connection pool usage: < 50%
- Lock wait time: < 100ms

Cache Metrics:
- Hit ratio: > 80%
- Memory usage: < 75% of max
- Evictions: 0 per minute

Resource Metrics:
- CPU: < 60%
- Memory: < 70%
- Disk: < 80%
- Network: < 60% capacity
```

### 9.2 Performance Trend Analysis

**Weekly Performance Report**:
```bash
#!/bin/bash
# File: /usr/local/bin/weekly-performance-report.sh

cat > /tmp/performance-report.md << 'EOF'
# Weekly Performance Report - $(date +%Y-%m-%d)

## API Response Times
EOF

# Collect metrics from CloudWatch
aws cloudwatch get-metric-statistics \
  --namespace AWS/ApplicationELB \
  --metric-name TargetResponseTime \
  --start-time $(date -u -d '7 days ago' +%Y-%m-%dT%H:%M:%S) \
  --end-time $(date -u +%Y-%m-%dT%H:%M:%S) \
  --period 3600 \
  --statistics Average,Maximum \
  --query 'Datapoints[].[Timestamp,Average,Maximum]' \
  --output table >> /tmp/performance-report.md

# Add more metrics and email report
mail -s "Weekly Performance Report" operations@edms.local < /tmp/performance-report.md
```

---

## 10. User Management

### 10.1 User Access

**Adding New User**:
```bash
# Create user via admin panel or artisan
php artisan make:user \
  --email newuser@edms.local \
  --name "New User" \
  --type user \
  --department "Land Admin"

# Or via database
INSERT INTO users (name, email, type, department_id, email_verified_at)
VALUES ('New User', 'newuser@edms.local', 'user', 1, NOW());

# Verify user created
php artisan tinker
User::where('email', 'newuser@edms.local')->first()
```

**Granting Permissions**:
```bash
php artisan tinker

$user = User::where('email', 'newuser@edms.local')->first();
$user->givePermissionTo('view-analytics');
$user->givePermissionTo('create-reports');
$user->givePermissionTo('download-reports');
```

### 10.2 Monitoring User Activity

**Check Active Sessions**:
```bash
# Get current online users
curl -H "Authorization: Bearer $TOKEN" \
  https://api.edms.local/api/sessions/active

# Check specific user's recent activity
php artisan tinker
AuditLog::where('user_id', 5)->recent(7)->get()
```

### 10.3 Deactivating Users

**Disable User Access**:
```bash
# Via database
UPDATE users SET active = FALSE WHERE email = 'olduser@edms.local';

# Revoke all permissions
$user = User::where('email', 'olduser@edms.local')->first();
$user->revokePermissionTo('*');

# Kill active sessions
DELETE FROM user_activity_logs 
WHERE user_id = $userId AND status != 'Offline';

# Audit log
AuditLog::create([
  'user_id' => Auth::id(),
  'action' => 'USER_DEACTIVATED',
  'resource_type' => 'user',
  'resource_id' => $userId
]);
```

---

## 11. Security Operations

### 11.1 Daily Security Checks

```bash
#!/bin/bash
# File: /usr/local/bin/daily-security-check.sh

echo "=== Daily Security Check - $(date) ==="

# 1. Check for unauthorized SSH keys
echo "Checking SSH keys..."
wc -l ~/.ssh/authorized_keys

# 2. Check for failed login attempts
echo "Failed login attempts (last 24h):"
grep "Failed password" /var/log/auth.log | wc -l

# 3. Verify file permissions
echo "Checking sensitive file permissions..."
ls -la /var/www/edms/.env
ls -la /etc/ssl/private/

# 4. Check for missing security patches
echo "Checking for updates..."
apt list --upgradable | wc -l

# 5. Verify firewall rules
echo "Checking firewall status..."
sudo ufw status

# 6. Check system package versions
echo "Critical packages:"
dpkg -l | grep -E "openssl|php|nginx" | head -10

# Email report
mail -s "Daily Security Check" security@edms.local < \
  <(echo "=== Daily Security Report ==="; date)
```

### 11.2 Security Patches

**Apply Security Updates**:
```bash
# Weekly patch cycle (Sunday 03:00)

# 1. Check available updates
apt update
apt list --upgradable

# 2. Back up system
tar -czf /backups/system-before-patch-$(date +%Y-%m-%d).tar.gz /etc /var/www

# 3. Apply critical patches immediately
apt upgrade -y

# 4. Check for restarts needed
[ -f /var/run/reboot-required ] && echo "Reboot required"

# 5. If reboot needed:
# Schedule during maintenance window
sudo shutdown -r +120 "System reboot for security patches in 2 hours"

# 6. Verify services after reboot
systemctl status nginx
systemctl status php8.1-fpm
systemctl status redis-server
```

### 11.3 SSL/TLS Certificate Management

**Certificate Renewal**:
```bash
# SSL certificates auto-renew via Let's Encrypt (if used)
# Or via AWS Certificate Manager (recommended for production)

# Check certificate expiration
echo | openssl s_client -servername api.edms.local \
  -connect api.edms.local:443 2>/dev/null | \
  openssl x509 -noout -dates

# Renew manually (if needed)
certbot renew --no-eff-email --quiet

# Update Nginx after renewal
sudo systemctl reload nginx
```

---

## 12. Escalation Procedures

### 12.1 Escalation Matrix

```
Severity: CRITICAL
First Responder: On-call Engineer
If not resolved in 5 min → Escalate to Engineering Lead
If not resolved in 10 min → Escalate to VP Engineering
If not resolved in 15 min → Escalate to CEO

Severity: HIGH
First Responder: Support Team
If not resolved in 15 min → Escalate to On-call Engineer
If not resolved in 30 min → Escalate to Engineering Lead
If not resolved in 1 hour → Escalate to VP Engineering

Severity: MEDIUM
First Responder: Support Team
If not resolved in 1 hour → Escalate to Engineering
If not resolved in 4 hours → Escalate to Team Lead
If not resolved by EOD → Escalate to VP
```

### 12.2 Escalation Communication Template

```
ESCALATION NOTICE

Severity: CRITICAL
Issue: [Brief description]
Status: [Current status]
Time Open: 15 minutes
Previous Responder: [Name]
Escalated To: [Name]

Details:
[Full description]

Actions Taken:
1. [Action 1]
2. [Action 2]

Current Investigation:
[Investigation details]

Requested Response:
[What specific help is needed]

Contact: [Phone/Slack]
```

---

## 13. Runbooks

### 13.1 Runbook: High CPU Usage

```markdown
# HIGH CPU USAGE - RUNBOOK

## Symptoms
- CloudWatch CPU alert triggered
- Slow API responses
- Users reporting delays

## Investigation
1. SSH to affected server
   ssh -i prod-key.pem ubuntu@instance-ip

2. Check current CPU usage
   top -b -n 1 | head -20

3. Identify top processes
   ps aux --sort=-%cpu | head -10

4. Check database connections
   SELECT COUNT(*) FROM sys.dm_exec_requests

5. Look for slow queries
   SELECT * FROM sys.dm_exec_query_stats 
   ORDER BY total_elapsed_time DESC LIMIT 10

## Solution

If PHP-FPM using too much CPU:
1. Check running jobs
   ps aux | grep php

2. Kill stuck processes
   sudo kill -9 [PID]

3. Restart PHP-FPM
   sudo systemctl restart php8.1-fpm

If Database using too much CPU:
1. Check slow query log
2. Kill long-running queries
3. Optimize problematic queries
4. Consider adding indexes

If still high:
1. Scale up instance
2. Add more PHP-FPM workers
3. Increase database memory

## Escalation
If CPU remains > 80% after 5 minutes → Page DBA
```

### 13.2 Runbook: Database Connection Pool Exhausted

```markdown
# DATABASE CONNECTION POOL EXHAUSTED - RUNBOOK

## Symptoms
- API returning 500 errors
- "SQLSTATE[08001]: Could not connect" error
- Connection timeout errors

## Investigation
1. Check active connections
   SELECT * FROM sys.dm_exec_sessions 
   WHERE database_id = DB_ID('edms_production')

2. Check connection pool status
   redis-cli -h $REDIS_HOST INFO stats | grep connections

3. Check for idle connections
   SELECT * FROM sys.dm_exec_sessions 
   WHERE status = 'sleeping' AND last_request_start_time < DATEADD(MINUTE, -10, GETDATE())

4. Review application logs
   tail -f /storage/logs/laravel.log | grep "connect"

## Solution

1. Kill idle connections
   ALTER SESSION CLOSE IDLE CONNECTIONS

2. Restart connection pool
   sudo systemctl restart php8.1-fpm

3. Check for connection leaks
   grep -c "DB::connection" /app/code/*.php

4. Verify connection pool size
   cat config/database.php | grep "pool"

5. If needed, increase pool size
   Edit config/database.php
   'pool' => 100  (increase from current value)

## Escalation
If connections still maxed → Page Database Admin
```

---

## 14. SLA & Support

### 14.1 Service Level Agreement

```
EDMS User Activity Log System - SLA

Availability: 99.9% uptime
- Downtime allowed: ~43 minutes/month
- Measured: Monthly uptime percentage

Response Times:
- API endpoints: < 500ms (95th percentile)
- Dashboard: < 2s load time
- Report generation: < 3 minutes for 30-day report

Support Hours:
- Critical (24/7): All hours, 15-minute response
- High (Business Hours): 06:00-22:00, 1-hour response
- Medium (Business Hours): 08:00-18:00, 4-hour response
- Low (Ticket): Response within 1 business day

Backup & Recovery:
- Backup: Daily automatic backup
- Recovery: RTO < 1 hour, RPO < 1 hour
```

### 14.2 Support Contact

```
Email: support@edms.local
Phone: +1-555-SUPPORT (1-555-7887634)
Slack: #edms-support

Hours:
- 24/7 for Critical issues
- Mon-Fri 08:00-18:00 for other issues
- Emergency on-call through PagerDuty
```

---

*Generated: November 10, 2025*
*Version: 1.0*
*Status: Complete & Production-Ready*
