# User Activity Log System - Visual Reference Guide

**Date**: November 10, 2025  
**Purpose**: Quick reference for architecture and flow diagrams

---

## 📊 System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         USER ACTIVITY LOG SYSTEM                        │
└─────────────────────────────────────────────────────────────────────────┘

┌────────────────┐
│  Web Browser   │
│  (Client)      │
└───────┬────────┘
        │
        │ HTTPS/AJAX
        │
┌───────▼────────────────────────────────────────────────────────┐
│                     LARAVEL APPLICATION                         │
│ ┌──────────────────────────────────────────────────────────┐   │
│ │                   HTTP REQUESTS                          │   │
│ └──────────────────────────────────────────────────────────┘   │
│ ┌──────────────────────────────────────────────────────────┐   │
│ │  Middleware Chain                                        │   │
│ │  - Authentication                                        │   │
│ │  - TrackActivityLog (Captures session data)             │   │
│ │  - Permissions                                          │   │
│ └──────────────────────────────────────────────────────────┘   │
│ ┌──────────────────────────────────────────────────────────┐   │
│ │  Controllers                                             │   │
│ │  - ActivityLogController (Web requests)                 │   │
│ │  - API endpoints (AJAX)                                 │   │
│ └──────────────────────────────────────────────────────────┘   │
│ ┌──────────────────────────────────────────────────────────┐   │
│ │  Service Layer                                           │   │
│ │  - ActivityLogService (Business logic)                  │   │
│ │  - Session management                                   │   │
│ │  - Cleanup operations                                   │   │
│ └──────────────────────────────────────────────────────────┘   │
└───────┬──────────────────────────────────────────────────────────┘
        │
        │ Queries/Cache
        │
┌───────▼──────────────────────────────────────────────────────┐
│                    DATA LAYER                                │
│ ┌────────────────────────────────────────┐                  │
│ │  SQL Server                            │                  │
│ │  ├─ user_activity_logs                 │                  │
│ │  ├─ user_activity_log_settings         │                  │
│ │  └─ users                              │                  │
│ └────────────────────────────────────────┘                  │
│ ┌────────────────────────────────────────┐                  │
│ │  Redis Cache (Optional)                │                  │
│ │  ├─ online_users (TTL: 2 min)          │                  │
│ │  └─ activity_log_settings_* (TTL: 1 hr)│                  │
│ └────────────────────────────────────────┘                  │
└───────────────────────────────────────────────────────────────┘
```

---

## 🔄 Session Lifecycle Flow

```
┌─────────────────┐
│   USER LOGIN    │
└────────┬────────┘
         │
         ▼
┌──────────────────────────────┐
│ TrackActivityLog Middleware  │
│ - Capture IP, device, browser│
└────────┬─────────────────────┘
         │
         ▼
┌──────────────────────────────┐
│ Create ActivityLog Entry     │
│ status = 'Online'            │
│ login_time = now()           │
│ last_seen_at = now()         │
└────────┬─────────────────────┘
         │
         ▼
┌──────────────────────────────┐
│ Client: Start Heartbeat      │
│ Interval: 30-60s             │
└────────┬─────────────────────┘
         │
         ▼
┌──────────────────────────────┐
│ ACTIVE SESSION               │
│ POST /api/activity/heartbeat │
│ → Update last_seen_at        │
└────────┬─────────────────────┘
         │
    ┌────┴────┬──────────────────┐
    │          │                  │
    ▼          ▼                  ▼
  Active    No Heartbeat     Logout Event
  (repeat)  for 30+ mins     
    │      (status = Idle)       │
    │          │                 │
    │          ▼                 │
    │      No Heartbeat for      │
    │      120+ mins             │
    │      (status = Offline)    │
    │          │                 │
    └──────────┴─────────────────┘
               │
               ▼
    ┌──────────────────────────┐
    │  User Logout             │
    │  status = 'Offline'      │
    │  logout_time = now()     │
    │  Calculate duration_mins │
    └──────────────────────────┘
```

---

## 📋 Database Schema

### user_activity_logs Table
```
┌─ Identification ─────────────────────────┐
│ id (PK)                                  │
│ user_id (FK) → users.id                  │
└──────────────────────────────────────────┘

┌─ Session Timing ─────────────────────────┐
│ login_time (datetime, indexed)           │
│ logout_time (datetime, nullable)         │
│ duration_minutes (int, nullable)         │
│ last_seen_at (datetime, indexed)         │
└──────────────────────────────────────────┘

┌─ Device/Network ─────────────────────────┐
│ ip_address (varchar)                     │
│ device (varchar: desktop/mobile/tablet)  │
│ browser (varchar: Chrome/Firefox/etc)    │
│ platform (varchar: Windows/Mac/Linux)    │
└──────────────────────────────────────────┘

┌─ Status Management ──────────────────────┐
│ status (enum: Online/Offline/Idle)       │
│ related_file_number (varchar, nullable)  │
└──────────────────────────────────────────┘

┌─ Admin/Tracking ─────────────────────────┐
│ test_control (enum: TEST/PRO)            │
│ indexed_at (datetime, nullable)          │
│ created_at, updated_at (timestamps)      │
└──────────────────────────────────────────┘

Indexes:
  • (user_id, status, login_time)
  • status
  • test_control
  • last_seen_at
```

### user_activity_log_settings Table
```
┌─ User Preferences ───────────────────────┐
│ id (PK)                                  │
│ user_id (FK) → users.id (unique)         │
│ timezone (varchar, default: config)      │
│ notes (text, nullable)                   │
│ created_at, updated_at (timestamps)      │
└──────────────────────────────────────────┘

┌─ Cleanup Settings ───────────────────────┐
│ auto_cleanup_enabled (boolean)           │
│ retention_days (int)                     │
└──────────────────────────────────────────┘

┌─ UI Settings ───────────────────────────┐
│ auto_refresh_enabled (boolean)           │
│ refresh_interval (int, seconds)          │
└──────────────────────────────────────────┘
```

---

## 🏗️ Component Architecture

```
┌─────────────────────────────────────────────────────────┐
│                 FRONTEND LAYER                         │
│                                                         │
│  ┌──────────────────────────────────────────────────┐  │
│  │  Blade Templates (partials/)                     │  │
│  │  ├─ header.blade.php (Page title + buttons)     │  │
│  │  ├─ tabs.blade.php (Tab navigation)             │  │
│  │  ├─ filters.blade.php (Filter panel)            │  │
│  │  ├─ activity-table.blade.php (DataTable)        │  │
│  │  ├─ online-users.blade.php (Grid)               │  │
│  │  └─ modals.blade.php (Modal dialogs)            │  │
│  └──────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────┐  │
│  │  JavaScript Modules (public/js/)                 │  │
│  │  ├─ heartbeat.js (Session tracking)             │  │
│  │  ├─ online-users.js (Real-time updates)         │  │
│  │  ├─ table-manager.js (DataTable mgmt)           │  │
│  │  ├─ filters.js (Filter logic)                   │  │
│  │  ├─ modals.js (Modal management)                │  │
│  │  └─ export.js (Export functionality)            │  │
│  └──────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────┐  │
│  │  CSS/Styling (resources/css/)                    │  │
│  │  ├─ activity-logs.css (Component styles)        │  │
│  │  └─ status-badges.css (Status colors)           │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
           │
           │ AJAX Requests
           ▼
┌─────────────────────────────────────────────────────────┐
│                 API LAYER (REST)                        │
│                                                         │
│  POST   /api/activity/heartbeat                        │
│  GET    /api/activity/online                           │
│  GET    /api/activity/logs                             │
│  GET    /api/activity/logs/{id}                        │
│  POST   /api/activity/logs/{id}/delete                 │
│  POST   /api/activity/logs/bulk-delete                 │
│  GET    /api/activity/export                           │
│  POST   /api/activity/cleanup                          │
│  GET    /api/activity/settings                         │
│  POST   /api/activity/settings                         │
│  POST   /api/activity/logout-user/{userId}             │
└─────────────────────────────────────────────────────────┘
           │
           │ HTTP Routing
           ▼
┌─────────────────────────────────────────────────────────┐
│              CONTROLLER LAYER                          │
│                                                         │
│  ActivityLogController                                 │
│  ├─ index() → GET /user-activity-logs               │  │
│  ├─ show($id) → GET /user-activity-logs/{id}        │  │
│  ├─ destroy($id) → DELETE /user-activity-logs/{id}  │  │
│  ├─ bulkDelete() → POST /api/activity/bulk-delete   │  │
│  ├─ export() → GET /api/activity/export             │  │
│  ├─ cleanup() → POST /api/activity/cleanup          │  │
│  ├─ heartbeat() → POST /api/activity/heartbeat      │  │
│  ├─ onlineUsers() → GET /api/activity/online        │  │
│  └─ settings() → GET/POST /api/activity/settings    │  │
└─────────────────────────────────────────────────────────┘
           │
           │ Method Calls
           ▼
┌─────────────────────────────────────────────────────────┐
│              SERVICE LAYER                             │
│                                                         │
│  ActivityLogService                                    │
│  ├─ recordLogin()                                    │  │
│  ├─ recordLogout()                                   │  │
│  ├─ updateHeartbeat()                                │  │
│  ├─ detectIdleUsers()                                │  │
│  ├─ getOnlineUsers()                                 │  │
│  ├─ getFilteredLogs()                                │  │
│  ├─ performCleanup()                                 │  │
│  ├─ calculateDuration()                              │  │
│  └─ detectDevice/Browser/Platform()                 │  │
└─────────────────────────────────────────────────────────┘
           │
           │ Query Building
           ▼
┌─────────────────────────────────────────────────────────┐
│              MODEL LAYER (Eloquent)                    │
│                                                         │
│  UserActivityLog                                       │
│  ├─ belongsTo(User)                                  │  │
│  ├─ scopeOnline(), scopeOffline(), scopeIdle()      │  │
│  ├─ scopeByUser(), scopeByDateRange()                │  │
│  ├─ scopeTestData(), scopeProduction()              │  │
│  ├─ isOnline(), isOffline(), isIdle()               │  │
│  └─ Accessors: duration_minutes, formatted_duration  │  │
│                                                         │
│  UserActivityLogSetting                                │
│  ├─ belongsTo(User)                                  │  │
│  ├─ scopeForUser()                                   │  │
│  └─ getDefaults(), firstOrCreateDefaults()           │  │
└─────────────────────────────────────────────────────────┘
           │
           │ SQL Queries
           ▼
┌─────────────────────────────────────────────────────────┐
│              DATABASE LAYER                            │
│                                                         │
│  SQL Server Database                                   │
│  ├─ user_activity_logs (indexed)                     │  │
│  ├─ user_activity_log_settings                       │  │
│  └─ users (FK relationships)                         │  │
│                                                         │
│  Cache Layer (Redis/File)                             │
│  ├─ online_users (TTL: 2 min)                       │  │
│  └─ activity_log_settings_* (TTL: 1 hr)             │  │
└─────────────────────────────────────────────────────────┘
```

---

## 🔀 Request/Response Patterns

### Pattern 1: Heartbeat Request
```
CLIENT REQUEST:
POST /api/activity/heartbeat
Headers: {
  'X-CSRF-TOKEN': token,
  'Content-Type': 'application/json'
}
Body: {} (empty)

SERVER RESPONSE:
{
  "success": true,
  "timestamp": 1731247000,
  "last_seen_at": "2025-11-10 14:30:00"
}
```

### Pattern 2: Online Users Request
```
CLIENT REQUEST:
GET /api/activity/online

SERVER RESPONSE:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 5,
      "user_name": "Musa Ali",
      "user_email": "john@example.com",
      "ip_address": "192.168.1.100",
      "device_type": "desktop",
      "browser": "Chrome",
      "login_time": "2025-11-10 08:00:00",
      "online_duration": "6h 30m",
      "last_seen_at": "2025-11-10 14:29:50"
    },
    ...
  ],
  "count": 12
}
```

### Pattern 3: Filter Logs Request
```
CLIENT REQUEST:
GET /user-activity-logs?
  user_id=5&
  status=Online&
  device_type=desktop&
  date_from=2025-11-10&
  draw=1&
  start=0&
  length=50

SERVER RESPONSE:
{
  "draw": 1,
  "recordsTotal": 156,
  "recordsFiltered": 42,
  "data": [
    {
      "id": 1,
      "user_name": "Musa Ali",
      "user_email": "john@example.com",
      "ip_address": "192.168.1.100",
      "device_info": "Desktop • Chrome",
      "login_time": "2025-11-10 08:00:00",
      "logout_time": "2025-11-10 17:00:00",
      "duration": "9h 0m",
      "status": "<span class='badge-green'>Online</span>",
      "actions": "<button onclick='...'>Details</button>"
    },
    ...
  ]
}
```

---

## 📊 Status Flow Diagram

```
           ┌─────────────────┐
           │   User Login    │
           └────────┬────────┘
                    │
                    ▼
            ┌──────────────────┐
            │  Online (status) │
            │ last_seen: NOW   │
            └────────┬─────────┘
                     │
                  Heartbeat every 30-60s
                     │
        ┌────────────┼────────────┐
        │            │            │
        ▼            ▼            ▼
    Heartbeat   No Heartbeat  No Heartbeat
    Received    for 30+ min   for 120+ min
        │            │            │
        │            ▼            ▼
        │      ┌─────────────┐  ┌──────────────┐
        │      │ Idle Status │  │ Offline      │
        │      │             │  │ (with time)  │
        │      └─────────────┘  └──────────────┘
        │            │
        ▼            ▼ (manual logout or activity)
    ┌────────────────────────────────────┐
    │      Offline (Final Status)        │
    │  - logout_time recorded            │
    │  - duration_minutes calculated     │
    │  - Session closed                  │
    └────────────────────────────────────┘
```

---

## ⏱️ Timeline at a Glance

```
Week 1
  Day 1   │ Database Schema & Indexes
  Day 2-3 │ Eloquent Models
  Day 4-5 │ Backend Controllers & Services
          │
Week 2    │
  Day 1-2 │ API Endpoints
  Day 3-4 │ Frontend Views & JavaScript
  Day 5   │ Styling & Components
          │
Week 3    │
  Day 1-2 │ Advanced Features (Scheduler, Export)
  Day 3-4 │ Unit & Feature Tests
  Day 5   │ Performance Testing
          │
Week 4    │
  Day 1-2 │ Documentation
  Day 3-4 │ Staging Deployment
  Day 5   │ Final QA
          │
Week 5    │
  Day 1   │ Production Deployment & Monitoring
  
Total: 56.5 hours
```

---

## 🎯 Key Metrics

```
Performance Targets:
  - Query Response: < 100ms (50 records)
  - AJAX Refresh: < 300ms (with cache)
  - Pagination: < 500ms (50 records)
  - Cleanup: < 1 min (1 year data)

Load Capacity:
  - Concurrent Users: 100+
  - Records Handled: 10K+
  - Heartbeat Requests: 100/sec

Reliability:
  - Uptime Target: 99.5%
  - Data Loss: Zero tolerance
  - Session Accuracy: ±1 minute

Code Quality:
  - Test Coverage: 80%+ (units), 75%+ (feature)
  - Code Standards: PSR-12
  - Performance Score: Optimized
```

---

## 📁 File Organization

```
Project Root
├── app/
│   ├── Models/
│   │   ├── UserActivityLog.php
│   │   └── UserActivityLogSetting.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── ActivityLogController.php
│   │   └── Middleware/
│   │       └── TrackActivityLog.php
│   ├── Services/
│   │   └── ActivityLogService.php
│   └── Console/
│       └── Commands/
│           └── CleanupActivityLogs.php
│
├── resources/
│   ├── views/
│   │   └── user_activity_logs/
│   │       ├── index.blade.php
│   │       └── partials/
│   │           ├── header.blade.php
│   │           ├── tabs.blade.php
│   │           ├── filters.blade.php
│   │           ├── activity-table.blade.php
│   │           ├── online-users.blade.php
│   │           └── modals.blade.php
│   ├── js/
│   │   └── activity-logs/
│   │       ├── heartbeat.js
│   │       ├── online-users.js
│   │       ├── table-manager.js
│   │       ├── filters.js
│   │       ├── modals.js
│   │       └── export.js
│   └── css/
│       └── activity-logs.css
│
├── database/
│   └── migrations/
│       └── 2025_11_10_000001_enhance_activity_logs_tables.php
│
├── routes/
│   ├── api.php (API endpoints)
│   └── web.php (Web routes)
│
├── tests/
│   ├── Unit/
│   │   └── Services/
│   │       └── ActivityLogServiceTest.php
│   └── Feature/
│       ├── ActivityLogControllerTest.php
│       └── ActivityLogApiTest.php
│
├── config/
│   └── activity-logs.php
│
└── docs/
    ├── USER_ACTIVITY_LOG_IMPLEMENTATION_PLAN.md
    ├── USER_ACTIVITY_LOG_TECHNICAL_DECISIONS.md
    ├── USER_ACTIVITY_LOG_CODE_PATTERNS.md
    ├── USER_ACTIVITY_LOG_PLANNING_SUMMARY.md
    └── USER_ACTIVITY_LOG_VISUAL_REFERENCE.md
```

---

## 🔗 Integration Points

```
Existing Systems ← → Activity Log System

┌─────────────────────────────────────┐
│  User Authentication (Laravel Auth) │
│         ↓                           │
│  TrackActivityLog Middleware        │
│         ↓                           │
│  Activity Log Recording             │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│  Permission System (Spatie)         │
│         ↓                           │
│  Access Control Layer               │
│         ↓                           │
│  Activity Log Viewing Permissions   │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│  Department System (if exists)      │
│         ↓                           │
│  Role-Based Filtering               │
│         ↓                           │
│  Activity Logs Per Department       │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│  File/Application System            │
│         ↓                           │
│  related_file_number Field          │
│         ↓                           │
│  Link Sessions to Operations        │
└─────────────────────────────────────┘
```

---

**This visual guide complements the detailed documentation.**  
**Reference diagrams while reviewing implementation plans and code patterns.**

