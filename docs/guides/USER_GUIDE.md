# USER ACTIVITY LOG SYSTEM - USER GUIDE

## Table of Contents

1. [Getting Started](#getting-started)
2. [Dashboard Overview](#dashboard-overview)
3. [Analytics Features](#analytics-features)
4. [Report Generation](#report-generation)
5. [Report Scheduling](#report-scheduling)
6. [Data Export](#data-export)
7. [Audit Logs](#audit-logs)
8. [Session Management](#session-management)
9. [Permissions & Access](#permissions--access)
10. [Common Tasks](#common-tasks)
11. [FAQ](#faq)
12. [Troubleshooting](#troubleshooting)

---

## 1. Getting Started

### 1.1 System Access

**URL**: https://analytics.edms.local

**Login**:
1. Open your browser to the system URL
2. Enter your email address
3. Click "Send Login Link"
4. Check your email for login link
5. Click link to authenticate
6. You're logged in! The system remembers you for 30 days

### 1.2 First-Time Setup

**Complete Your Profile**:
1. Go to Settings → Profile
2. Add your phone number (for alerts)
3. Set your timezone (for report scheduling)
4. Choose notification preferences
5. Click Save

**Set Dashboard Preferences**:
1. Go to Dashboard
2. Click "Customize Layout" (top right)
3. Drag cards to arrange
4. Choose time range (default: Last 7 days)
5. Click "Save Layout"

### 1.3 Understanding Roles

```
Role: Viewer
├─ Can view dashboard
├─ Can view reports
├─ Cannot create/schedule
└─ Cannot edit data

Role: Analyst
├─ Can view dashboard
├─ Can view/create reports
├─ Can schedule reports
├─ Cannot edit user data
└─ Cannot view sensitive fields

Role: Administrator
├─ Full access to all features
├─ Can manage users
├─ Can configure system settings
├─ Can delete data
└─ Can view audit logs
```

---

## 2. Dashboard Overview

### 2.1 Dashboard Layout

```
┌─────────────────────────────────────────────────────────┐
│ User Activity Log System Dashboard                      │
├─────────────────────────────────────────────────────────┤
│                                                          │
│ Time Range: [Last 7 Days ▼]    [Refresh ↻]  [Export]  │
│                                                          │
├─────────────────────────────────────────────────────────┤
│                                                          │
│ ┌──────────────────┐  ┌──────────────────┐             │
│ │ Total Sessions   │  │ Active Users     │             │
│ │ 24,531           │  │ 1,847            │             │
│ │ ↑ 12% from last  │  │ ↑ 8% from last   │             │
│ │ week             │  │ week             │             │
│ └──────────────────┘  └──────────────────┘             │
│                                                          │
│ ┌──────────────────────────────────────────────────┐   │
│ │ Session Trends (Last 7 Days)                     │   │
│ │                                                   │   │
│ │     ╱╲  ╱╲                                       │   │
│ │    ╱  ╲╱  ╲                                      │   │
│ │   ╱            ╲╱                                │   │
│ │                                                   │   │
│ │ Mon  Tue  Wed  Thu  Fri  Sat  Sun                │   │
│ └──────────────────────────────────────────────────┘   │
│                                                          │
│ ┌──────────────────┐  ┌──────────────────┐             │
│ │ Top Actions      │  │ Device Summary   │             │
│ │ • Login: 8.2K    │  │ Desktop: 65%     │             │
│ │ • View: 12.4K    │  │ Mobile: 30%      │             │
│ │ • Export: 1.2K   │  │ Tablet: 5%       │             │
│ └──────────────────┘  └──────────────────┘             │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

### 2.2 Key Dashboard Cards

**Total Sessions**
- Shows all user sessions in date range
- Includes both active and completed sessions
- Green indicator if trending up, red if down
- Click to see session detail report

**Active Users**
- Shows unique users with activity
- Excludes test accounts and crawlers
- Click to see user activity timeline
- Hover for daily breakdown

**Session Trends**
- Line chart showing daily session count
- Interactive: hover to see daily values
- Can zoom in/out on time range
- Toggle between sessions/revenue views

**Top Actions**
- Most frequently performed actions
- Ranked by frequency
- Shows action count and % of total
- Click action to see detailed breakdown

**Device Summary**
- Desktop, mobile, tablet breakdown
- Pie chart visualization
- Hover for exact percentages
- Click for device-specific reports

### 2.3 Customizing Dashboard

**Add/Remove Cards**:
1. Click "Customize Layout" (top right)
2. Toggle cards on/off with switch
3. Drag to reorder
4. Click "Save Layout"

**Change Time Range**:
1. Click "Last 7 Days" dropdown
2. Select: Today / Last 7 days / Last 30 days / Last 90 days / Custom
3. If custom: select start and end dates
4. Dashboard auto-updates

**Save Dashboard Version**:
1. Arrange dashboard as desired
2. Click "Save As Preset"
3. Give preset a name (e.g., "Executive Summary")
4. Choose visibility: Private / Share with team / Public
5. Later: Load preset from "Presets" menu

---

## 3. Analytics Features

### 3.1 Real-Time Analytics

**Accessing Real-Time Data**:
1. Go to Analytics → Real-Time
2. See activity happening right now
3. Green = active user, Gray = recent (< 5 min)
4. Click user to see live session

**Real-Time Features**:
```
Feature: Live Activity Stream
├─ Shows activity as it happens
├─ Auto-refreshes every 5 seconds
├─ Filters by action type
└─ Can export current snapshot

Feature: User Heat Map
├─ Shows which pages users are on right now
├─ Color intensity = # of users
├─ Click page to see user list
└─ Helps identify bottlenecks

Feature: System Health
├─ Server response time
├─ Database query time
├─ Cache hit ratio
└─ Error rate
```

### 3.2 Trend Analysis

**Accessing Trends**:
1. Go to Analytics → Trends
2. Select metric: Sessions / Users / Revenue / Engagement
3. Select period: Daily / Weekly / Monthly
4. Adjust date range with pickers

**Trend Features**:
```
Visualization Options:
├─ Line chart (default - good for trends)
├─ Bar chart (good for comparisons)
├─ Area chart (good for stacked data)
└─ Table (exact numbers)

Filtering:
├─ By device type
├─ By geography
├─ By user segment
└─ By action

Comparison:
├─ Compare to previous period
├─ Year-over-year comparison
└─ Show growth %, index
```

### 3.3 User Segmentation

**Creating Segments**:
1. Go to Analytics → Segments
2. Click "New Segment"
3. Name your segment (e.g., "Power Users")
4. Add criteria:
   - Session count > 10
   - Last active < 7 days
   - Device = Desktop
5. Click "Save Segment"

**Using Segments**:
1. Go to any report
2. Click "Add Filter"
3. Select "User Segment"
4. Choose your segment
5. Report auto-filters to segment

---

## 4. Report Generation

### 4.1 Built-In Reports

**Available Reports**:

```
Report: Session Summary
Purpose: Overview of all user sessions
Contains: Total sessions, unique users, avg duration, bounce rate
Export: PDF, Excel, CSV
```

```
Report: User Activity Breakdown
Purpose: Which actions users performed most
Contains: Action frequency, user breakdown, trends
Export: PDF, Excel, CSV
```

```
Report: Device & Browser Analysis
Purpose: What devices/browsers users use
Contains: Device mix, browser versions, OS breakdown
Export: PDF, Excel, CSV
```

```
Report: Geographic Distribution
Purpose: Where users are located
Contains: Country, region, city breakdown, visit heatmap
Export: PDF, Excel, CSV
```

```
Report: Engagement Metrics
Purpose: How engaged users are
Contains: Session duration, pages per session, return rate, churn
Export: PDF, Excel, CSV
```

### 4.2 Creating Custom Reports

**Step 1: Start Report**
1. Go to Reports → Create New Report
2. Name your report (e.g., "Q4 User Analysis")
3. Choose report type: Simple / Advanced / Pivot Table
4. Click Continue

**Step 2: Select Metrics**
- Check boxes for metrics you want:
  - Sessions, Users, Page Views, Actions
  - Revenue, Conversions, Engagement
  - Device, Browser, Geography

**Step 3: Add Filters** (Optional)
1. Click "Add Filter"
2. Choose dimension: Date, Device, Country, Action, User Segment
3. Set condition:
   - Equals / Not equals / Contains / In list
   - Select values
4. Click "Apply Filter"

**Step 4: Configure Visualization**
1. Choose chart type:
   - Table (for details)
   - Line chart (for trends)
   - Bar chart (for comparisons)
   - Pie chart (for percentages)
2. Choose grouping: By date / By dimension / No grouping
3. Preview displays below

**Step 5: Save Report**
1. Click "Save Report"
2. Choose:
   - Save to My Reports (private)
   - Share with team
   - Publish (organization-wide)
3. Report now appears in Reports list

### 4.3 Report Examples

**Example 1: Monthly User Engagement**

```
Steps:
1. Metrics: Sessions, Users, Avg Duration
2. Filter: Date is Last 30 days
3. Filter: User Segment = Active Users
4. Group: By week
5. Chart: Line chart showing trend
6. Name: "Monthly User Engagement"
```

**Example 2: Device Performance Comparison**

```
Steps:
1. Metrics: Sessions, Avg Response Time, Bounce Rate
2. Filter: Date is Last 7 days
3. Group: By device type
4. Chart: Bar chart (side-by-side)
5. Name: "Device Performance"
6. Share with: Performance team
```

---

## 5. Report Scheduling

### 5.1 Email Delivery

**Schedule a Report**:
1. Open or create a report
2. Click "Schedule" button (top right)
3. Name: "Weekly User Summary"
4. Recipients: your@email.com (add more with +)
5. Frequency:
   - Daily (default: 6 AM your timezone)
   - Weekly (choose day, default: Monday)
   - Monthly (choose date, default: 1st)
6. Format: PDF / Excel / CSV (PDF default)
7. Click "Save Schedule"

**Manage Schedules**:
1. Go to Reports → Scheduled Reports
2. View all your scheduled reports
3. Click to edit frequency/recipients
4. Or click delete to remove schedule

**Schedule Examples**:
```
Executive Summary
├─ Daily at 6 AM
├─ Recipients: execs@company.com, you@company.com
└─ Format: PDF

Performance Report
├─ Weekly on Friday at 9 AM
├─ Recipients: ops-team@company.com
└─ Format: Excel

Detailed Analysis
├─ Monthly on 1st at 8 AM
├─ Recipients: analytics-team@company.com
└─ Format: CSV
```

### 5.2 Advanced Scheduling

**Conditional Scheduling**:
1. Create report
2. Click "Schedule"
3. Toggle "Conditional Delivery" ON
4. Add condition: "Only send if X > Y"
   - Example: "Send if users > 1000"
5. Report only sends when condition met

**Recipient Groups**:
1. Go to Settings → Recipient Groups
2. Click "New Group"
3. Name: "Executive Team"
4. Add recipients: exec1@, exec2@, etc
5. Use group in report scheduling

---

## 6. Data Export

### 6.1 Export Options

**From Dashboard**:
1. Click "Export" button (top right)
2. Choose format: PDF / Excel / CSV
3. Choose what to include:
   - Current visible cards
   - All dashboard cards
   - Custom selection
4. Click "Export"

**From Reports**:
1. Open any report
2. Click "Export" (top right)
3. Choose format: PDF / Excel / CSV
4. Click "Export"

**From Tables**:
1. Click any table/list
2. Click "Export" (if available)
3. Choose format
4. Click "Export"

### 6.2 Export Formats

**PDF Export**:
- Best for: sharing, printing, archiving
- Includes: charts, formatting, headers/footers
- Size: 2-5 MB for typical reports
- Watermark: Shows "Confidential" if set

**Excel Export**:
- Best for: data analysis, further processing
- Includes: multiple sheets per report
- Size: 500 KB - 2 MB
- Features: formulas preserved, can edit

**CSV Export**:
- Best for: system integration, large datasets
- Includes: raw data only, no formatting
- Size: 100 KB - 1 MB
- Features: can open in any spreadsheet

---

## 7. Audit Logs

### 7.1 Accessing Audit Logs

**Who can access**:
- Administrators: Full access
- Analysts: Can see own activity
- Viewers: Cannot access

**How to access**:
1. Go to Settings → Audit Logs
2. View list of all activities (if admin)
3. Filter by:
   - Date range
   - User
   - Action type
   - Resource

### 7.2 Audit Log Contents

Each log entry shows:
```
Timestamp: 2025-01-15 14:30:22 UTC
User: john.smith@company.com
Action: Report Generated
Resource: "Q4 User Summary" (Report ID: 2847)
Details: Generated report with 5 filters
Result: Success
IP Address: 192.168.1.100
User Agent: Chrome 120.0 on Windows
```

**Common Actions Logged**:
```
Authentication:
├─ Login
├─ Logout
└─ Failed Login

Report Actions:
├─ Report Created
├─ Report Updated
├─ Report Deleted
├─ Report Downloaded
└─ Report Shared

Data Access:
├─ Dashboard Viewed
├─ Analytics Accessed
├─ Export Performed
└─ Audit Log Viewed

Admin Actions:
├─ User Added
├─ User Deleted
├─ Permission Changed
└─ System Setting Updated
```

### 7.3 Using Audit Logs

**Find User Activity**:
1. Go to Audit Logs
2. Filter by User: select specific user
3. Filter by Action: select action type
4. Filter by Date: last 30 days
5. View all activities for that user

**Export Audit Trail**:
1. Apply filters
2. Click "Export"
3. Choose Excel or CSV
4. Can then analyze in spreadsheet

---

## 8. Session Management

### 8.1 Active Sessions

**View Your Sessions**:
1. Go to Settings → Sessions
2. See list of active sessions
3. Each shows:
   - Device & browser
   - Location
   - Last activity time
   - "Sign out" button

**Sign Out Sessions**:
1. Find session to end
2. Click "Sign out from this device"
3. That session immediately ends
4. User will be logged out on next page load

### 8.2 Session Timeout

**Default Settings**:
- Desktop browser: 30 days idle timeout
- Mobile browser: 7 days idle timeout
- API token: 1 year or until revoked

**Adjust for Your Account**:
1. Go to Settings → Preferences
2. Set "Session Duration":
   - More secure: 1 hour
   - Balanced: 24 hours (default)
   - Less secure: 30 days
3. Click Save

**Remember Me**:
- Check "Remember me" on login page
- Session lasts 30 days
- On shared computers: don't check this

---

## 9. Permissions & Access

### 9.1 Understanding Your Access

**Check Your Role**:
1. Click your name (top right)
2. Go to "My Profile"
3. View your role and permissions
4. "Request access" if you need more

**Common Scenarios**:
```
I want to:               I need:
─────────────────────────────────────
View a dashboard         Viewer role
Create a report          Analyst role
Schedule reports         Analyst role
Delete a report          Admin role
Manage users            Admin role
View audit logs         Admin role (others' logs)
```

### 9.2 Requesting Access

**Request More Permissions**:
1. Go to Settings → Access Requests
2. Click "Request Permission"
3. Select permission needed:
   - View sensitive metrics
   - Create reports
   - Schedule reports
   - Export data
4. Add justification: "Need for Q4 analysis"
5. Submit
6. Request goes to your manager
7. Notification when approved/denied

---

## 10. Common Tasks

### 10.1 Daily Tasks

**Check Dashboard in Morning**:
1. Go to Dashboard
2. Review last 24 hours
3. Look for any red indicators or anomalies
4. Drill into if needed

**Generate Daily Report**:
1. Go to Reports
2. Select "Daily Summary"
3. Check it looks correct
4. Click Export → PDF
5. File saved to downloads

### 10.2 Weekly Tasks

**Review Weekly Metrics**:
1. Go to Analytics → Trends
2. Filter to last 7 days
3. Compare to previous week
4. Note any significant changes
5. Document findings

**Send Team Report**:
1. Open "Weekly Team Summary" report
2. Click "Email Now"
3. Enter recipient emails
4. Click Send
5. Report delivered immediately

### 10.3 Monthly Tasks

**Generate Monthly Report**:
1. Go to Reports → Create New
2. Set date range: first to last day of month
3. Add all key metrics
4. Add trend comparison
5. Save report
6. Schedule for next month

**Archive Old Reports**:
1. Go to Reports
2. Filter to > 90 days old
3. Select reports to keep
4. Click "Archive"
5. Archived reports hidden but searchable

---

## 11. FAQ

### 11.1 General Questions

**Q: What's the difference between "Sessions" and "Users"?**
A: A session is one visit. A user is one person. One user can have multiple sessions.

**Q: Why is my data delayed?**
A: Data updates every 5 minutes. Real-time data is 1-2 minutes delayed due to processing.

**Q: How far back is data stored?**
A: 2 years. Older data can be accessed via archives.

**Q: Can I delete my data?**
A: Only admins can delete data. Contact your admin if needed.

### 11.2 Report Questions

**Q: Can I schedule reports automatically?**
A: Yes! Use Report Scheduling. Go to any report → Schedule.

**Q: Can multiple people receive the same report?**
A: Yes! Add multiple email addresses separated by commas.

**Q: Can I customize which data appears in a report?**
A: Yes! Use Custom Report builder to add/remove metrics and filters.

**Q: Can I embed a report on my website/intranet?**
A: Yes! Click "Share" → "Embed" to get embed code.

### 11.3 Technical Questions

**Q: What's my login URL?**
A: https://analytics.edms.local

**Q: Do you have a mobile app?**
A: Not yet, but the website is fully mobile-responsive.

**Q: Is data encrypted?**
A: Yes! All data encrypted in transit (HTTPS) and at rest (AES-256).

**Q: Can I access this via API?**
A: Yes, for developers. Contact your admin for API credentials.

---

## 12. Troubleshooting

### 12.1 Login Issues

**"Invalid email or password"**
1. Check email is spelled correctly
2. Check you're not using CAPS LOCK
3. Click "Forgot password" to reset
4. Check spam folder for reset email

**"Session expired"**
1. This is normal after idle time (12-24 hours)
2. Click "Log in again"
3. Enter credentials
4. You're back in!

**"Cannot login from this location"**
1. Your IP is blocked for security
2. Contact your admin to whitelist your IP
3. They can unblock in minutes

### 12.2 Report Issues

**"Report won't generate"**
1. Check date range isn't too large (>2 years)
2. Try smaller date range first
3. Check filters are reasonable
4. Try again in 5 minutes

**"Export is taking too long"**
1. Large reports can take 1-2 minutes
2. Don't close the page or browser
3. Notification appears when done
4. Check downloads folder

**"Wrong data in report"**
1. Check filters are correct
2. Verify date range is what you intended
3. Try generating again (might be cache)
4. Contact support if still wrong

### 12.3 Access Issues

**"I can't see that report"**
1. Check if it's private or shared
2. Ask the owner to share with you
3. If it's your report, you should see it under "My Reports"

**"Permission denied"**
1. Your role doesn't allow this action
2. Go to Settings → Access Requests
3. Request the needed permission
4. Wait for approval from admin/manager

**"Dashboard card not showing data"**
1. The card might be filtered to empty result
2. Click card to see details and expand date range
3. If no data, the metric truly has no data

### 12.4 Getting Help

**Help Resources**:
```
Quick answers: Click "?" in app (bottom right)
├─ Tooltips explain each feature
├─ Video tutorials (5-10 minutes each)
└─ Knowledge base with articles

Email support: support@edms.local
├─ Response time: 4 hours business hours
├─ 24-hour support for critical issues
└─ Include screenshot if possible

Chat support: Slack #analytics-help
├─ Real-time help from team
├─ Faster for urgent issues
└─ Available 24/7

Phone support: +1-555-0100
├─ For critical production issues
├─ Available 8 AM - 8 PM ET
└─ Ask for name + incident number

Your Admin: [Name] - [email]
├─ For access/permission questions
├─ For account setup help
└─ Fastest for internal issues
```

---

## Quick Reference

**Most Common Actions**:
- View dashboard: https://analytics.edms.local
- Create report: Reports → Create New
- Schedule report: Open report → Schedule
- Export data: Click report → Export
- View audit logs: Settings → Audit Logs
- Change password: Settings → Profile
- Contact support: support@edms.local

**Time Ranges**:
- Today: Last 0-24 hours
- Last 7 days: Last 7 full days
- Last 30 days: Last 30 full days
- Last 90 days: Last 90 full days
- Custom: Pick any start/end date

**Export Formats**:
- PDF: Best for sharing/printing
- Excel: Best for analysis
- CSV: Best for integration

---

*Generated: November 10, 2025*
*Version: 1.0*
*Status: Complete & Ready for Users*
