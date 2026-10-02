<style>
/* ============================================================
           CSS VARIABLES & RESET
           ============================================================ */
        :root {
            --primary: #0f3b5e;
            --primary-light: #1a5276;
            --primary-dark: #0a2a42;
            --primary-50: #e8f0f8;
            --primary-100: #d1e1f0;
            --secondary: #2ecc71;
            --secondary-dark: #27ae60;
            --accent: #f39c12;
            --accent-dark: #e67e22;
            --danger: #e74c3c;
            --danger-dark: #c0392b;
            --warning: #f1c40f;
            --info: #3498db;
            --gray-50: #f8f9fa;
            --gray-100: #f1f3f5;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.10);
            --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.12);
            --radius: 12px;
            --radius-sm: 8px;
            --radius-xs: 4px;
            --transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 260px;
            --header-height: 64px;
            --font: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            font-size: 16px;
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font);
            background: var(--gray-50);
            color: var(--gray-800);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--gray-400);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-500);
        }

        /* ============================================================
           SIDEBAR
           ============================================================ */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--primary-dark);
            color: #fff;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: transform var(--transition);
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            flex-shrink: 0;
        }

        .sidebar-brand .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--secondary);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .sidebar-brand h1 {
            font-size: 18px;
            font-weight: 600;
            letter-spacing: -0.3px;
        }
        .sidebar-brand h1 small {
            font-weight: 400;
            font-size: 11px;
            opacity: 0.6;
            display: block;
            letter-spacing: 0.3px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 16px 12px 24px;
        }

        .sidebar-nav .nav-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: rgba(255, 255, 255, 0.3);
            padding: 16px 12px 8px;
            font-weight: 600;
        }

        .sidebar-nav .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            color: rgba(255, 255, 255, 0.65);
            text-decoration: none;
            cursor: pointer;
            transition: all var(--transition);
            font-size: 13.5px;
            font-weight: 500;
            position: relative;
            margin-bottom: 2px;
        }

        .sidebar-nav .nav-item:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        .sidebar-nav .nav-item.active {
            background: rgba(46, 204, 113, 0.2);
            color: #fff;
        }
        .sidebar-nav .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: var(--secondary);
            border-radius: 0 4px 4px 0;
        }

        .sidebar-nav .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .sidebar-nav .nav-item .badge {
            margin-left: auto;
            background: var(--danger);
            color: #fff;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .sidebar-nav .nav-item .badge.green {
            background: var(--secondary);
        }
        .sidebar-nav .nav-item .badge.yellow {
            background: var(--accent);
        }
        .sidebar-nav .nav-item .badge.blue {
            background: var(--info);
        }

        .sidebar-nav .nav-item.sub-item {
            padding-left: 44px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.5);
        }
        .sidebar-nav .nav-item.sub-item:hover {
            color: #fff;
        }
        .sidebar-nav .nav-item.sub-item.active {
            color: #fff;
            background: rgba(46, 204, 113, 0.15);
        }
        .sidebar-nav .nav-item.sub-item.active::before {
            left: 20px;
        }

        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            flex-shrink: 0;
        }

        .sidebar-footer .user-card {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-footer .user-card .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            color: var(--primary-dark);
        }

        .sidebar-footer .user-card .user-info {
            flex: 1;
        }
        .sidebar-footer .user-card .user-info .name {
            font-size: 14px;
            font-weight: 600;
        }
        .sidebar-footer .user-card .user-info .role {
            font-size: 11px;
            opacity: 0.6;
        }

        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ============================================================
           HEADER
           ============================================================ */
        .header {
            height: var(--header-height);
            background: #fff;
            border-bottom: 1px solid var(--gray-200);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.92);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-left .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: var(--gray-700);
            cursor: pointer;
            padding: 4px;
        }

        .header-left .page-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-900);
        }
        .header-left .page-title .sub {
            font-weight: 400;
            font-size: 13px;
            color: var(--gray-500);
            margin-left: 8px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-right .search-box {
            display: flex;
            align-items: center;
            background: var(--gray-100);
            border-radius: 24px;
            padding: 6px 16px;
            gap: 10px;
            transition: all var(--transition);
        }
        .header-right .search-box:focus-within {
            background: #fff;
            box-shadow: 0 0 0 2px var(--primary-100);
        }
        .header-right .search-box input {
            border: none;
            background: transparent;
            outline: none;
            font-size: 14px;
            padding: 6px 0;
            width: 180px;
            font-family: var(--font);
        }
        .header-right .search-box i {
            color: var(--gray-500);
        }

        .header-right .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: var(--gray-100);
            color: var(--gray-700);
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition);
            position: relative;
        }
        .header-right .icon-btn:hover {
            background: var(--gray-200);
        }
        .header-right .icon-btn .dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid #fff;
        }

        /* ============================================================
           PAGE CONTENT
           ============================================================ */
        .page-content {
            padding: 24px 32px 40px;
            flex: 1;
        }

        .page {
            display: none;
            animation: fadeUp 0.3s ease;
        }
        .page.active {
            display: block;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ============================================================
           BREADCRUMB
           ============================================================ */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--gray-500);
            margin-bottom: 16px;
            padding: 8px 0;
        }
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .breadcrumb .separator {
            color: var(--gray-300);
        }
        .breadcrumb .current {
            color: var(--gray-700);
            font-weight: 500;
        }

        /* ============================================================
           KPI CARDS
           ============================================================ */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .kpi-card {
            background: #fff;
            border-radius: var(--radius);
            padding: 20px 22px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: all var(--transition);
        }
        .kpi-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .kpi-card .kpi-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .kpi-card .kpi-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
            margin-top: 4px;
        }
        .kpi-card .kpi-change {
            font-size: 12px;
            font-weight: 600;
            margin-top: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 20px;
        }
        .kpi-card .kpi-change.up {
            color: var(--secondary-dark);
            background: #d5f5e3;
        }
        .kpi-card .kpi-change.down {
            color: var(--danger);
            background: #fadbd8;
        }
        .kpi-card .kpi-icon {
            float: right;
            font-size: 28px;
            color: var(--primary-100);
            opacity: 0.6;
        }

        /* ============================================================
           DASHBOARD GRID
           ============================================================ */
        .dash-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .dash-card {
            background: #fff;
            border-radius: var(--radius);
            padding: 20px 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .dash-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .dash-card .card-header h3 {
            font-size: 15px;
            font-weight: 600;
        }
        .dash-card .card-header a {
            font-size: 13px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }
        .dash-card .card-header a:hover {
            text-decoration: underline;
        }

        /* ============================================================
           WORKFLOW TIMELINE
           ============================================================ */
        .workflow-timeline {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .workflow-step {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .workflow-step .step-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
            font-weight: 600;
        }
        .workflow-step .step-icon.done {
            background: var(--secondary);
            color: #fff;
        }
        .workflow-step .step-icon.pending {
            background: var(--accent);
            color: #fff;
        }
        .workflow-step .step-icon.waiting {
            background: var(--gray-300);
            color: var(--gray-600);
        }
        .workflow-step .step-icon.fail {
            background: var(--danger);
            color: #fff;
        }

        .workflow-step .step-info {
            flex: 1;
        }
        .workflow-step .step-info .step-name {
            font-size: 14px;
            font-weight: 500;
        }
        .workflow-step .step-info .step-status {
            font-size: 12px;
            color: var(--gray-500);
        }

        .workflow-step .step-time {
            font-size: 12px;
            color: var(--gray-500);
        }

        /* ============================================================
           ACTIVITY FEED
           ============================================================ */
        .activity-feed {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .activity-item {
            display: flex;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--gray-100);
        }
        .activity-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .activity-item .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-50);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 13px;
        }

        .activity-item .activity-content {
            flex: 1;
        }
        .activity-item .activity-content .text {
            font-size: 14px;
            color: var(--gray-700);
        }
        .activity-item .activity-content .text strong {
            color: var(--gray-900);
        }
        .activity-item .activity-content .time {
            font-size: 12px;
            color: var(--gray-500);
        }

        /* ============================================================
           BUTTONS
           ============================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 500;
            font-family: var(--font);
            border: none;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .btn-primary:hover {
            background: var(--primary-light);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-success {
            background: var(--secondary);
            color: #fff;
        }
        .btn-success:hover {
            background: var(--secondary-dark);
        }

        .btn-danger {
            background: var(--danger);
            color: #fff;
        }
        .btn-danger:hover {
            background: var(--danger-dark);
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid var(--gray-300);
            color: var(--gray-700);
        }
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }

        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
        }
        .btn-xs {
            padding: 3px 10px;
            font-size: 11px;
        }

        /* ============================================================
           TABLES
           ============================================================ */
        .table-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .table-toolbar .left {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .table-wrapper {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .table-scroll {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        table thead {
            background: var(--gray-50);
        }
        table thead th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }

        table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            vertical-align: middle;
        }
        table tbody tr:hover {
            background: var(--gray-50);
        }
        table tbody tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        .status-badge.active {
            background: #d5f5e3;
            color: #1a7a3a;
        }
        .status-badge.active .dot {
            background: #1a7a3a;
        }
        .status-badge.pending {
            background: #fdebd0;
            color: #b9770e;
        }
        .status-badge.pending .dot {
            background: #b9770e;
        }
        .status-badge.completed {
            background: #d6eaf8;
            color: #1a5276;
        }
        .status-badge.completed .dot {
            background: #1a5276;
        }
        .status-badge.rejected {
            background: #fadbd8;
            color: #922b21;
        }
        .status-badge.rejected .dot {
            background: #922b21;
        }
        .status-badge.review {
            background: #ebf5fb;
            color: #2c7fb8;
        }
        .status-badge.review .dot {
            background: #2c7fb8;
        }
        .status-badge.approved {
            background: #d5f5e3;
            color: #1a7a3a;
        }
        .status-badge.approved .dot {
            background: #1a7a3a;
        }

        .action-icons {
            display: flex;
            gap: 6px;
        }
        .action-icons button {
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 4px;
            transition: all var(--transition);
            font-size: 14px;
        }
        .action-icons button:hover {
            background: var(--gray-100);
            color: var(--primary);
        }

        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-top: 1px solid var(--gray-200);
            font-size: 14px;
            color: var(--gray-500);
        }
        .table-footer .pagination {
            display: flex;
            gap: 6px;
        }

        /* ============================================================
           MULTI-STEP FORM
           ============================================================ */
        .form-container {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .form-stepper {
            display: flex;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            padding: 20px 32px;
            gap: 4px;
            flex-wrap: wrap;
            overflow-x: auto;
        }

        .form-stepper .step-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-500);
            padding: 4px 0;
            white-space: nowrap;
        }

        .form-stepper .step-indicator .num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-300);
            color: #fff;
            font-weight: 700;
            font-size: 13px;
            transition: all var(--transition);
            flex-shrink: 0;
        }

        .form-stepper .step-indicator.active .num {
            background: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-100);
        }

        .form-stepper .step-indicator.done .num {
            background: var(--secondary);
        }

        .form-stepper .step-indicator .label {
            font-size: 12px;
        }

        .form-stepper .step-indicator .sep {
            color: var(--gray-300);
            font-size: 16px;
            margin: 0 4px;
        }

        .form-stepper .step-indicator.active {
            color: var(--primary);
        }

        .form-stepper .step-indicator.done {
            color: var(--secondary-dark);
        }

        .form-body {
            padding: 32px;
        }

        .form-step {
            display: none;
            animation: fadeUp 0.3s ease;
        }

        .form-step.active {
            display: block;
        }

        .form-step .step-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .form-step .step-subtitle {
            color: var(--gray-500);
            font-size: 14px;
            margin-bottom: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 28px;
        }

        .form-grid .full {
            grid-column: 1 / -1;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-700);
        }

        .form-group label .required {
            color: var(--danger);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 14px;
            border: 1.5px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: var(--font);
            transition: all var(--transition);
            background: #fff;
            color: var(--gray-800);
            width: 100%;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-100);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group .helper-text {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 2px;
        }

        /* Compensation type selector */
        .comp-type-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 4px;
        }
        .comp-type-option {
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-sm);
            padding: 18px 20px;
            cursor: pointer;
            transition: all var(--transition);
            background: #fff;
            position: relative;
        }
        .comp-type-option:hover {
            border-color: var(--primary-100);
            background: var(--primary-50);
        }
        .comp-type-option.selected {
            border-color: var(--primary);
            background: var(--primary-50);
            box-shadow: 0 0 0 3px var(--primary-100);
        }
        .comp-type-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .comp-type-option .type-icon {
            font-size: 28px;
            color: var(--primary);
            margin-bottom: 8px;
            opacity: 0.75;
        }
        .comp-type-option .type-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }
        .comp-type-option .type-desc {
            font-size: 12px;
            color: var(--gray-600);
            line-height: 1.45;
        }
        .comp-type-option .type-badge {
            display: inline-block;
            margin-top: 10px;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            background: var(--gray-200);
            color: var(--gray-700);
        }
        .comp-type-option.selected .type-badge {
            background: var(--primary);
            color: #fff;
        }
        .comp-type-note {
            margin-top: 12px;
            padding: 12px 16px;
            background: #fef9e7;
            border-left: 4px solid var(--accent);
            border-radius: var(--radius-xs);
            font-size: 13px;
            color: var(--gray-700);
        }
        .comp-type-note i {
            color: var(--accent-dark);
            margin-right: 6px;
        }
        .comp-panel {
            display: none;
        }
        .comp-panel.active {
            display: block;
        }
        .comp-cards.single {
            grid-template-columns: 1fr;
            max-width: 480px;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 24px;
            border-top: 1px solid var(--gray-200);
            margin-top: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .form-actions .left {
            display: flex;
            gap: 10px;
        }

        .form-actions .right {
            display: flex;
            gap: 10px;
        }

        /* ============================================================
           BENEFICIARY LIST
           ============================================================ */
        .beneficiary-list {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 16px;
            border: 1px solid var(--gray-200);
            margin-top: 12px;
        }

        .beneficiary-list .list-header {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1.5fr 1fr 1fr auto;
            gap: 12px;
            font-weight: 600;
            font-size: 12px;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--gray-200);
        }

        .beneficiary-list .list-item {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1.5fr 1fr 1fr auto;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-200);
            align-items: center;
            font-size: 14px;
        }

        .beneficiary-list .list-item:last-child {
            border-bottom: none;
        }

        .beneficiary-list .list-item .status-badge {
            font-size: 11px;
            padding: 2px 12px;
        }

        .beneficiary-form {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 16px 20px;
            border: 1px dashed var(--gray-300);
            margin-top: 12px;
            display: none;
        }

        .beneficiary-form.open {
            display: block;
        }

        .beneficiary-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .beneficiary-form .form-row .full {
            grid-column: 1 / -1;
        }

        .beneficiary-form .form-row input,
        .beneficiary-form .form-row select {
            padding: 8px 12px;
            border: 1.5px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: var(--font);
            width: 100%;
        }

        .beneficiary-form .form-row input:focus,
        .beneficiary-form .form-row select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-100);
        }

        .beneficiary-form .photo-upload {
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius-sm);
            padding: 20px;
            text-align: center;
            color: var(--gray-500);
            cursor: pointer;
            transition: all var(--transition);
        }

        .beneficiary-form .photo-upload:hover {
            border-color: var(--primary);
            background: var(--primary-50);
        }

        .beneficiary-form .photo-upload i {
            font-size: 28px;
            display: block;
            margin-bottom: 6px;
        }

        /* ============================================================
           FARM INFORMATION (Original - with Map and Upload)
           ============================================================ */
        .map-placeholder {
            background: var(--gray-100);
            border-radius: var(--radius-sm);
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-500);
            border: 1px solid var(--gray-300);
            flex-direction: column;
            gap: 8px;
            cursor: pointer;
            transition: all var(--transition);
        }

        .map-placeholder:hover {
            border-color: var(--primary);
            background: var(--primary-50);
        }

        .map-placeholder i {
            font-size: 36px;
            opacity: 0.4;
        }

        .upload-area {
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius-sm);
            padding: 30px;
            text-align: center;
            color: var(--gray-500);
            cursor: pointer;
            transition: all var(--transition);
        }

        .upload-area:hover {
            border-color: var(--primary);
            background: var(--primary-50);
        }

        .upload-area i {
            font-size: 32px;
            display: block;
            margin-bottom: 8px;
        }

        /* ============================================================
           TREE LIST
           ============================================================ */
        .tree-list {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 16px;
            border: 1px solid var(--gray-200);
            margin-top: 12px;
        }

        .tree-list .list-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr 1.5fr auto;
            gap: 12px;
            font-weight: 600;
            font-size: 12px;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--gray-200);
        }

        .tree-list .list-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr 1.5fr auto;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-200);
            align-items: center;
            font-size: 14px;
        }

        .tree-list .list-item:last-child {
            border-bottom: none;
        }

        .tree-list .list-item .total {
            font-weight: 600;
            color: var(--primary);
        }

        .tree-list .list-total {
            display: flex;
            justify-content: flex-end;
            padding-top: 12px;
            border-top: 2px solid var(--gray-200);
            font-size: 16px;
            font-weight: 600;
        }

        .tree-list .list-total span {
            color: var(--primary);
        }

        .tree-form {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 16px 20px;
            border: 1px dashed var(--gray-300);
            margin-top: 12px;
            display: none;
        }

        .tree-form.open {
            display: block;
        }

        .tree-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        .tree-form .form-row input,
        .tree-form .form-row select {
            padding: 8px 12px;
            border: 1.5px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: var(--font);
            width: 100%;
        }

        .tree-form .form-row input:focus,
        .tree-form .form-row select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-100);
        }

        /* ============================================================
           COMPENSATION CARDS
           ============================================================ */
        .comp-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 12px;
        }

        .comp-card {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 24px 28px;
            border: 1px solid var(--gray-200);
            text-align: center;
            transition: all var(--transition);
        }

        .comp-card:hover {
            box-shadow: var(--shadow-md);
        }

        .comp-card .card-icon {
            font-size: 32px;
            color: var(--primary);
            opacity: 0.6;
            margin-bottom: 8px;
        }

        .comp-card .card-label {
            font-size: 14px;
            color: var(--gray-500);
            font-weight: 500;
        }

        .comp-card .card-amount {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin: 8px 0;
        }

        .comp-card .card-detail {
            font-size: 14px;
            color: var(--gray-600);
        }

        .comp-card .card-detail .highlight {
            font-weight: 600;
            color: var(--primary);
        }

        .comp-card .divider {
            border-top: 2px dashed var(--gray-300);
            margin: 16px 0;
        }

        .comp-card .land-split {
            display: flex;
            justify-content: space-around;
            margin-top: 12px;
        }

        .comp-card .land-split .split-item {
            text-align: center;
        }

        .comp-card .land-split .split-item .num {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }

        .comp-card .land-split .split-item .label {
            font-size: 12px;
            color: var(--gray-500);
        }

        .comp-total {
            background: var(--primary-50);
            border-radius: var(--radius-sm);
            padding: 16px 24px;
            border-left: 4px solid var(--primary);
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .comp-total .total-label {
            font-weight: 500;
            color: var(--gray-700);
        }

        .comp-total .total-amount {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        /* ============================================================
           PREVIEW
           ============================================================ */
        .preview-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            background: var(--gray-50);
            padding: 24px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-200);
        }

        .preview-grid .preview-item {
            display: flex;
            flex-direction: column;
        }

        .preview-grid .preview-item .label {
            font-size: 12px;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: 600;
        }

        .preview-grid .preview-item .value {
            font-size: 15px;
            font-weight: 500;
            color: var(--gray-900);
            margin-top: 2px;
        }

        .preview-grid .preview-item.full {
            grid-column: 1 / -1;
        }

        .preview-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        /* ============================================================
           FARM DATA TABLE (Plot Allocation - Separate Tab) - FIXED
           ============================================================ */
        .farm-table-wrapper {
            background: #fff;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-200);
            position: relative;
            max-width: 100%;
            /* overflow NOT hidden here so scrollbar remains visible */
        }

        /* Scroll indicator – always visible when table can overflow */
        .scroll-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
            padding: 8px 12px;
            font-size: 12px;
            color: var(--gray-600);
            background: var(--primary-50);
            border-bottom: 1px solid var(--gray-200);
            font-weight: 500;
            border-radius: var(--radius-sm) var(--radius-sm) 0 0;
        }

        .scroll-indicator i {
            color: var(--primary);
        }

        .farm-table-wrapper .farm-table-scroll {
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            width: 100%;
            max-width: 100%;
            /* Ensure scrollbar is always accessible */
            scrollbar-gutter: stable;
        }

        /* Custom scrollbar for the table – always visible height */
        .farm-table-wrapper .farm-table-scroll::-webkit-scrollbar {
            height: 12px;
        }
        .farm-table-wrapper .farm-table-scroll::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 10px;
        }
        .farm-table-wrapper .farm-table-scroll::-webkit-scrollbar-thumb {
            background: var(--gray-400);
            border-radius: 10px;
            border: 2px solid var(--gray-100);
        }
        .farm-table-wrapper .farm-table-scroll::-webkit-scrollbar-thumb:hover {
            background: var(--gray-500);
        }

        .farm-table-wrapper table.farm-data-table {
            width: max-content;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
            table-layout: fixed;
        }

        .farm-table-wrapper table.farm-data-table thead th {
            background: var(--gray-50);
            padding: 10px 8px;
            text-align: center;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        /* Column widths – realistic min-widths so content is readable */
        .farm-table-wrapper table.farm-data-table .col-sr { width: 48px; min-width: 48px; }
        .farm-table-wrapper table.farm-data-table .col-fno { width: 70px; min-width: 70px; }
        .farm-table-wrapper table.farm-data-table .col-name { width: 140px; min-width: 140px; }
        .farm-table-wrapper table.farm-data-table .col-acreage { width: 70px; min-width: 70px; }
        .farm-table-wrapper table.farm-data-table .col-creetable { width: 80px; min-width: 80px; }
        .farm-table-wrapper table.farm-data-table .col-govt { width: 75px; min-width: 75px; }
        .farm-table-wrapper table.farm-data-table .col-farmer { width: 80px; min-width: 80px; }
        .farm-table-wrapper table.farm-data-table .col-plotno { width: 75px; min-width: 75px; }
        .farm-table-wrapper table.farm-data-table .col-opno { width: 95px; min-width: 95px; }
        .farm-table-wrapper table.farm-data-table .col-sign { width: 70px; min-width: 70px; }
        .farm-table-wrapper table.farm-data-table .col-remarks { width: 120px; min-width: 120px; }
        .farm-table-wrapper table.farm-data-table .col-action {
            width: 60px;
            min-width: 60px;
            /* Sticky Action column so it stays visible while scrolling */
            position: sticky;
            right: 0;
            z-index: 12;
            background: var(--gray-50);
            box-shadow: -4px 0 8px rgba(0, 0, 0, 0.06);
        }

        .farm-table-wrapper table.farm-data-table tbody td {
            padding: 6px 8px;
            border: 1px solid var(--gray-200);
            vertical-align: middle;
            text-align: center;
            font-size: 13px;
            background: #fff;
        }

        /* Sticky Action cells in body */
        .farm-table-wrapper table.farm-data-table tbody td.col-action,
        .farm-table-wrapper table.farm-data-table tbody td:last-child {
            position: sticky;
            right: 0;
            z-index: 5;
            background: #fff;
            box-shadow: -4px 0 8px rgba(0, 0, 0, 0.06);
        }

        .farm-table-wrapper table.farm-data-table tbody tr:hover td.col-action,
        .farm-table-wrapper table.farm-data-table tbody tr:hover td:last-child {
            background: var(--gray-50);
        }

        .farm-table-wrapper table.farm-data-table tbody td .form-control {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-xs);
            font-size: 13px;
            font-family: var(--font);
            background: #fff;
            transition: all var(--transition);
            min-width: 0;
            box-sizing: border-box;
        }

        .farm-table-wrapper table.farm-data-table tbody td .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-100);
        }

        .farm-table-wrapper table.farm-data-table tbody td .form-control-sm {
            padding: 4px 6px;
            font-size: 12px;
        }

        .farm-table-wrapper table.farm-data-table tbody td .text-left {
            text-align: left;
        }

        .farm-table-wrapper table.farm-data-table tbody tr.row-highlight {
            background: var(--primary-50);
        }

        .farm-table-wrapper table.farm-data-table tbody tr.row-highlight td {
            border-color: var(--primary-100);
            background: var(--primary-50);
        }

        .farm-table-wrapper table.farm-data-table tbody tr.row-highlight td.col-action,
        .farm-table-wrapper table.farm-data-table tbody tr.row-highlight td:last-child {
            background: var(--primary-50);
        }

        .farm-table-wrapper .table-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-top: 1px solid var(--gray-200);
            flex-wrap: wrap;
            gap: 8px;
            background: var(--gray-50);
            border-radius: 0 0 var(--radius-sm) var(--radius-sm);
        }

        .farm-table-wrapper .table-actions .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .farm-table-wrapper .table-actions .totals-display {
            display: flex;
            gap: 16px;
            font-size: 14px;
            flex-wrap: wrap;
        }

        .farm-table-wrapper .table-actions .totals-display span {
            white-space: nowrap;
        }

        /* Farmer Entry Form for Farm Table */
        .farmer-entry-form {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 16px 20px;
            border: 1px dashed var(--gray-300);
            margin-bottom: 12px;
            display: none;
        }

        .farmer-entry-form.open {
            display: block;
        }

        .farmer-entry-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .farmer-entry-form .form-row .full {
            grid-column: 1 / -1;
        }

        .farmer-entry-form .form-row input,
        .farmer-entry-form .form-row select {
            padding: 8px 12px;
            border: 1.5px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: var(--font);
            width: 100%;
        }

        .farmer-entry-form .form-row input:focus,
        .farmer-entry-form .form-row select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-100);
        }

        /* ============================================================
           COMPENSATION CALCULATOR
           ============================================================ */
        .calc-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 16px;
        }

        .calc-card {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 20px 24px;
            border: 1px solid var(--gray-200);
            text-align: center;
        }
        .calc-card .label {
            font-size: 13px;
            color: var(--gray-500);
            font-weight: 500;
        }
        .calc-card .amount {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            margin: 8px 0;
        }
        .calc-card .detail {
            font-size: 13px;
            color: var(--gray-600);
        }

        .calc-ratio {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            padding: 20px 24px;
            background: var(--primary-50);
            border-radius: var(--radius-sm);
            border-left: 4px solid var(--primary);
            margin-top: 20px;
        }
        .calc-ratio .ratio-item {
            text-align: center;
        }
        .calc-ratio .ratio-item .num {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }
        .calc-ratio .ratio-item .label {
            font-size: 12px;
            color: var(--gray-600);
        }
        .calc-ratio .ratio-divider {
            font-size: 32px;
            color: var(--gray-400);
            font-weight: 300;
        }

        /* ============================================================
           TABS
           ============================================================ */
        .tabs {
            display: flex;
            gap: 4px;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .tabs .tab {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-600);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all var(--transition);
        }
        .tabs .tab:hover {
            color: var(--gray-900);
        }
        .tabs .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-panel {
            display: none;
            animation: fadeUp 0.2s ease;
        }
        .tab-panel.active {
            display: block;
        }

        /* ============================================================
           WORKFLOW PAGE
           ============================================================ */
        .workflow-page {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px 0;
        }

        .workflow-page .wf-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .workflow-page .wf-header h2 {
            font-size: 24px;
        }
        .workflow-page .wf-header p {
            color: var(--gray-500);
        }

        .wf-chain {
            display: flex;
            flex-direction: column;
            gap: 0;
            position: relative;
            padding-left: 40px;
        }
        .wf-chain::before {
            content: '';
            position: absolute;
            left: 14px;
            top: 20px;
            bottom: 20px;
            width: 2px;
            background: var(--gray-300);
        }

        .wf-node {
            position: relative;
            padding: 16px 20px 16px 30px;
            background: #fff;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-200);
            margin-bottom: 12px;
            cursor: pointer;
            transition: all var(--transition);
        }
        .wf-node:hover {
            box-shadow: var(--shadow-md);
        }
        .wf-node::before {
            content: '';
            position: absolute;
            left: -32px;
            top: 24px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--gray-300);
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px var(--gray-300);
        }
        .wf-node.done::before {
            background: var(--secondary);
            box-shadow: 0 0 0 2px var(--secondary);
        }
        .wf-node.active::before {
            background: var(--accent);
            box-shadow: 0 0 0 2px var(--accent);
            animation: pulse 1.5s infinite;
        }
        .wf-node.fail::before {
            background: var(--danger);
            box-shadow: 0 0 0 2px var(--danger);
        }

        @keyframes pulse {
            0%,
            100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.2);
            }
        }

        .wf-node .wf-title {
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .wf-node .wf-title .wf-status {
            font-size: 12px;
            font-weight: 500;
            padding: 2px 14px;
            border-radius: 20px;
        }
        .wf-node .wf-title .wf-status.done {
            background: #d5f5e3;
            color: #1a7a3a;
        }
        .wf-node .wf-title .wf-status.active {
            background: #fdebd0;
            color: #b9770e;
        }
        .wf-node .wf-title .wf-status.waiting {
            background: var(--gray-200);
            color: var(--gray-600);
        }
        .wf-node .wf-title .wf-status.fail {
            background: #fadbd8;
            color: #922b21;
        }

        .wf-node .wf-meta {
            font-size: 13px;
            color: var(--gray-500);
            margin-top: 6px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .wf-node .wf-detail {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--gray-100);
            font-size: 14px;
            color: var(--gray-700);
            display: none;
        }
        .wf-node .wf-detail.open {
            display: block;
        }

        /* OP Scenarios */
        .op-scenarios {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }
        .op-scenario {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 16px 20px;
            border: 1px solid var(--gray-200);
            font-size: 14px;
        }
        .op-scenario .scenario-title {
            font-weight: 600;
            color: var(--primary);
        }
        .op-scenario .scenario-desc {
            color: var(--gray-600);
            margin-top: 4px;
            font-size: 13px;
        }

        /* ============================================================
           GIS MODULE
           ============================================================ */
        .gis-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .gis-map {
            background: #eef2f7;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-500);
            font-size: 14px;
            flex-direction: column;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }
        .gis-map i {
            font-size: 48px;
            opacity: 0.3;
        }

        .gis-map .map-controls {
            position: absolute;
            bottom: 16px;
            left: 16px;
            display: flex;
            gap: 6px;
        }
        .gis-map .map-controls button {
            background: #fff;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-xs);
            padding: 6px 12px;
            font-size: 13px;
            cursor: pointer;
            font-family: var(--font);
            box-shadow: var(--shadow-sm);
        }
        .gis-map .map-controls button:hover {
            background: var(--gray-50);
        }

        .gis-panel {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            padding: 20px 24px;
        }

        .gis-panel .layer-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .gis-panel .layer-item:last-child {
            border-bottom: none;
        }
        .gis-panel .layer-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }

        /* ============================================================
           EXAMINATION
           ============================================================ */
        .exam-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .exam-card {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            padding: 20px 24px;
            box-shadow: var(--shadow-sm);
        }

        .exam-card .exam-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        /* ============================================================
           REPORTS
           ============================================================ */
        .report-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            padding: 20px 24px;
            margin-bottom: 24px;
            align-items: flex-end;
        }

        .report-filters .form-group {
            min-width: 150px;
            flex: 1;
        }

        .report-filters .form-group label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--gray-500);
        }

        /* ============================================================
           SECTION CARDS
           ============================================================ */
        .section-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .section-card {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            padding: 20px 24px;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition);
        }
        .section-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        .section-card .section-icon {
            font-size: 32px;
            color: var(--primary);
            opacity: 0.7;
        }
        .section-card h4 {
            margin: 8px 0 4px;
        }
        .section-card p {
            font-size: 13px;
            color: var(--gray-500);
        }
        .section-card .stat {
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-900);
            margin-top: 8px;
        }

        /* ============================================================
           QUICK WIN BANNER
           ============================================================ */
        .quick-win {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: #fff;
            border-radius: var(--radius);
            padding: 20px 28px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .quick-win .badge {
            background: var(--accent);
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .quick-win h3 {
            font-size: 18px;
            font-weight: 600;
        }
        .quick-win p {
            opacity: 0.8;
            font-size: 14px;
        }

        /* ============================================================
           PAGE HEADER
           ============================================================ */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .page-header h2 {
            font-size: 20px;
            font-weight: 600;
        }
        .page-header p {
            color: var(--gray-500);
            font-size: 14px;
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1024px) {
            .dash-grid {
                grid-template-columns: 1fr;
            }
            .gis-layout {
                grid-template-columns: 1fr;
            }
            .calc-grid {
                grid-template-columns: 1fr 1fr;
            }
            .exam-grid {
                grid-template-columns: 1fr;
            }
            .op-scenarios {
                grid-template-columns: 1fr;
            }
            .comp-cards {
                grid-template-columns: 1fr;
            }
            .preview-grid {
                grid-template-columns: 1fr;
            }
            .preview-grid .preview-item.full {
                grid-column: 1;
            }
            .farmer-entry-form .form-row {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .header-left .menu-toggle {
                display: block;
            }

            .header {
                padding: 0 16px;
            }
            .header-right .search-box input {
                width: 100px;
            }

            .page-content {
                padding: 16px;
            }

            .comp-type-grid {
                grid-template-columns: 1fr;
            }

            .kpi-grid {
                grid-template-columns: 1fr 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-stepper {
                padding: 12px 16px;
                gap: 2px;
            }
            .form-stepper .step-indicator .label {
                display: none;
            }
            .form-stepper .step-indicator .sep {
                display: none;
            }

            .form-body {
                padding: 20px 16px;
            }

            .beneficiary-list .list-header,
            .beneficiary-list .list-item {
                grid-template-columns: 1fr 1fr;
                gap: 6px;
                font-size: 13px;
            }
            .beneficiary-list .list-header .hide-mobile,
            .beneficiary-list .list-item .hide-mobile {
                display: none;
            }

            .tree-list .list-header,
            .tree-list .list-item {
                grid-template-columns: 1fr 1fr;
                gap: 6px;
                font-size: 13px;
            }
            .tree-list .list-header .hide-mobile,
            .tree-list .list-item .hide-mobile {
                display: none;
            }

            .tree-form .form-row {
                grid-template-columns: 1fr 1fr;
            }

            .beneficiary-form .form-row {
                grid-template-columns: 1fr;
            }

            .table-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .report-filters {
                flex-direction: column;
            }
            .report-filters .form-group {
                min-width: auto;
            }

            .workflow-page {
                padding: 0;
            }
            .wf-chain {
                padding-left: 28px;
            }
            .wf-node {
                padding: 12px 14px 12px 20px;
            }
            .wf-node::before {
                left: -24px;
                width: 12px;
                height: 12px;
                top: 20px;
            }

            .calc-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .form-actions .left,
            .form-actions .right {
                justify-content: center;
            }

            .comp-total {
                flex-direction: column;
                text-align: center;
            }

            .preview-actions {
                justify-content: center;
            }

            /* Farm Table Responsive - keep horizontal scroll + sticky Action */
            .farm-table-wrapper table.farm-data-table {
                font-size: 12px;
            }

            .farm-table-wrapper table.farm-data-table thead th {
                font-size: 10px;
                padding: 6px 4px;
            }
            .farm-table-wrapper table.farm-data-table tbody td {
                padding: 4px 4px;
                font-size: 12px;
            }
            .farm-table-wrapper table.farm-data-table tbody td .form-control {
                font-size: 11px;
                padding: 4px 4px;
            }

            .farm-table-wrapper .table-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            .farm-table-wrapper .table-actions .action-buttons {
                justify-content: center;
                flex-wrap: wrap;
            }
            .farm-table-wrapper .table-actions .totals-display {
                justify-content: center;
                gap: 12px;
                font-size: 13px;
            }

            .farmer-entry-form .form-row {
                grid-template-columns: 1fr 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            .page-header .btn {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }
            .header-right .search-box {
                display: none;
            }

            .form-stepper .step-indicator .num {
                width: 28px;
                height: 28px;
                font-size: 11px;
            }

            .farmer-entry-form .form-row {
                grid-template-columns: 1fr;
            }

            .farm-table-wrapper table.farm-data-table {
                font-size: 11px;
            }
            .farm-table-wrapper table.farm-data-table thead th {
                font-size: 9px;
                padding: 4px 3px;
            }
            .farm-table-wrapper table.farm-data-table tbody td {
                padding: 3px 3px;
                font-size: 11px;
            }
            .farm-table-wrapper table.farm-data-table tbody td .form-control {
                font-size: 10px;
                padding: 3px 3px;
            }

            .page-header h2 {
                font-size: 18px;
            }

            .farm-table-wrapper .table-actions .action-buttons .btn-sm {
                font-size: 11px;
                padding: 4px 8px;
            }
            .farm-table-wrapper .table-actions .totals-display {
                font-size: 12px;
                gap: 8px;
            }
        }

        /* ============================================================
           UTILITY
           ============================================================ */
        .text-center {
            text-align: center;
        }
        .mt-16 {
            margin-top: 16px;
        }
        .mb-16 {
            margin-bottom: 16px;
        }
        .gap-8 {
            gap: 8px;
        }
        .flex {
            display: flex;
        }
        .flex-center {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .gap-12 {
            gap: 12px;
        }
        .wrap {
            flex-wrap: wrap;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 999;
        }
        .sidebar-overlay.active {
            display: block;
        }

        /* Farm Summary Cards */
        .farm-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .farm-summary .summary-item {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            border: 1px solid var(--gray-200);
            text-align: center;
        }

        .farm-summary .summary-item .number {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .farm-summary .summary-item .label {
            font-size: 12px;
            color: var(--gray-500);
        }

        /* Ensure table wrapper respects parent width; scroll lives inside */
        .farm-table-wrapper {
            max-width: 100%;
        }

        .farm-table-wrapper .farm-table-scroll {
            max-width: 100%;
        }
</style>
