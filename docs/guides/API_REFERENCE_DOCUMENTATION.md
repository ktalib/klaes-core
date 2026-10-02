# USER ACTIVITY LOG SYSTEM - COMPREHENSIVE API REFERENCE DOCUMENTATION

## Table of Contents

1. [API Overview](#api-overview)
2. [Authentication](#authentication)
3. [Authorization & Permissions](#authorization--permissions)
4. [Error Handling](#error-handling)
5. [Response Formats](#response-formats)
6. [Rate Limiting](#rate-limiting)
7. [Analytics API](#analytics-api)
8. [Reports API](#reports-api)
9. [Comparison API](#comparison-api)
10. [Audit Logs API](#audit-logs-api)
11. [Scheduled Reports API](#scheduled-reports-api)
12. [Sessions API](#sessions-api)
13. [Code Examples](#code-examples)
14. [Pagination & Filtering](#pagination--filtering)
15. [Webhook Events](#webhook-events)

---

## 1. API Overview

### 1.1 Base Information

```
Environment: API
Base URL: https://api.edms.local/api/
Current Version: v1
Protocol: HTTPS (HTTP redirects to HTTPS)
Content-Type: application/json
Charset: UTF-8
```

### 1.2 API Endpoints Summary

| Category | Endpoints | Status |
|----------|-----------|--------|
| Analytics | 5 endpoints | ✅ Active |
| Reports | 5 endpoints | ✅ Active |
| Comparison | 3 endpoints | ✅ Active |
| Audit Logs | 4 endpoints | ✅ Active |
| Schedules | 4 endpoints | ✅ Active |
| Sessions | 3 endpoints | ✅ Active |
| **Total** | **24+ endpoints** | **✅ Production** |

### 1.3 API Standards

- **RESTful Design**: Standard HTTP methods (GET, POST, PUT, DELETE)
- **JSON Format**: All requests/responses use JSON
- **Pagination**: Cursor-based or offset-based pagination
- **Timestamps**: ISO 8601 format (2025-01-15T14:30:00Z)
- **Status Codes**: Standard HTTP status codes (200, 201, 400, 401, 403, 404, 429, 500, 503)

---

## 2. Authentication

### 2.1 Authentication Methods

**Method 1: API Token (Recommended for applications)**
```
Authorization: Bearer {token}

Example:
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Method 2: Session-Based (Web browsers)**
```
Cookie: XSRF-TOKEN={token}
X-XSRF-TOKEN: {token}
```

**Method 3: OAuth 2.0 (Future)**
```
Authorization: Bearer {oauth_token}
Refresh-Token: {refresh_token}
```

### 2.2 Obtaining API Tokens

**Create Token**:
```bash
# Via web interface
POST https://api.edms.local/api/tokens
Content-Type: application/json

{
  "name": "My Application",
  "abilities": ["view-analytics", "create-reports"]
}

Response:
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_name": "My Application",
    "created_at": "2025-01-15T14:30:00Z"
  }
}
```

**Token Abilities**:
```
- view-analytics       (read analytics data)
- create-reports       (generate new reports)
- download-reports     (download generated reports)
- manage-schedules     (create/edit/delete schedules)
- view-audit-logs      (access audit trail)
- admin                (full access)
```

### 2.3 Token Expiration

```
Default Expiration: 365 days
Refresh: Not automatic (create new token)
Revocation: DELETE /api/tokens/{id}
```

### 2.4 Test Credentials

```
For Development/Testing:
Email: test@edms.local
Password: testing123

Token (Test):
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IlRlc3QgVXNlciIsImlhdCI6MTUxNjIzOTAyMn0...
```

---

## 3. Authorization & Permissions

### 3.1 Permission Model

```
Role-Based Access Control (RBAC)

Roles:
- super_admin      → All permissions
- admin            → Administrative access
- manager          → Department management
- user             → Basic access

Department-Based Filtering:
- Users see only their department's data
- Managers see department + subordinate data
- Admins see all data
```

### 3.2 Permission Checks

**Check Permission**:
```bash
GET /api/permissions/check
Authorization: Bearer {token}

Query Parameters:
- permission: string (required)
- resource: string (optional)
- resource_id: integer (optional)

Response:
{
  "success": true,
  "data": {
    "permission": "view-analytics",
    "granted": true,
    "reason": "user has view-analytics permission"
  }
}
```

### 3.3 Common Authorization Errors

**403 Forbidden**:
```json
{
  "success": false,
  "message": "Unauthorized",
  "code": "UNAUTHORIZED",
  "error": "You do not have permission to perform this action"
}
```

**401 Unauthorized**:
```json
{
  "success": false,
  "message": "Unauthenticated",
  "code": "UNAUTHENTICATED",
  "error": "Token not provided or invalid"
}
```

---

## 4. Error Handling

### 4.1 HTTP Status Codes

| Code | Meaning | Example |
|------|---------|---------|
| 200 | OK | Request successful |
| 201 | Created | Resource created |
| 204 | No Content | Success with no content |
| 400 | Bad Request | Invalid parameters |
| 401 | Unauthorized | Missing/invalid token |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource doesn't exist |
| 409 | Conflict | Duplicate/concurrent issue |
| 422 | Unprocessable Entity | Validation failure |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Server Error | Internal error |
| 503 | Service Unavailable | Maintenance mode |

### 4.2 Error Response Format

**Standard Error**:
```json
{
  "success": false,
  "message": "Validation failed",
  "code": "VALIDATION_ERROR",
  "errors": {
    "period_days": [
      "The period_days must be between 1 and 365",
      "The period_days field is required"
    ],
    "metric": [
      "The metric must be one of: sessions, users, duration"
    ]
  }
}
```

**Server Error**:
```json
{
  "success": false,
  "message": "Internal Server Error",
  "code": "INTERNAL_ERROR",
  "error": "An unexpected error occurred",
  "trace_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479"
}
```

### 4.3 Validation Errors

```json
{
  "success": false,
  "message": "The given data was invalid",
  "code": "VALIDATION_FAILED",
  "errors": {
    "email": ["The email must be a valid email address"],
    "name": ["The name field is required"],
    "date_from": ["The date from must be a valid date"]
  }
}
```

---

## 5. Response Formats

### 5.1 Success Response

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    /* endpoint-specific data */
  }
}
```

### 5.2 Collection Response with Pagination

```json
{
  "success": true,
  "message": "Data retrieved successfully",
  "data": [
    { "id": 1, "name": "Item 1" },
    { "id": 2, "name": "Item 2" }
  ],
  "pagination": {
    "total": 1000,
    "per_page": 50,
    "current_page": 1,
    "last_page": 20,
    "from": 1,
    "to": 50,
    "has_more": true,
    "path": "/api/resource",
    "next_page_url": "https://api.edms.local/api/resource?page=2",
    "prev_page_url": null
  }
}
```

### 5.3 Empty Response

```json
{
  "success": true,
  "message": "No data available",
  "data": []
}
```

---

## 6. Rate Limiting

### 6.1 Rate Limit Headers

All responses include rate limit information in headers:

```
X-RateLimit-Limit: 100              (Requests per minute)
X-RateLimit-Remaining: 87           (Requests remaining)
X-RateLimit-Reset: 1639594920       (Unix timestamp when limit resets)
Retry-After: 42                     (Seconds to wait before retrying)
```

### 6.2 Rate Limits by Plan

| Plan | Requests/Min | Burst | Concurrent |
|------|-------------|-------|-----------|
| Free | 30 | 5 | 1 |
| Pro | 100 | 20 | 3 |
| Enterprise | 500 | 100 | 10 |
| Admin | Unlimited | - | - |

### 6.3 Rate Limit Exceeded

```bash
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1639594920
Retry-After: 42

{
  "success": false,
  "message": "Rate limit exceeded",
  "code": "RATE_LIMIT_EXCEEDED",
  "error": "Too many requests. Please retry after 42 seconds.",
  "retry_after": 42
}
```

---

## 7. Analytics API

### 7.1 Session Statistics Endpoint

**Get Session Analytics**:
```
POST /api/activity-analytics/sessions
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "period_days": 30,
  "granularity": "daily",         // daily, hourly, weekly
  "date_from": "2025-01-01",
  "date_to": "2025-01-31",
  "department_id": null,
  "user_id": null
}

Response (200 OK):
{
  "success": true,
  "message": "Session statistics retrieved",
  "data": {
    "summary": {
      "total_sessions": 1250,
      "unique_users": 145,
      "total_duration_hours": 2840,
      "avg_session_duration_minutes": 136.3,
      "peak_hour": "14:00",
      "peak_day": "Wednesday"
    },
    "by_date": [
      {
        "date": "2025-01-15",
        "sessions": 42,
        "users": 38,
        "duration_minutes": 5460,
        "avg_duration": 130
      }
    ],
    "by_hour": [
      {
        "hour": "09:00",
        "sessions": 156,
        "users": 94,
        "duration_minutes": 280
      }
    ]
  }
}
```

### 7.2 Activity Trends Endpoint

**Get Activity Trends**:
```
POST /api/activity-analytics/trends
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "period_days": 30,
  "metric": "sessions",           // sessions, users, duration, actions
  "granularity": "daily",
  "compare_period": false,
  "previous_period_days": 30
}

Response (200 OK):
{
  "success": true,
  "data": {
    "current_period": [
      {
        "date": "2025-01-15",
        "value": 245,
        "change": 12.5,
        "trend": "up"
      }
    ],
    "previous_period": [
      {
        "date": "2024-12-15",
        "value": 218
      }
    ],
    "summary": {
      "current_total": 7350,
      "previous_total": 6540,
      "change_percent": 12.4,
      "trend": "up"
    }
  }
}
```

### 7.3 Top Users Endpoint

**Get Top Users**:
```
POST /api/activity-analytics/top-users
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "period_days": 30,
  "limit": 20,
  "metric": "sessions",           // sessions, duration, actions
  "order": "desc"
}

Response (200 OK):
{
  "success": true,
  "data": {
    "users": [
      {
        "id": 1,
        "name": "Musa Ali",
        "email": "john@edms.local",
        "department": "Land Administration",
        "sessions": 156,
        "duration_hours": 312,
        "avg_session_hours": 2.0,
        "last_activity": "2025-01-15T14:30:00Z"
      }
    ]
  }
}
```

### 7.4 Device Analytics Endpoint

**Get Device Analytics**:
```
POST /api/activity-analytics/devices
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "period_days": 30
}

Response (200 OK):
{
  "success": true,
  "data": {
    "by_device": [
      {
        "device": "desktop",
        "count": 892,
        "percentage": 71.4,
        "users": 112
      },
      {
        "device": "mobile",
        "count": 258,
        "percentage": 20.6,
        "users": 48
      },
      {
        "device": "tablet",
        "count": 100,
        "percentage": 8.0,
        "users": 15
      }
    ],
    "by_browser": [
      {
        "browser": "Chrome",
        "count": 756,
        "percentage": 60.5,
        "users": 98
      }
    ],
    "by_platform": [
      {
        "platform": "Windows",
        "count": 945,
        "percentage": 75.6,
        "users": 125
      }
    ]
  }
}
```

### 7.5 Peak Hours Endpoint

**Get Peak Hours**:
```
POST /api/activity-analytics/peak-hours
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "period_days": 30
}

Response (200 OK):
{
  "success": true,
  "data": {
    "hourly_distribution": [
      {
        "hour": "09:00",
        "sessions": 156,
        "users": 94,
        "avg_duration": 95
      },
      {
        "hour": "10:00",
        "sessions": 189,
        "users": 112,
        "avg_duration": 102
      }
    ],
    "peak_hours": [
      {
        "hour": "14:00",
        "sessions": 245,
        "users": 145
      }
    ],
    "off_peak_hours": [
      {
        "hour": "02:00",
        "sessions": 12,
        "users": 8
      }
    ]
  }
}
```

---

## 8. Reports API

### 8.1 Generate Report Endpoint

**Generate New Report**:
```
POST /api/activity-reports/generate
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "report_type": "activity_summary",    // See types below
  "title": "January Activity Summary",
  "period_days": 30,
  "date_from": "2025-01-01",
  "date_to": "2025-01-31",
  "format": "pdf",                      // pdf, csv, excel
  "include_charts": true,
  "department_id": null,
  "filters": {
    "user_ids": [1, 2, 3],
    "actions": ["CREATED", "UPDATED"],
    "min_sessions": 10
  }
}

Report Types:
- activity_summary      (Overview of all activity)
- user_activity         (Per-user breakdown)
- audit_trail           (Detailed audit log)
- device_analysis       (Device distribution)
- performance_metrics   (Performance data)
- compliance_report     (Compliance data)

Response (202 Accepted):
{
  "success": true,
  "message": "Report generation started",
  "data": {
    "report_id": 1,
    "status": "processing",
    "title": "January Activity Summary",
    "created_at": "2025-01-15T14:30:00Z",
    "estimated_completion": "2025-01-15T14:33:00Z"
  }
}
```

### 8.2 List Reports Endpoint

**List Reports**:
```
GET /api/activity-reports
Authorization: Bearer {token}

Query Parameters:
- page: integer (default: 1)
- per_page: integer (default: 25, max: 100)
- report_type: string (optional)
- status: string (optional)
- sort_by: string (default: -created_at)
- search: string (optional, searches title)

Response (200 OK):
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "January Activity Summary",
      "report_type": "activity_summary",
      "format": "pdf",
      "status": "completed",
      "file_size": 256000,
      "record_count": 1250,
      "created_at": "2025-01-15T14:30:00Z",
      "completed_at": "2025-01-15T14:32:00Z",
      "download_url": "/api/activity-reports/1/download"
    }
  ],
  "pagination": {
    "total": 50,
    "per_page": 25,
    "current_page": 1,
    "last_page": 2
  }
}
```

### 8.3 Get Report Details Endpoint

**Get Report Details**:
```
GET /api/activity-reports/{id}
Authorization: Bearer {token}

Response (200 OK):
{
  "success": true,
  "data": {
    "id": 1,
    "title": "January Activity Summary",
    "report_type": "activity_summary",
    "format": "pdf",
    "status": "completed",
    "file_size": 256000,
    "record_count": 1250,
    "created_by": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@edms.local"
    },
    "created_at": "2025-01-15T14:30:00Z",
    "completed_at": "2025-01-15T14:32:00Z",
    "expires_at": "2025-03-15T14:32:00Z",
    "filters": {
      "period_days": 30,
      "department_id": null,
      "user_ids": []
    }
  }
}
```

### 8.4 Download Report Endpoint

**Download Report**:
```
GET /api/activity-reports/{id}/download
Authorization: Bearer {token}

Query Parameters:
- format: string (override original format)

Response (200 OK):
Content-Type: application/pdf | text/csv | application/vnd.ms-excel
Content-Disposition: attachment; filename="activity_report_001.pdf"
Content-Length: 256000

(Binary file content)
```

### 8.5 Delete Report Endpoint

**Delete Report**:
```
DELETE /api/activity-reports/{id}
Authorization: Bearer {token}

Response (200 OK):
{
  "success": true,
  "message": "Report deleted successfully"
}
```

---

## 9. Comparison API

### 9.1 Compare Periods Endpoint

**Compare Two Periods**:
```
POST /api/report-comparison/compare
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "current_period_days": 30,
  "previous_period_days": 30,
  "metrics": [
    "total_sessions",
    "unique_users",
    "total_duration",
    "avg_duration",
    "peak_hour"
  ],
  "date_to": "2025-01-15",
  "department_id": null
}

Response (200 OK):
{
  "success": true,
  "data": {
    "current_period": {
      "start_date": "2025-01-15",
      "end_date": "2025-02-14",
      "total_sessions": 1250,
      "unique_users": 145,
      "total_duration_hours": 2840,
      "avg_session_duration_minutes": 136.3
    },
    "previous_period": {
      "start_date": "2024-12-15",
      "end_date": "2025-01-14",
      "total_sessions": 1180,
      "unique_users": 142,
      "total_duration_hours": 2680,
      "avg_session_duration_minutes": 136.8
    },
    "comparison": {
      "total_sessions": {
        "current": 1250,
        "previous": 1180,
        "change": 70,
        "change_percent": 5.9,
        "trend": "up"
      },
      "unique_users": {
        "current": 145,
        "previous": 142,
        "change": 3,
        "change_percent": 2.1,
        "trend": "up"
      },
      "total_duration_hours": {
        "current": 2840,
        "previous": 2680,
        "change": 160,
        "change_percent": 5.97,
        "trend": "up"
      }
    }
  }
}
```

### 9.2 Export Comparison as PDF Endpoint

**Export Comparison**:
```
POST /api/report-comparison/export-pdf
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "current_period_days": 30,
  "previous_period_days": 30,
  "metrics": ["all"],
  "include_charts": true,
  "date_to": "2025-01-15"
}

Response (200 OK):
Content-Type: application/pdf
Content-Disposition: attachment; filename="comparison_2025-01-15.pdf"

(Binary PDF content)
```

### 9.3 Get Comparison UI Data Endpoint

**Get UI Data**:
```
GET /api/report-comparison
Authorization: Bearer {token}

Response (200 OK):
{
  "success": true,
  "data": {
    "available_metrics": [
      "total_sessions",
      "unique_users",
      "total_duration",
      "avg_duration",
      "peak_hour",
      "device_distribution"
    ],
    "default_comparison": {
      "current_period_days": 30,
      "previous_period_days": 30
    }
  }
}
```

---

## 10. Audit Logs API

### 10.1 List Audit Logs Endpoint

**Get Audit Logs**:
```
GET /api/audit-logs
Authorization: Bearer {token}

Query Parameters:
- page: integer (default: 1)
- per_page: integer (default: 50, max: 250)
- user_id: integer (optional)
- action: string (optional - CREATED, UPDATED, DELETED, etc.)
- resource_type: string (optional)
- date_from: date (optional)
- date_to: date (optional)
- sort_by: string (default: -created_at)

Response (200 OK):
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 5,
      "user_name": "Musa Ali",
      "action": "CREATED",
      "resource_type": "report",
      "resource_id": 123,
      "old_values": null,
      "new_values": {
        "title": "January Report",
        "status": "processing"
      },
      "ip_address": "192.168.1.100",
      "user_agent": "Mozilla/5.0 ...",
      "created_at": "2025-01-15T14:30:00Z"
    }
  ],
  "pagination": {
    "total": 5000,
    "per_page": 50,
    "current_page": 1,
    "last_page": 100
  }
}
```

### 10.2 Get Audit Log Details Endpoint

**Get Log Details**:
```
GET /api/audit-logs/{id}
Authorization: Bearer {token}

Response (200 OK):
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 5,
    "user_name": "Musa Ali",
    "user_email": "john@edms.local",
    "action": "CREATED",
    "action_display": "Created",
    "resource_type": "report",
    "resource_type_display": "Report",
    "resource_id": 123,
    "old_values": null,
    "new_values": {
      "title": "January Report",
      "format": "pdf",
      "status": "processing",
      "record_count": 1250
    },
    "changed_fields": ["title", "format", "status", "record_count"],
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0 Windows NT 10.0; Win64; x64",
    "device": "desktop",
    "browser": "Chrome",
    "platform": "Windows",
    "created_at": "2025-01-15T14:30:00Z"
  }
}
```

### 10.3 Get Resource Audit History Endpoint

**Get Resource History**:
```
GET /api/audit-logs/resource/{resource_type}/{resource_id}
Authorization: Bearer {token}

Query Parameters:
- page: integer (default: 1)
- per_page: integer (default: 50)

Response (200 OK):
{
  "success": true,
  "data": {
    "resource_type": "report",
    "resource_id": 123,
    "history": [
      {
        "id": 1,
        "action": "CREATED",
        "action_display": "Created",
        "user_name": "Musa Ali",
        "old_values": null,
        "new_values": { "title": "Report 1", "status": "draft" },
        "created_at": "2025-01-15T14:00:00Z"
      },
      {
        "id": 2,
        "action": "UPDATED",
        "action_display": "Updated",
        "user_name": "Musa Ali",
        "old_values": { "status": "draft" },
        "new_values": { "status": "processing" },
        "created_at": "2025-01-15T14:30:00Z"
      }
    ]
  }
}
```

### 10.4 Get User Audit Trail Endpoint

**Get User Audit Trail**:
```
GET /api/audit-logs/user/{user_id}
Authorization: Bearer {token}

Query Parameters:
- page: integer (default: 1)
- per_page: integer (default: 50)
- period_days: integer (default: 90)

Response (200 OK):
{
  "success": true,
  "data": {
    "user_id": 5,
    "user_name": "Musa Ali",
    "total_actions": 245,
    "period_days": 90,
    "actions": [
      {
        "id": 245,
        "action": "UPDATED",
        "resource_type": "report",
        "resource_id": 123,
        "created_at": "2025-01-15T14:30:00Z"
      }
    ]
  }
}
```

---

## 11. Scheduled Reports API

### 11.1 List Schedules Endpoint

**Get Scheduled Reports**:
```
GET /api/scheduled-reports
Authorization: Bearer {token}

Query Parameters:
- page: integer (default: 1)
- per_page: integer (default: 25)
- enabled: boolean (optional)
- report_type: string (optional)
- sort_by: string (default: -created_at)

Response (200 OK):
{
  "success": true,
  "data": [
    {
      "id": 1,
      "report_type": "activity_summary",
      "frequency": "daily",
      "format": "pdf",
      "enabled": true,
      "next_execution": "2025-01-16T09:00:00Z",
      "last_execution": "2025-01-15T09:00:00Z",
      "execution_count": 15,
      "failed_count": 0,
      "recipients": ["admin@edms.local", "manager@edms.local"],
      "created_at": "2025-01-01T00:00:00Z"
    }
  ],
  "pagination": {
    "total": 12,
    "per_page": 25,
    "current_page": 1
  }
}
```

### 11.2 Create Schedule Endpoint

**Create Scheduled Report**:
```
POST /api/scheduled-reports
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "report_type": "activity_summary",
  "title": "Daily Activity Report",
  "frequency": "daily",                 // daily, weekly, monthly
  "time_of_day": "09:00",              // 24-hour format
  "day_of_week": null,                 // For weekly (0-6, 0=Sunday)
  "day_of_month": null,                // For monthly (1-31)
  "format": "pdf",                     // pdf, csv, excel
  "enabled": true,
  "recipients": [
    "admin@edms.local",
    "manager@edms.local"
  ],
  "filters": {
    "period_days": 30,
    "department_id": null
  }
}

Response (201 Created):
{
  "success": true,
  "message": "Schedule created successfully",
  "data": {
    "id": 1,
    "report_type": "activity_summary",
    "frequency": "daily",
    "next_execution": "2025-01-16T09:00:00Z",
    "created_at": "2025-01-15T14:30:00Z"
  }
}
```

### 11.3 Update Schedule Endpoint

**Update Scheduled Report**:
```
PUT /api/scheduled-reports/{id}
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "frequency": "weekly",
  "time_of_day": "10:00",
  "recipients": ["admin@edms.local"],
  "enabled": true
}

Response (200 OK):
{
  "success": true,
  "message": "Schedule updated successfully",
  "data": {
    "id": 1,
    "report_type": "activity_summary",
    "frequency": "weekly",
    "next_execution": "2025-01-22T10:00:00Z"
  }
}
```

### 11.4 Delete Schedule Endpoint

**Delete Scheduled Report**:
```
DELETE /api/scheduled-reports/{id}
Authorization: Bearer {token}

Response (200 OK):
{
  "success": true,
  "message": "Schedule deleted successfully"
}
```

---

## 12. Sessions API

### 12.1 Get Current Session Endpoint

**Get Current Session**:
```
GET /api/sessions/current
Authorization: Bearer {token}

Response (200 OK):
{
  "success": true,
  "data": {
    "id": 42,
    "user_id": 5,
    "ip_address": "192.168.1.100",
    "device": "desktop",
    "browser": "Chrome",
    "platform": "Windows",
    "login_time": "2025-01-15T09:30:00Z",
    "last_seen_at": "2025-01-15T14:30:00Z",
    "status": "Online",
    "duration_minutes": 300,
    "session_token": "abc123..."
  }
}
```

### 12.2 List Active Sessions Endpoint

**List Active Sessions**:
```
GET /api/sessions/active
Authorization: Bearer {token}
(Admin only)

Query Parameters:
- page: integer (default: 1)
- per_page: integer (default: 50)

Response (200 OK):
{
  "success": true,
  "data": [
    {
      "id": 42,
      "user_id": 5,
      "user_name": "Musa Ali",
      "ip_address": "192.168.1.100",
      "device": "desktop",
      "browser": "Chrome",
      "login_time": "2025-01-15T09:30:00Z",
      "last_seen_at": "2025-01-15T14:30:00Z",
      "status": "Online",
      "duration_minutes": 300
    }
  ]
}
```

### 12.3 End Session Endpoint

**End Session**:
```
POST /api/sessions/{id}/end
Authorization: Bearer {token}
(Admin or session owner)

Response (200 OK):
{
  "success": true,
  "message": "Session ended successfully",
  "data": {
    "id": 42,
    "logout_time": "2025-01-15T14:30:00Z",
    "duration_minutes": 300,
    "status": "Offline"
  }
}
```

---

## 13. Code Examples

### 13.1 cURL Examples

**Get Session Analytics**:
```bash
curl -X POST https://api.edms.local/api/activity-analytics/sessions \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
  -H "Content-Type: application/json" \
  -d '{
    "period_days": 30,
    "granularity": "daily"
  }'
```

**Generate Report**:
```bash
curl -X POST https://api.edms.local/api/activity-reports/generate \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
  -H "Content-Type: application/json" \
  -d '{
    "report_type": "activity_summary",
    "title": "January Summary",
    "period_days": 30,
    "format": "pdf"
  }'
```

**Download Report**:
```bash
curl -X GET https://api.edms.local/api/activity-reports/1/download \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
  -o report.pdf
```

### 13.2 JavaScript/Fetch Examples

**Get Analytics Data**:
```javascript
async function getSessionAnalytics() {
  const response = await fetch('https://api.edms.local/api/activity-analytics/sessions', {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer ' + apiToken,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      period_days: 30,
      granularity: 'daily'
    })
  });

  const data = await response.json();
  console.log(data);
  return data;
}
```

**Handle Rate Limiting**:
```javascript
async function apiCallWithRetry(url, options, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    const response = await fetch(url, options);
    
    // Check rate limit
    if (response.status === 429) {
      const retryAfter = response.headers.get('Retry-After');
      await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
      continue;
    }
    
    return response;
  }
  throw new Error('Max retries exceeded');
}
```

### 13.3 PHP/Laravel Examples

**Get Analytics Using Guzzle**:
```php
$client = new GuzzleHttp\Client();

$response = $client->post('https://api.edms.local/api/activity-analytics/sessions', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json'
    ],
    'json' => [
        'period_days' => 30,
        'granularity' => 'daily'
    ]
]);

$data = json_decode($response->getBody(), true);
```

**Generate Report**:
```php
$response = $client->post('https://api.edms.local/api/activity-reports/generate', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json'
    ],
    'json' => [
        'report_type' => 'activity_summary',
        'title' => 'January Summary',
        'period_days' => 30,
        'format' => 'pdf'
    ]
]);
```

---

## 14. Pagination & Filtering

### 14.1 Pagination

**Offset-Based Pagination**:
```
GET /api/resource?page=2&per_page=50

Response includes:
{
  "pagination": {
    "total": 1000,
    "per_page": 50,
    "current_page": 2,
    "last_page": 20,
    "from": 51,
    "to": 100,
    "path": "/api/resource",
    "next_page_url": "...?page=3",
    "prev_page_url": "...?page=1"
  }
}
```

**Cursor-Based Pagination**:
```
GET /api/resource?cursor=eyJpZCI6MTU=&limit=50
```

### 14.2 Filtering

**Common Filters**:
```
GET /api/audit-logs?user_id=5&action=CREATED&date_from=2025-01-01&date_to=2025-01-31

GET /api/activity-reports?report_type=activity_summary&status=completed&sort_by=-created_at

GET /api/scheduled-reports?enabled=true&frequency=daily
```

### 14.3 Sorting

**Sort by Field**:
```
GET /api/resource?sort_by=created_at        (ascending)
GET /api/resource?sort_by=-created_at       (descending)
GET /api/resource?sort_by=name,created_at   (multiple fields)
```

---

## 15. Webhook Events

### 15.1 Webhook Registration

**Register Webhook**:
```
POST /api/webhooks
Authorization: Bearer {token}

{
  "event": "report.generated",
  "url": "https://your-app.com/webhooks/reports",
  "secret": "webhook_secret_key",
  "active": true
}
```

### 15.2 Webhook Events

**Available Events**:
- `report.generated` - Report generation complete
- `report.failed` - Report generation failed
- `schedule.executed` - Scheduled report executed
- `schedule.failed` - Scheduled report failed
- `audit.created` - New audit log entry
- `user.online` - User came online
- `user.offline` - User went offline

### 15.3 Webhook Payload

**Report Generated Event**:
```json
{
  "event": "report.generated",
  "timestamp": "2025-01-15T14:32:00Z",
  "data": {
    "id": 1,
    "title": "January Summary",
    "report_type": "activity_summary",
    "status": "completed",
    "file_size": 256000,
    "download_url": "https://api.edms.local/api/activity-reports/1/download"
  }
}
```

---

## API Changelog

### Version 1.0 (Current)
- Initial release with 24+ endpoints
- Full analytics suite
- Report generation and scheduling
- Audit trail integration
- Session management

---

## Support & Documentation

- **API Base URL**: https://api.edms.local/api/
- **Status Page**: https://status.edms.local/
- **Support**: api-support@edms.local
- **Documentation**: https://docs.edms.local/api/
- **GitHub Issues**: https://github.com/edms/api/issues

---

*Generated: November 10, 2025*
*Version: 1.0*
*Status: Complete & Verified*
