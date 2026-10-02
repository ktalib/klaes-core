{{-- Survey-Module prototype CSS, scoped under .survey-proto so it cannot
     affect the main KLAES chrome. Generated from _prototype_css.blade.php
     by scope_css.php — regenerate rather than hand-editing. --}}
<style>
/* ============================================================
           CSS VARIABLES & RESET
           ============================================================ */
.survey-proto {
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
.survey-proto *,
.survey-proto *::before,
.survey-proto *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
.survey-proto {
    font-family: var(--font);
    background: var(--gray-50);
    color: var(--gray-800);
}
.survey-proto ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
.survey-proto ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }
.survey-proto ::-webkit-scrollbar-thumb {
            background: var(--gray-400);
            border-radius: 10px;
        }
.survey-proto ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-500);
        }
/* ============================================================
           SIDEBAR
           ============================================================ */
/* ============================================================
           MAIN CONTENT
           ============================================================ */
/* ============================================================
           HEADER
           ============================================================ */
/* ============================================================
           PAGE CONTENT
           ============================================================ */
.survey-proto .page-content {
            padding: 24px 32px 40px;
            flex: 1;
        }
.survey-proto .page {
            display: none;
            animation: fadeUp 0.3s ease;
        }
.survey-proto .page.active {
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
.survey-proto .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--gray-500);
            margin-bottom: 16px;
            padding: 8px 0;
        }
.survey-proto .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }
.survey-proto .breadcrumb a:hover {
            text-decoration: underline;
        }
.survey-proto .breadcrumb .separator {
            color: var(--gray-300);
        }
.survey-proto .breadcrumb .current {
            color: var(--gray-700);
            font-weight: 500;
        }
/* ============================================================
           KPI CARDS
           ============================================================ */
.survey-proto .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }
.survey-proto .kpi-card {
            background: #fff;
            border-radius: var(--radius);
            padding: 20px 22px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: all var(--transition);
        }
.survey-proto .kpi-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
.survey-proto .kpi-card .kpi-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
.survey-proto .kpi-card .kpi-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
            margin-top: 4px;
        }
.survey-proto .kpi-card .kpi-change {
            font-size: 12px;
            font-weight: 600;
            margin-top: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 20px;
        }
.survey-proto .kpi-card .kpi-change.up {
            color: var(--secondary-dark);
            background: #d5f5e3;
        }
.survey-proto .kpi-card .kpi-change.down {
            color: var(--danger);
            background: #fadbd8;
        }
.survey-proto .kpi-card .kpi-icon {
            float: right;
            font-size: 28px;
            color: var(--primary-100);
            opacity: 0.6;
        }
/* ============================================================
           DASHBOARD GRID
           ============================================================ */
.survey-proto .dash-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
.survey-proto .dash-card {
            background: #fff;
            border-radius: var(--radius);
            padding: 20px 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }
.survey-proto .dash-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
.survey-proto .dash-card .card-header h3 {
            font-size: 15px;
            font-weight: 600;
        }
.survey-proto .dash-card .card-header a {
            font-size: 13px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }
.survey-proto .dash-card .card-header a:hover {
            text-decoration: underline;
        }
/* ============================================================
           WORKFLOW TIMELINE
           ============================================================ */
.survey-proto .workflow-timeline {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
.survey-proto .workflow-step {
            display: flex;
            align-items: center;
            gap: 14px;
        }
.survey-proto .workflow-step .step-icon {
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
.survey-proto .workflow-step .step-icon.done {
            background: var(--secondary);
            color: #fff;
        }
.survey-proto .workflow-step .step-icon.pending {
            background: var(--accent);
            color: #fff;
        }
.survey-proto .workflow-step .step-icon.waiting {
            background: var(--gray-300);
            color: var(--gray-600);
        }
.survey-proto .workflow-step .step-icon.fail {
            background: var(--danger);
            color: #fff;
        }
.survey-proto .workflow-step .step-info {
            flex: 1;
        }
.survey-proto .workflow-step .step-info .step-name {
            font-size: 14px;
            font-weight: 500;
        }
.survey-proto .workflow-step .step-info .step-status {
            font-size: 12px;
            color: var(--gray-500);
        }
.survey-proto .workflow-step .step-time {
            font-size: 12px;
            color: var(--gray-500);
        }
/* ============================================================
           ACTIVITY FEED
           ============================================================ */
.survey-proto .activity-feed {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
.survey-proto .activity-item {
            display: flex;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--gray-100);
        }
.survey-proto .activity-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
.survey-proto .activity-item .activity-icon {
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
.survey-proto .activity-item .activity-content {
            flex: 1;
        }
.survey-proto .activity-item .activity-content .text {
            font-size: 14px;
            color: var(--gray-700);
        }
.survey-proto .activity-item .activity-content .text strong {
            color: var(--gray-900);
        }
.survey-proto .activity-item .activity-content .time {
            font-size: 12px;
            color: var(--gray-500);
        }
/* ============================================================
           BUTTONS
           ============================================================ */
.survey-proto .btn {
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
.survey-proto .btn-primary {
            background: var(--primary);
            color: #fff;
        }
.survey-proto .btn-primary:hover {
            background: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
.survey-proto .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
.survey-proto .btn-secondary:hover {
            background: var(--gray-300);
        }
.survey-proto .btn-success {
            background: var(--secondary);
            color: #fff;
        }
.survey-proto .btn-success:hover {
            background: var(--secondary-dark);
        }
.survey-proto .btn-danger {
            background: var(--danger);
            color: #fff;
        }
.survey-proto .btn-danger:hover {
            background: var(--danger-dark);
        }
.survey-proto .btn-outline {
            background: transparent;
            border: 1.5px solid var(--gray-300);
            color: var(--gray-700);
        }
.survey-proto .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50);
        }
.survey-proto .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
        }
.survey-proto .btn-xs {
            padding: 3px 10px;
            font-size: 11px;
        }
/* ============================================================
           TABLES
           ============================================================ */
.survey-proto .table-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
.survey-proto .table-toolbar .left {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
.survey-proto .table-wrapper {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
.survey-proto .table-scroll {
            overflow-x: auto;
        }
.survey-proto table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
.survey-proto table thead {
            background: var(--gray-50);
        }
.survey-proto table thead th {
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
.survey-proto table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            vertical-align: middle;
        }
.survey-proto table tbody tr:hover {
            background: var(--gray-50);
        }
.survey-proto table tbody tr:last-child td {
            border-bottom: none;
        }
.survey-proto .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
.survey-proto .status-badge .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
.survey-proto .status-badge.active {
            background: #d5f5e3;
            color: #1a7a3a;
        }
.survey-proto .status-badge.active .dot {
            background: #1a7a3a;
        }
.survey-proto .status-badge.pending {
            background: #fdebd0;
            color: #b9770e;
        }
.survey-proto .status-badge.pending .dot {
            background: #b9770e;
        }
.survey-proto .status-badge.completed {
            background: #d6eaf8;
            color: #1a5276;
        }
.survey-proto .status-badge.completed .dot {
            background: #1a5276;
        }
.survey-proto .status-badge.rejected {
            background: #fadbd8;
            color: #922b21;
        }
.survey-proto .status-badge.rejected .dot {
            background: #922b21;
        }
.survey-proto .status-badge.review {
            background: #ebf5fb;
            color: #2c7fb8;
        }
.survey-proto .status-badge.review .dot {
            background: #2c7fb8;
        }
.survey-proto .status-badge.approved {
            background: #d5f5e3;
            color: #1a7a3a;
        }
.survey-proto .status-badge.approved .dot {
            background: #1a7a3a;
        }
.survey-proto .action-icons {
            display: flex;
            gap: 6px;
        }
.survey-proto .action-icons button {
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 4px;
            transition: all var(--transition);
            font-size: 14px;
        }
.survey-proto .action-icons button:hover {
            background: var(--gray-100);
            color: var(--primary);
        }
.survey-proto .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-top: 1px solid var(--gray-200);
            font-size: 14px;
            color: var(--gray-500);
        }
.survey-proto .table-footer .pagination {
            display: flex;
            gap: 6px;
        }
/* ============================================================
           MULTI-STEP FORM
           ============================================================ */
.survey-proto .form-container {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
.survey-proto .form-stepper {
            display: flex;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            padding: 20px 32px;
            gap: 4px;
            flex-wrap: wrap;
            overflow-x: auto;
        }
.survey-proto .form-stepper .step-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-500);
            padding: 4px 0;
            white-space: nowrap;
        }
.survey-proto .form-stepper .step-indicator .num {
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
.survey-proto .form-stepper .step-indicator.active .num {
            background: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-100);
        }
.survey-proto .form-stepper .step-indicator.done .num {
            background: var(--secondary);
        }
.survey-proto .form-stepper .step-indicator .label {
            font-size: 12px;
        }
.survey-proto .form-stepper .step-indicator .sep {
            color: var(--gray-300);
            font-size: 16px;
            margin: 0 4px;
        }
.survey-proto .form-stepper .step-indicator.active {
            color: var(--primary);
        }
.survey-proto .form-stepper .step-indicator.done {
            color: var(--secondary-dark);
        }
.survey-proto .form-body {
            padding: 32px;
        }
.survey-proto .form-step {
            display: none;
            animation: fadeUp 0.3s ease;
        }
.survey-proto .form-step.active {
            display: block;
        }
.survey-proto .form-step .step-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }
.survey-proto .form-step .step-subtitle {
            color: var(--gray-500);
            font-size: 14px;
            margin-bottom: 24px;
        }
.survey-proto .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 28px;
        }
.survey-proto .form-grid .full {
            grid-column: 1 / -1;
        }
.survey-proto .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
.survey-proto .form-group label {
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-700);
        }
.survey-proto .form-group label .required {
            color: var(--danger);
        }
.survey-proto .form-group input,
.survey-proto .form-group select,
.survey-proto .form-group textarea {
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
.survey-proto .form-group input:focus,
.survey-proto .form-group select:focus,
.survey-proto .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-100);
        }
.survey-proto .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
.survey-proto .form-group .helper-text {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 2px;
        }
/* Compensation type selector */
.survey-proto .comp-type-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 4px;
        }
.survey-proto .comp-type-option {
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-sm);
            padding: 18px 20px;
            cursor: pointer;
            transition: all var(--transition);
            background: #fff;
            position: relative;
        }
.survey-proto .comp-type-option:hover {
            border-color: var(--primary-100);
            background: var(--primary-50);
        }
.survey-proto .comp-type-option.selected {
            border-color: var(--primary);
            background: var(--primary-50);
            box-shadow: 0 0 0 3px var(--primary-100);
        }
.survey-proto .comp-type-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
.survey-proto .comp-type-option .type-icon {
            font-size: 28px;
            color: var(--primary);
            margin-bottom: 8px;
            opacity: 0.75;
        }
.survey-proto .comp-type-option .type-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }
.survey-proto .comp-type-option .type-desc {
            font-size: 12px;
            color: var(--gray-600);
            line-height: 1.45;
        }
.survey-proto .comp-type-option .type-badge {
            display: inline-block;
            margin-top: 10px;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            background: var(--gray-200);
            color: var(--gray-700);
        }
.survey-proto .comp-type-option.selected .type-badge {
            background: var(--primary);
            color: #fff;
        }
.survey-proto .comp-type-note {
            margin-top: 12px;
            padding: 12px 16px;
            background: #fef9e7;
            border-left: 4px solid var(--accent);
            border-radius: var(--radius-xs);
            font-size: 13px;
            color: var(--gray-700);
        }
.survey-proto .comp-type-note i {
            color: var(--accent-dark);
            margin-right: 6px;
        }
.survey-proto .comp-panel {
            display: none;
        }
.survey-proto .comp-panel.active {
            display: block;
        }
.survey-proto .comp-cards.single {
            grid-template-columns: 1fr;
            max-width: 480px;
        }
.survey-proto .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 24px;
            border-top: 1px solid var(--gray-200);
            margin-top: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }
.survey-proto .form-actions .left {
            display: flex;
            gap: 10px;
        }
.survey-proto .form-actions .right {
            display: flex;
            gap: 10px;
        }
/* ============================================================
           BENEFICIARY LIST
           ============================================================ */
.survey-proto .beneficiary-list {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 16px;
            border: 1px solid var(--gray-200);
            margin-top: 12px;
        }
.survey-proto .beneficiary-list .list-header {
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
.survey-proto .beneficiary-list .list-item {
            display: grid;
            grid-template-columns: 2fr 1.5fr 1.5fr 1fr 1fr auto;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-200);
            align-items: center;
            font-size: 14px;
        }
.survey-proto .beneficiary-list .list-item:last-child {
            border-bottom: none;
        }
.survey-proto .beneficiary-list .list-item .status-badge {
            font-size: 11px;
            padding: 2px 12px;
        }
.survey-proto .beneficiary-form {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 16px 20px;
            border: 1px dashed var(--gray-300);
            margin-top: 12px;
            display: none;
        }
.survey-proto .beneficiary-form.open {
            display: block;
        }
.survey-proto .beneficiary-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }
.survey-proto .beneficiary-form .form-row .full {
            grid-column: 1 / -1;
        }
.survey-proto .beneficiary-form .form-row input,
.survey-proto .beneficiary-form .form-row select {
            padding: 8px 12px;
            border: 1.5px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: var(--font);
            width: 100%;
        }
.survey-proto .beneficiary-form .form-row input:focus,
.survey-proto .beneficiary-form .form-row select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-100);
        }
.survey-proto .beneficiary-form .photo-upload {
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius-sm);
            padding: 20px;
            text-align: center;
            color: var(--gray-500);
            cursor: pointer;
            transition: all var(--transition);
        }
.survey-proto .beneficiary-form .photo-upload:hover {
            border-color: var(--primary);
            background: var(--primary-50);
        }
.survey-proto .beneficiary-form .photo-upload i {
            font-size: 28px;
            display: block;
            margin-bottom: 6px;
        }
/* ============================================================
           FARM INFORMATION (Original - with Map and Upload)
           ============================================================ */
.survey-proto .map-placeholder {
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
.survey-proto .map-placeholder:hover {
            border-color: var(--primary);
            background: var(--primary-50);
        }
.survey-proto .map-placeholder i {
            font-size: 36px;
            opacity: 0.4;
        }
.survey-proto .upload-area {
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius-sm);
            padding: 30px;
            text-align: center;
            color: var(--gray-500);
            cursor: pointer;
            transition: all var(--transition);
        }
.survey-proto .upload-area:hover {
            border-color: var(--primary);
            background: var(--primary-50);
        }
.survey-proto .upload-area i {
            font-size: 32px;
            display: block;
            margin-bottom: 8px;
        }
/* ============================================================
           TREE LIST
           ============================================================ */
.survey-proto .tree-list {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 16px;
            border: 1px solid var(--gray-200);
            margin-top: 12px;
        }
.survey-proto .tree-list .list-header {
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
.survey-proto .tree-list .list-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr 1.5fr auto;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-200);
            align-items: center;
            font-size: 14px;
        }
.survey-proto .tree-list .list-item:last-child {
            border-bottom: none;
        }
.survey-proto .tree-list .list-item .total {
            font-weight: 600;
            color: var(--primary);
        }
.survey-proto .tree-list .list-total {
            display: flex;
            justify-content: flex-end;
            padding-top: 12px;
            border-top: 2px solid var(--gray-200);
            font-size: 16px;
            font-weight: 600;
        }
.survey-proto .tree-list .list-total span {
            color: var(--primary);
        }
.survey-proto .tree-form {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 16px 20px;
            border: 1px dashed var(--gray-300);
            margin-top: 12px;
            display: none;
        }
.survey-proto .tree-form.open {
            display: block;
        }
.survey-proto .tree-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }
.survey-proto .tree-form .form-row input,
.survey-proto .tree-form .form-row select {
            padding: 8px 12px;
            border: 1.5px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: var(--font);
            width: 100%;
        }
.survey-proto .tree-form .form-row input:focus,
.survey-proto .tree-form .form-row select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-100);
        }
/* ============================================================
           COMPENSATION CARDS
           ============================================================ */
.survey-proto .comp-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 12px;
        }
.survey-proto .comp-card {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 24px 28px;
            border: 1px solid var(--gray-200);
            text-align: center;
            transition: all var(--transition);
        }
.survey-proto .comp-card:hover {
            box-shadow: var(--shadow-md);
        }
.survey-proto .comp-card .card-icon {
            font-size: 32px;
            color: var(--primary);
            opacity: 0.6;
            margin-bottom: 8px;
        }
.survey-proto .comp-card .card-label {
            font-size: 14px;
            color: var(--gray-500);
            font-weight: 500;
        }
.survey-proto .comp-card .card-amount {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin: 8px 0;
        }
.survey-proto .comp-card .card-detail {
            font-size: 14px;
            color: var(--gray-600);
        }
.survey-proto .comp-card .card-detail .highlight {
            font-weight: 600;
            color: var(--primary);
        }
.survey-proto .comp-card .divider {
            border-top: 2px dashed var(--gray-300);
            margin: 16px 0;
        }
.survey-proto .comp-card .land-split {
            display: flex;
            justify-content: space-around;
            margin-top: 12px;
        }
.survey-proto .comp-card .land-split .split-item {
            text-align: center;
        }
.survey-proto .comp-card .land-split .split-item .num {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }
.survey-proto .comp-card .land-split .split-item .label {
            font-size: 12px;
            color: var(--gray-500);
        }
.survey-proto .comp-total {
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
.survey-proto .comp-total .total-label {
            font-weight: 500;
            color: var(--gray-700);
        }
.survey-proto .comp-total .total-amount {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }
/* ============================================================
           PREVIEW
           ============================================================ */
.survey-proto .preview-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            background: var(--gray-50);
            padding: 24px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-200);
        }
.survey-proto .preview-grid .preview-item {
            display: flex;
            flex-direction: column;
        }
.survey-proto .preview-grid .preview-item .label {
            font-size: 12px;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: 600;
        }
.survey-proto .preview-grid .preview-item .value {
            font-size: 15px;
            font-weight: 500;
            color: var(--gray-900);
            margin-top: 2px;
        }
.survey-proto .preview-grid .preview-item.full {
            grid-column: 1 / -1;
        }
.survey-proto .preview-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
/* ============================================================
           FARM DATA TABLE (Plot Allocation - Separate Tab) - FIXED
           ============================================================ */
.survey-proto .farm-table-wrapper {
            background: #fff;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-200);
            position: relative;
            max-width: 100%;
            /* overflow NOT hidden here so scrollbar remains visible */
        }
/* Scroll indicator – always visible when table can overflow */
.survey-proto .scroll-indicator {
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
.survey-proto .scroll-indicator i {
            color: var(--primary);
        }
.survey-proto .farm-table-wrapper .farm-table-scroll {
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            width: 100%;
            max-width: 100%;
            /* Ensure scrollbar is always accessible */
            scrollbar-gutter: stable;
        }
/* Custom scrollbar for the table – always visible height */
.survey-proto .farm-table-wrapper .farm-table-scroll::-webkit-scrollbar {
            height: 12px;
        }
.survey-proto .farm-table-wrapper .farm-table-scroll::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 10px;
        }
.survey-proto .farm-table-wrapper .farm-table-scroll::-webkit-scrollbar-thumb {
            background: var(--gray-400);
            border-radius: 10px;
            border: 2px solid var(--gray-100);
        }
.survey-proto .farm-table-wrapper .farm-table-scroll::-webkit-scrollbar-thumb:hover {
            background: var(--gray-500);
        }
.survey-proto .farm-table-wrapper table.farm-data-table {
            width: max-content;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
            table-layout: fixed;
        }
.survey-proto .farm-table-wrapper table.farm-data-table thead th {
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
.survey-proto .farm-table-wrapper table.farm-data-table .col-sr { width: 48px; min-width: 48px; }
.survey-proto .farm-table-wrapper table.farm-data-table .col-fno { width: 70px; min-width: 70px; }
.survey-proto .farm-table-wrapper table.farm-data-table .col-name { width: 140px; min-width: 140px; }
.survey-proto .farm-table-wrapper table.farm-data-table .col-acreage { width: 70px; min-width: 70px; }
.survey-proto .farm-table-wrapper table.farm-data-table .col-creetable { width: 80px; min-width: 80px; }
.survey-proto .farm-table-wrapper table.farm-data-table .col-govt { width: 75px; min-width: 75px; }
.survey-proto .farm-table-wrapper table.farm-data-table .col-farmer { width: 80px; min-width: 80px; }
.survey-proto .farm-table-wrapper table.farm-data-table .col-plotno { width: 75px; min-width: 75px; }
.survey-proto .farm-table-wrapper table.farm-data-table .col-opno { width: 95px; min-width: 95px; }
.survey-proto .farm-table-wrapper table.farm-data-table .col-sign { width: 70px; min-width: 70px; }
.survey-proto .farm-table-wrapper table.farm-data-table .col-remarks { width: 120px; min-width: 120px; }
.survey-proto .farm-table-wrapper table.farm-data-table .col-action {
            width: 60px;
            min-width: 60px;
            /* Sticky Action column so it stays visible while scrolling */
            position: sticky;
            right: 0;
            z-index: 12;
            background: var(--gray-50);
            box-shadow: -4px 0 8px rgba(0, 0, 0, 0.06);
        }
.survey-proto .farm-table-wrapper table.farm-data-table tbody td {
            padding: 6px 8px;
            border: 1px solid var(--gray-200);
            vertical-align: middle;
            text-align: center;
            font-size: 13px;
            background: #fff;
        }
/* Sticky Action cells in body */
.survey-proto .farm-table-wrapper table.farm-data-table tbody td.col-action,
.survey-proto .farm-table-wrapper table.farm-data-table tbody td:last-child {
            position: sticky;
            right: 0;
            z-index: 5;
            background: #fff;
            box-shadow: -4px 0 8px rgba(0, 0, 0, 0.06);
        }
.survey-proto .farm-table-wrapper table.farm-data-table tbody tr:hover td.col-action,
.survey-proto .farm-table-wrapper table.farm-data-table tbody tr:hover td:last-child {
            background: var(--gray-50);
        }
.survey-proto .farm-table-wrapper table.farm-data-table tbody td .form-control {
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
.survey-proto .farm-table-wrapper table.farm-data-table tbody td .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-100);
        }
.survey-proto .farm-table-wrapper table.farm-data-table tbody td .form-control-sm {
            padding: 4px 6px;
            font-size: 12px;
        }
.survey-proto .farm-table-wrapper table.farm-data-table tbody td .text-left {
            text-align: left;
        }
.survey-proto .farm-table-wrapper table.farm-data-table tbody tr.row-highlight {
            background: var(--primary-50);
        }
.survey-proto .farm-table-wrapper table.farm-data-table tbody tr.row-highlight td {
            border-color: var(--primary-100);
            background: var(--primary-50);
        }
.survey-proto .farm-table-wrapper table.farm-data-table tbody tr.row-highlight td.col-action,
.survey-proto .farm-table-wrapper table.farm-data-table tbody tr.row-highlight td:last-child {
            background: var(--primary-50);
        }
.survey-proto .farm-table-wrapper .table-actions {
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
.survey-proto .farm-table-wrapper .table-actions .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
.survey-proto .farm-table-wrapper .table-actions .totals-display {
            display: flex;
            gap: 16px;
            font-size: 14px;
            flex-wrap: wrap;
        }
.survey-proto .farm-table-wrapper .table-actions .totals-display span {
            white-space: nowrap;
        }
/* Farmer Entry Form for Farm Table */
.survey-proto .farmer-entry-form {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 16px 20px;
            border: 1px dashed var(--gray-300);
            margin-bottom: 12px;
            display: none;
        }
.survey-proto .farmer-entry-form.open {
            display: block;
        }
.survey-proto .farmer-entry-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }
.survey-proto .farmer-entry-form .form-row .full {
            grid-column: 1 / -1;
        }
.survey-proto .farmer-entry-form .form-row input,
.survey-proto .farmer-entry-form .form-row select {
            padding: 8px 12px;
            border: 1.5px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: var(--font);
            width: 100%;
        }
.survey-proto .farmer-entry-form .form-row input:focus,
.survey-proto .farmer-entry-form .form-row select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-100);
        }
/* ============================================================
           COMPENSATION CALCULATOR
           ============================================================ */
.survey-proto .calc-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 16px;
        }
.survey-proto .calc-card {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 20px 24px;
            border: 1px solid var(--gray-200);
            text-align: center;
        }
.survey-proto .calc-card .label {
            font-size: 13px;
            color: var(--gray-500);
            font-weight: 500;
        }
.survey-proto .calc-card .amount {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            margin: 8px 0;
        }
.survey-proto .calc-card .detail {
            font-size: 13px;
            color: var(--gray-600);
        }
.survey-proto .calc-ratio {
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
.survey-proto .calc-ratio .ratio-item {
            text-align: center;
        }
.survey-proto .calc-ratio .ratio-item .num {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
        }
.survey-proto .calc-ratio .ratio-item .label {
            font-size: 12px;
            color: var(--gray-600);
        }
.survey-proto .calc-ratio .ratio-divider {
            font-size: 32px;
            color: var(--gray-400);
            font-weight: 300;
        }
/* ============================================================
           TABS
           ============================================================ */
.survey-proto .tabs {
            display: flex;
            gap: 4px;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
.survey-proto .tabs .tab {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-600);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all var(--transition);
        }
.survey-proto .tabs .tab:hover {
            color: var(--gray-900);
        }
.survey-proto .tabs .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
.survey-proto .tab-panel {
            display: none;
            animation: fadeUp 0.2s ease;
        }
.survey-proto .tab-panel.active {
            display: block;
        }
/* ============================================================
           WORKFLOW PAGE
           ============================================================ */
.survey-proto .workflow-page {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px 0;
        }
.survey-proto .workflow-page .wf-header {
            text-align: center;
            margin-bottom: 32px;
        }
.survey-proto .workflow-page .wf-header h2 {
            font-size: 24px;
        }
.survey-proto .workflow-page .wf-header p {
            color: var(--gray-500);
        }
.survey-proto .wf-chain {
            display: flex;
            flex-direction: column;
            gap: 0;
            position: relative;
            padding-left: 40px;
        }
.survey-proto .wf-chain::before {
            content: '';
            position: absolute;
            left: 14px;
            top: 20px;
            bottom: 20px;
            width: 2px;
            background: var(--gray-300);
        }
.survey-proto .wf-node {
            position: relative;
            padding: 16px 20px 16px 30px;
            background: #fff;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-200);
            margin-bottom: 12px;
            cursor: pointer;
            transition: all var(--transition);
        }
.survey-proto .wf-node:hover {
            box-shadow: var(--shadow-md);
        }
.survey-proto .wf-node::before {
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
.survey-proto .wf-node.done::before {
            background: var(--secondary);
            box-shadow: 0 0 0 2px var(--secondary);
        }
.survey-proto .wf-node.active::before {
            background: var(--accent);
            box-shadow: 0 0 0 2px var(--accent);
            animation: pulse 1.5s infinite;
        }
.survey-proto .wf-node.fail::before {
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
.survey-proto .wf-node .wf-title {
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
.survey-proto .wf-node .wf-title .wf-status {
            font-size: 12px;
            font-weight: 500;
            padding: 2px 14px;
            border-radius: 20px;
        }
.survey-proto .wf-node .wf-title .wf-status.done {
            background: #d5f5e3;
            color: #1a7a3a;
        }
.survey-proto .wf-node .wf-title .wf-status.active {
            background: #fdebd0;
            color: #b9770e;
        }
.survey-proto .wf-node .wf-title .wf-status.waiting {
            background: var(--gray-200);
            color: var(--gray-600);
        }
.survey-proto .wf-node .wf-title .wf-status.fail {
            background: #fadbd8;
            color: #922b21;
        }
.survey-proto .wf-node .wf-meta {
            font-size: 13px;
            color: var(--gray-500);
            margin-top: 6px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
.survey-proto .wf-node .wf-detail {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--gray-100);
            font-size: 14px;
            color: var(--gray-700);
            display: none;
        }
.survey-proto .wf-node .wf-detail.open {
            display: block;
        }
/* OP Scenarios */
.survey-proto .op-scenarios {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }
.survey-proto .op-scenario {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 16px 20px;
            border: 1px solid var(--gray-200);
            font-size: 14px;
        }
.survey-proto .op-scenario .scenario-title {
            font-weight: 600;
            color: var(--primary);
        }
.survey-proto .op-scenario .scenario-desc {
            color: var(--gray-600);
            margin-top: 4px;
            font-size: 13px;
        }
/* ============================================================
           GIS MODULE
           ============================================================ */
.survey-proto .gis-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
.survey-proto .gis-map {
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
.survey-proto .gis-map i {
            font-size: 48px;
            opacity: 0.3;
        }
.survey-proto .gis-map .map-controls {
            position: absolute;
            bottom: 16px;
            left: 16px;
            display: flex;
            gap: 6px;
        }
.survey-proto .gis-map .map-controls button {
            background: #fff;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-xs);
            padding: 6px 12px;
            font-size: 13px;
            cursor: pointer;
            font-family: var(--font);
            box-shadow: var(--shadow-sm);
        }
.survey-proto .gis-map .map-controls button:hover {
            background: var(--gray-50);
        }
.survey-proto .gis-panel {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            padding: 20px 24px;
        }
.survey-proto .gis-panel .layer-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-100);
        }
.survey-proto .gis-panel .layer-item:last-child {
            border-bottom: none;
        }
.survey-proto .gis-panel .layer-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }
/* ============================================================
           EXAMINATION
           ============================================================ */
.survey-proto .exam-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
.survey-proto .exam-card {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            padding: 20px 24px;
            box-shadow: var(--shadow-sm);
        }
.survey-proto .exam-card .exam-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
/* ============================================================
           REPORTS
           ============================================================ */
.survey-proto .report-filters {
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
.survey-proto .report-filters .form-group {
            min-width: 150px;
            flex: 1;
        }
.survey-proto .report-filters .form-group label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--gray-500);
        }
/* ============================================================
           SECTION CARDS
           ============================================================ */
.survey-proto .section-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
.survey-proto .section-card {
            background: #fff;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            padding: 20px 24px;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition);
        }
.survey-proto .section-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
.survey-proto .section-card .section-icon {
            font-size: 32px;
            color: var(--primary);
            opacity: 0.7;
        }
.survey-proto .section-card h4 {
            margin: 8px 0 4px;
        }
.survey-proto .section-card p {
            font-size: 13px;
            color: var(--gray-500);
        }
.survey-proto .section-card .stat {
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-900);
            margin-top: 8px;
        }
/* ============================================================
           QUICK WIN BANNER
           ============================================================ */
.survey-proto .quick-win {
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
.survey-proto .quick-win .badge {
            background: var(--accent);
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
.survey-proto .quick-win h3 {
            font-size: 18px;
            font-weight: 600;
        }
.survey-proto .quick-win p {
            opacity: 0.8;
            font-size: 14px;
        }
/* ============================================================
           PAGE HEADER
           ============================================================ */
.survey-proto .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
.survey-proto .page-header h2 {
            font-size: 20px;
            font-weight: 600;
        }
.survey-proto .page-header p {
            color: var(--gray-500);
            font-size: 14px;
        }
/* ============================================================
           RESPONSIVE
           ============================================================ */
@media (max-width: 1024px) {
.survey-proto .dash-grid {
                grid-template-columns: 1fr;
            }
.survey-proto .gis-layout {
                grid-template-columns: 1fr;
            }
.survey-proto .calc-grid {
                grid-template-columns: 1fr 1fr;
            }
.survey-proto .exam-grid {
                grid-template-columns: 1fr;
            }
.survey-proto .op-scenarios {
                grid-template-columns: 1fr;
            }
.survey-proto .comp-cards {
                grid-template-columns: 1fr;
            }
.survey-proto .preview-grid {
                grid-template-columns: 1fr;
            }
.survey-proto .preview-grid .preview-item.full {
                grid-column: 1;
            }
.survey-proto .farmer-entry-form .form-row {
                grid-template-columns: 1fr 1fr 1fr;
            }
}
@media (max-width: 768px) {
.survey-proto .page-content {
                padding: 16px;
            }
.survey-proto .comp-type-grid {
                grid-template-columns: 1fr;
            }
.survey-proto .kpi-grid {
                grid-template-columns: 1fr 1fr;
            }
.survey-proto .form-grid {
                grid-template-columns: 1fr;
            }
.survey-proto .form-stepper {
                padding: 12px 16px;
                gap: 2px;
            }
.survey-proto .form-stepper .step-indicator .label {
                display: none;
            }
.survey-proto .form-stepper .step-indicator .sep {
                display: none;
            }
.survey-proto .form-body {
                padding: 20px 16px;
            }
.survey-proto .beneficiary-list .list-header,
.survey-proto .beneficiary-list .list-item {
                grid-template-columns: 1fr 1fr;
                gap: 6px;
                font-size: 13px;
            }
.survey-proto .beneficiary-list .list-header .hide-mobile,
.survey-proto .beneficiary-list .list-item .hide-mobile {
                display: none;
            }
.survey-proto .tree-list .list-header,
.survey-proto .tree-list .list-item {
                grid-template-columns: 1fr 1fr;
                gap: 6px;
                font-size: 13px;
            }
.survey-proto .tree-list .list-header .hide-mobile,
.survey-proto .tree-list .list-item .hide-mobile {
                display: none;
            }
.survey-proto .tree-form .form-row {
                grid-template-columns: 1fr 1fr;
            }
.survey-proto .beneficiary-form .form-row {
                grid-template-columns: 1fr;
            }
.survey-proto .table-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
.survey-proto .report-filters {
                flex-direction: column;
            }
.survey-proto .report-filters .form-group {
                min-width: auto;
            }
.survey-proto .workflow-page {
                padding: 0;
            }
.survey-proto .wf-chain {
                padding-left: 28px;
            }
.survey-proto .wf-node {
                padding: 12px 14px 12px 20px;
            }
.survey-proto .wf-node::before {
                left: -24px;
                width: 12px;
                height: 12px;
                top: 20px;
            }
.survey-proto .calc-grid {
                grid-template-columns: 1fr;
            }
.survey-proto .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
.survey-proto .form-actions .left,
.survey-proto .form-actions .right {
                justify-content: center;
            }
.survey-proto .comp-total {
                flex-direction: column;
                text-align: center;
            }
.survey-proto .preview-actions {
                justify-content: center;
            }
/* Farm Table Responsive - keep horizontal scroll + sticky Action */
.survey-proto .farm-table-wrapper table.farm-data-table {
                font-size: 12px;
            }
.survey-proto .farm-table-wrapper table.farm-data-table thead th {
                font-size: 10px;
                padding: 6px 4px;
            }
.survey-proto .farm-table-wrapper table.farm-data-table tbody td {
                padding: 4px 4px;
                font-size: 12px;
            }
.survey-proto .farm-table-wrapper table.farm-data-table tbody td .form-control {
                font-size: 11px;
                padding: 4px 4px;
            }
.survey-proto .farm-table-wrapper .table-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
.survey-proto .farm-table-wrapper .table-actions .action-buttons {
                justify-content: center;
                flex-wrap: wrap;
            }
.survey-proto .farm-table-wrapper .table-actions .totals-display {
                justify-content: center;
                gap: 12px;
                font-size: 13px;
            }
.survey-proto .farmer-entry-form .form-row {
                grid-template-columns: 1fr 1fr;
            }
.survey-proto .page-header {
                flex-direction: column;
                align-items: stretch;
            }
.survey-proto .page-header .btn {
                justify-content: center;
            }
}
@media (max-width: 480px) {
.survey-proto .kpi-grid {
                grid-template-columns: 1fr;
            }
.survey-proto .form-stepper .step-indicator .num {
                width: 28px;
                height: 28px;
                font-size: 11px;
            }
.survey-proto .farmer-entry-form .form-row {
                grid-template-columns: 1fr;
            }
.survey-proto .farm-table-wrapper table.farm-data-table {
                font-size: 11px;
            }
.survey-proto .farm-table-wrapper table.farm-data-table thead th {
                font-size: 9px;
                padding: 4px 3px;
            }
.survey-proto .farm-table-wrapper table.farm-data-table tbody td {
                padding: 3px 3px;
                font-size: 11px;
            }
.survey-proto .farm-table-wrapper table.farm-data-table tbody td .form-control {
                font-size: 10px;
                padding: 3px 3px;
            }
.survey-proto .page-header h2 {
                font-size: 18px;
            }
.survey-proto .farm-table-wrapper .table-actions .action-buttons .btn-sm {
                font-size: 11px;
                padding: 4px 8px;
            }
.survey-proto .farm-table-wrapper .table-actions .totals-display {
                font-size: 12px;
                gap: 8px;
            }
}
/* ============================================================
           UTILITY
           ============================================================ */
.survey-proto .text-center {
            text-align: center;
        }
.survey-proto .mt-16 {
            margin-top: 16px;
        }
.survey-proto .mb-16 {
            margin-bottom: 16px;
        }
.survey-proto .gap-8 {
            gap: 8px;
        }
.survey-proto .flex {
            display: flex;
        }
.survey-proto .flex-center {
            display: flex;
            align-items: center;
            justify-content: center;
        }
.survey-proto .flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
.survey-proto .gap-12 {
            gap: 12px;
        }
.survey-proto .wrap {
            flex-wrap: wrap;
        }
/* Farm Summary Cards */
.survey-proto .farm-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }
.survey-proto .farm-summary .summary-item {
            background: var(--gray-50);
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            border: 1px solid var(--gray-200);
            text-align: center;
        }
.survey-proto .farm-summary .summary-item .number {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }
.survey-proto .farm-summary .summary-item .label {
            font-size: 12px;
            color: var(--gray-500);
        }
/* Ensure table wrapper respects parent width; scroll lives inside */
.survey-proto .farm-table-wrapper {
            max-width: 100%;
        }
.survey-proto .farm-table-wrapper .farm-table-scroll {
            max-width: 100%;
        }
</style>
