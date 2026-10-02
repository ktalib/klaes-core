<style>
    /* ============================================================
       SURVEY MODULE — DESIGN SYSTEM (ported from Survey-Module prototype)
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
        --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.12);
        --radius: 12px;
        --radius-sm: 8px;
        --radius-xs: 4px;
        --transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        --sidebar-width: 260px;
        --header-height: 64px;
        --font: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    /* KPI CARDS */
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
    .kpi-card .kpi-sub {
        font-size: 12px;
        color: var(--gray-500);
        margin-top: 6px;
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

    /* STATUS / PAGE HEADER */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        gap: 16px;
        flex-wrap: wrap;
    }
    .page-header h2 {
        font-size: 22px;
        font-weight: 700;
        color: var(--gray-900);
        letter-spacing: -0.3px;
    }
    .page-header p {
        font-size: 14px;
        color: var(--gray-500);
        margin-top: 4px;
    }
    .page-header .header-actions {
        display: flex;
        gap: 8px;
    }

    .status-badge,
    .status-badge-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-badge .dot,
    .status-badge-pill .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }
    .status-badge.active { background: #d5f5e3; color: #1a7a3a; }
    .status-badge.active .dot { background: #1a7a3a; }
    .status-badge.pending { background: #fdebd0; color: #b9770e; }
    .status-badge.pending .dot { background: #b9770e; }
    .status-badge.completed { background: #d6eaf8; color: #1a5276; }
    .status-badge.completed .dot { background: #1a5276; }
    .status-badge.rejected { background: #fadbd8; color: #922b21; }
    .status-badge.rejected .dot { background: #922b21; }
    .status-badge.review { background: #ebf5fb; color: #2c7fb8; }
    .status-badge.review .dot { background: #2c7fb8; }
    .status-badge.approved { background: #d5f5e3; color: #1a7a3a; }
    .status-badge.approved .dot { background: #1a7a3a; }

    /* TABLES */
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
    .table-wrapper table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .table-wrapper table thead {
        background: var(--gray-50);
    }
    .table-wrapper table thead th {
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
    .table-wrapper table tbody td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--gray-100);
        color: var(--gray-700);
        vertical-align: middle;
    }
    .table-wrapper table tbody tr:hover {
        background: var(--gray-50);
    }
    .table-wrapper table tbody tr:last-child td {
        border-bottom: none;
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

    .action-icons {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
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

    /* BUTTONS */
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
    .btn-primary { background: var(--primary); color: #fff; }
    .btn-primary:hover { background: var(--primary-light); box-shadow: var(--shadow-md); }
    .btn-secondary { background: var(--gray-200); color: var(--gray-700); }
    .btn-secondary:hover { background: var(--gray-300); }
    .btn-success { background: var(--secondary); color: #fff; }
    .btn-success:hover { background: var(--secondary-dark); }
    .btn-danger { background: var(--danger); color: #fff; }
    .btn-danger:hover { background: var(--danger-dark); }
    .btn-outline {
        background: transparent;
        border: 1.5px solid var(--gray-300);
        color: var(--gray-700);
    }
    .btn-outline:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-50); }
    .btn-sm { padding: 5px 12px; font-size: 12px; }
    .btn-xs { padding: 3px 10px; font-size: 11px; }

    /* WORKFLOW TIMELINE */
    .workflow-timeline { display: flex; flex-direction: column; gap: 12px; }
    .workflow-step { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius); padding: 14px 16px; }
    .workflow-step .step-icon {
        width: 36px; height: 36px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 14px; flex-shrink: 0; font-weight: 600;
    }
    .workflow-step .step-icon.done { background: var(--secondary); color: #fff; }
    .workflow-step .step-icon.pending { background: var(--accent); color: #fff; }
    .workflow-step .step-icon.waiting { background: var(--gray-300); color: var(--gray-600); }
    .workflow-step .step-icon.fail { background: var(--danger); color: #fff; }
    .workflow-step .step-info { flex: 1; }
    .workflow-step .step-info .step-name { font-size: 14px; font-weight: 500; }
    .workflow-step .step-info .step-status { font-size: 12px; color: var(--gray-500); }
    .workflow-step .step-time { font-size: 12px; color: var(--gray-500); }

    /* DASHBOARD GRID + CARDS */
    .dash-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px; }
    @media (max-width: 900px) { .dash-grid { grid-template-columns: 1fr; } }
    .dash-card {
        background: #fff; border-radius: var(--radius); padding: 20px 24px;
        box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200);
    }
    .dash-card .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
    .dash-card .card-header h3 { font-size: 15px; font-weight: 600; }
    .dash-card .card-header a { font-size: 13px; color: var(--primary); text-decoration: none; font-weight: 500; }
    .dash-card .card-header a:hover { text-decoration: underline; }

    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .section-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
    .section-card {
        background: #fff; border-radius: var(--radius); padding: 20px;
        box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200);
    }
    .section-card .section-icon { font-size: 20px; color: var(--primary); margin-bottom: 10px; }
    .section-card h4 { font-size: 16px; font-weight: 600; color: var(--gray-800); }
    .section-card .stat { font-size: 24px; font-weight: 700; color: var(--gray-900); margin: 6px 0 2px; }
    .section-card p { font-size: 12px; color: var(--gray-500); }

    /* ACTIVITY FEED */
    .activity-feed { display: flex; flex-direction: column; gap: 14px; }
    .activity-item { display: flex; gap: 12px; padding-bottom: 14px; border-bottom: 1px solid var(--gray-100); }
    .activity-item:last-child { border-bottom: none; padding-bottom: 0; }
    .activity-item .activity-icon {
        width: 32px; height: 32px; border-radius: 50%;
        background: var(--primary-50); color: var(--primary);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 13px;
    }
    .activity-item .activity-content { flex: 1; }
    .activity-item .activity-content .text { font-size: 14px; color: var(--gray-700); }
    .activity-item .activity-content .text strong { color: var(--gray-900); }
    .activity-item .activity-content .time { font-size: 12px; color: var(--gray-500); }

    /* FORMS */
    .form-container {
        background: #fff; border-radius: var(--radius);
        border: 1px solid var(--gray-200); box-shadow: var(--shadow-lg); overflow: hidden;
    }
    .form-body { padding: 32px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 28px; }
    .form-grid .full { grid-column: 1 / -1; }
    .form-group { display: flex; flex-direction: column; gap: 4px; }
    .form-group label { font-size: 13px; font-weight: 500; color: var(--gray-700); }
    .form-group .required { color: var(--danger); }
    .form-group input, .form-group select, .form-group textarea {
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
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-100);
    }
    .form-actions {
        display: flex; justify-content: space-between; align-items: center;
        padding-top: 24px; border-top: 1px solid var(--gray-200); margin-top: 28px; gap: 12px; flex-wrap: wrap;
    }

    .required { color: var(--danger); }
    .map-placeholder {
        background: var(--gray-100); border-radius: var(--radius-sm); height: 200px;
        display: flex; align-items: center; justify-content: center;
        color: var(--gray-500); border: 1px solid var(--gray-300);
        flex-direction: column; gap: 8px; cursor: pointer; transition: all var(--transition);
    }
    .map-placeholder:hover { border-color: var(--primary); background: var(--primary-50); }
    .upload-area {
        border: 2px dashed var(--gray-300); border-radius: var(--radius-sm);
        padding: 30px; text-align: center; color: var(--gray-500);
        cursor: pointer; transition: all var(--transition);
    }
    .upload-area:hover { border-color: var(--primary); background: var(--primary-50); }
</style>