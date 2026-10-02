    <style>
        .dialog-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 50;
        }
        .dialog-overlay.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dialog-content {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        /* Collapsible department grouping header inside the Requested Files table.
           Each row carries its department's accent as --dept-* custom properties,
           set inline by the renderer, so one rule set covers every department. */
        .department-group-row {
            --dept-accent: #64748b;
            --dept-soft: #f8fafc;
            --dept-border: #e2e8f0;
            background: linear-gradient(90deg, var(--dept-soft) 0%, #ffffff 60%);
            cursor: pointer;
            user-select: none;
            transition: background 0.18s ease, box-shadow 0.18s ease;
        }
        .department-group-row > td {
            /* The accent rail reads as a colour key down the left of the list */
            border-left: 4px solid transparent;
            border-top: 1px solid #eef2f7;
            transition: border-color 0.18s ease;
        }
        .department-group-row:hover > td,
        .department-group-row.is-expanded > td {
            border-left-color: var(--dept-accent);
        }
        .department-group-row:hover {
            background: linear-gradient(90deg, var(--dept-soft) 0%, #ffffff 75%);
        }
        .department-group-row.is-expanded {
            background: linear-gradient(90deg, var(--dept-soft) 0%, #ffffff 80%);
            box-shadow: inset 0 -1px 0 var(--dept-border);
        }
        .department-group-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 2rem;
            height: 2rem;
            border-radius: 0.6rem;
            color: var(--dept-accent);
            background-color: var(--dept-soft);
            border: 1px solid var(--dept-border);
        }
        .department-group-icon-lg {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.5rem;
        }
        .department-group-chevron {
            color: #94a3b8;
            transition: transform 0.18s ease, color 0.18s ease;
        }
        .department-group-row:hover .department-group-chevron {
            color: var(--dept-accent);
        }
        .department-group-row.is-expanded .department-group-chevron {
            transform: rotate(90deg);
            color: var(--dept-accent);
        }
        .department-group-note {
            font-size: 0.68rem;
            color: #94a3b8;
        }
        .department-group-name {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #0f172a;
        }
        .department-group-count {
            padding: 0.1rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--dept-accent);
            background-color: var(--dept-soft);
            border: 1px solid var(--dept-border);
            white-space: nowrap;
        }
        /* Urgency chip on the right of a group header, shown only when the
           department has high-priority requests waiting. */
        .department-group-high {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.1rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.68rem;
            font-weight: 600;
            color: #b91c1c;
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            white-space: nowrap;
        }
        .department-group-hint {
            font-size: 0.68rem;
            font-weight: 500;
            color: #94a3b8;
            white-space: nowrap;
        }
        /* Rows of an expanded group sit under their department's accent rail,
           tinted down so the header still reads as the strongest line.
           The In-Transit tab groups by requester office and reuses the same
           header/rail styling, keyed on its own data attribute. */
        .file-row[data-dept-rows] > td:first-child,
        .file-row[data-office-rows] > td:first-child {
            border-left: 4px solid var(--dept-border, transparent);
        }

        /* File number pill used in the file details column */
        .file-no-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            color: #1e40af;
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            white-space: nowrap;
        }
        /* The file number leads the File Details cell, so it outsizes the title */
        .file-no-badge-lead {
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
        }
        .file-title-sub {
            font-size: 0.78rem;
            font-weight: 500;
            color: #6b7280;
        }
        .file-no-badge .dot {
            width: 5px;
            height: 5px;
            border-radius: 9999px;
            background-color: #3b82f6;
        }
        .tracking-id-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.45rem;
            border-radius: 9999px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.68rem;
            color: #475569;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        /* Initials chip in the Requester column */
        .requester-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #3730a3;
            background-color: #eef2ff;
            border: 1px solid #c7d2fe;
        }

        .priority-LOW { background-color: #10B981; color: white; }
        .priority-MEDIUM { background-color: #F59E0B; color: white; }
        .priority-HIGH { background-color: #EF4444; color: white; }
        .details-dialog {
            max-width: 800px;
            width: 95%;
        }
        .log-entry {
            border-left: 3px solid #3B82F6;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
        .log-entry-active {
            border-left-color: #10B981;
            background-color: #F0F9FF;
        }
        .log-entry-completed {
            border-left-color: #6B7280;
        }
        .dashboard-card {
            transition: all 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .file-status-active {
            background: linear-gradient(90deg, #10B981, #059669);
        }
        .file-status-completed {
            background: linear-gradient(90deg, #6B7280, #4B5563);
        }
        .file-status-pending {
            background: linear-gradient(90deg, #F59E0B, #D97706);
        }
        .animate-pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .8; }
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .file-row {
            transition: all 0.3s ease;
        }
        .file-row:hover {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.05), transparent);
            transform: translateY(-2px);
        }
        #charts-section {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
        }
        #charts-section.show {
            max-height: 1000px;
        }
        .toggle-charts-btn {
            transition: all 0.3s ease;
        }
        .toggle-charts-btn:hover {
            transform: scale(1.05);
        }
        .tab-btn {
            transition: all 0.3s ease;
            padding: 0.6rem 1.8rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            border: 2px solid transparent;
            font-size: 0.95rem;
        }
        .tab-btn.active {
            background: #3B82F6;
            color: white;
            border-color: #3B82F6;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        .tab-btn:not(.active) {
            background: #F3F4F6;
            color: #6B7280;
            border-color: #E5E7EB;
        }
        .tab-btn:not(.active):hover {
            background: #E5E7EB;
            color: #374151;
        }
        .tab-panel {
            display: none;
        }
        .tab-panel.active-panel {
            display: block;
            animation: fadeIn 0.4s ease;
        }
        .print-dropdown {
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 0.5rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            min-width: 220px;
            display: none;
            z-index: 100;
            overflow: hidden;
        }
        .print-dropdown.show {
            display: block;
        }
        .print-dropdown-item {
            padding: 0.75rem 1.5rem;
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid #F3F4F6;
        }
        .print-dropdown-item:hover {
            background: #F3F4F6;
        }
        .print-dropdown-item:last-child {
            border-bottom: none;
        }

        /* Status Badge Styles - Consistent across all tabs */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            gap: 0.3rem;
        }
        .status-badge .dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-right: 4px;
        }
        .status-badge.in-transit { 
            background: #dbeafe; 
            color: #1e40af; 
        }
        .status-badge.in-transit .dot { 
            background: #3b82f6; 
        }
        .status-badge.active { 
            background: #d1fae5; 
            color: #065f46; 
        }
        .status-badge.active .dot { 
            background: #10b981; 
            animation: pulse-dot 2s infinite;
        }
        .status-badge.completed { 
            background: #e2e8f0; 
            color: #475569; 
        }
        .status-badge.completed .dot { 
            background: #94a3b8; 
        }
        .status-badge.idle { 
            background: #fef3c7; 
            color: #92400e; 
        }
        .status-badge.idle .dot { 
            background: #f59e0b; 
        }
        .status-badge.in-progress { 
            background: #dbeafe; 
            color: #1e40af; 
        }
        .status-badge.in-progress .dot { 
            background: #3b82f6; 
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.3); }
        }

        /* Request Sheet Styles */
        .request-sheet-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .request-sheet-overlay.show {
            display: flex;
        }
        .request-sheet-container {
            background: white;
            border-radius: 0.75rem;
            max-width: 1200px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }
        .request-sheet {
            font-family: 'Times New Roman', serif;
            line-height: 1.4;
        }
        .request-sheet .header {
            border-bottom: 3px double #1e293b;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        .request-sheet .header h1 {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .request-sheet .header .subtitle {
            font-size: 1rem;
            color: #475569;
            margin-top: 0.25rem;
        }
        .request-sheet .header .meta-info {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-top: 0.75rem;
            font-size: 0.9rem;
            color: #475569;
            padding-top: 0.5rem;
            border-top: 1px solid #e2e8f0;
            gap: 0.5rem;
        }
        .request-sheet .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
            margin: 1.5rem 0;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
        }
        .request-sheet .summary-stats .stat-item {
            text-align: center;
        }
        .request-sheet .summary-stats .stat-item .stat-value {
            font-size: 1.3rem;
            font-weight: bold;
            color: #1e293b;
        }
        .request-sheet .summary-stats .stat-item .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .request-sheet table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            font-size: 0.85rem;
        }
        .request-sheet table th {
            background: #f1f5f9;
            padding: 0.6rem 0.4rem;
            text-align: left;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #cbd5e1;
            font-weight: 700;
            color: #1e293b;
        }
        .request-sheet table td {
            padding: 0.5rem 0.4rem;
            border: 1px solid #cbd5e1;
            font-size: 0.8rem;
            vertical-align: top;
        }
        .request-sheet table tr:nth-child(even) {
            background: #f8fafc;
        }
        .request-sheet .footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            font-size: 0.85rem;
            color: #475569;
            gap: 1rem;
        }
        .request-sheet .signature-line {
            display: inline-block;
            width: 200px;
            border-bottom: 1px solid #1e293b;
            margin-left: 0.5rem;
        }
        .request-sheet .badge-priority {
            display: inline-block;
            padding: 0.1rem 0.5rem;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .request-sheet .badge-priority.high { background: #fee2e2; color: #991b1b; }
        .request-sheet .badge-priority.medium { background: #fef3c7; color: #92400e; }
        .request-sheet .badge-priority.low { background: #d1fae5; color: #065f46; }

        .request-sheet .status-badge {
            display: inline-block;
            padding: 0.1rem 0.5rem;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .request-sheet .status-badge.active { background: #d1fae5; color: #065f46; }
        .request-sheet .status-badge.completed { background: #e2e8f0; color: #475569; }
        .request-sheet .status-badge.idle { background: #fef3c7; color: #92400e; }
        .request-sheet .status-badge.in-transit { background: #dbeafe; color: #1e40af; }

        .request-sheet .movement-history {
            font-size: 0.7rem;
            color: #475569;
            line-height: 1.3;
        }

        @media print {
            body * { visibility: hidden; }
            .request-sheet-container, .request-sheet-container * { visibility: visible; }
            .request-sheet-container { position: absolute; left: 0; top: 0; width: 100%; padding: 1.5rem; max-height: none; overflow: visible; }
            .request-sheet .no-print { display: none; }
            .request-sheet table th { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .request-sheet table tr:nth-child(even) { background: #f8fafc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .request-sheet .badge-priority.high { background: #fee2e2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .request-sheet .badge-priority.medium { background: #fef3c7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .request-sheet .badge-priority.low { background: #d1fae5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .request-sheet .status-badge.active { background: #d1fae5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .request-sheet .status-badge.completed { background: #e2e8f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .request-sheet .status-badge.idle { background: #fef3c7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .request-sheet .status-badge.in-transit { background: #dbeafe !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
    <!-- Dashboard Content -->
    <div class="p-6">
        <!-- Tabs -->
        <div class="flex gap-3 mb-6 bg-white rounded-lg border border-gray-200 p-2 shadow-sm">
            <button class="tab-btn active" data-tab="requested">
                <i data-lucide="clipboard-list" class="h-4 w-4 inline mr-2"></i>
                Requested Files
            </button>
            <button class="tab-btn" data-tab="transit">
                <i data-lucide="truck" class="h-4 w-4 inline mr-2"></i>
                In-Transit (Movement)
            </button>
            <button class="tab-btn" data-tab="idle">
                <i data-lucide="archive" class="h-4 w-4 inline mr-2"></i>
                Not in Transit (In-Archive)
            </button>
           
            <div class="flex-1"></div>
            <div class="relative">
                <!-- <button id="printMenuBtn" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i data-lucide="printer" class="h-4 w-4 mr-2"></i>
                    Request Sheet
                    <i data-lucide="chevron-down" class="h-4 w-4 ml-2"></i>
                </button> -->
                <div id="printDropdown" class="print-dropdown">
                    <div class="print-dropdown-item" data-period="weekly">
                        <i data-lucide="calendar" class="h-4 w-4 text-blue-600"></i>
                        Weekly Request Sheet
                    </div>
                    <div class="print-dropdown-item" data-period="monthly">
                        <i data-lucide="calendar-days" class="h-4 w-4 text-blue-600"></i>
                        Monthly Request Sheet
                    </div>
                    <div class="print-dropdown-item" data-period="quarterly">
                        <i data-lucide="calendar-range" class="h-4 w-4 text-blue-600"></i>
                        Quarterly Request Sheet
                    </div>
                    <div class="print-dropdown-item" data-period="custom">
                        <i data-lucide="calendar-plus" class="h-4 w-4 text-blue-600"></i>
                        Custom Date Range
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Panels -->
        <!-- Requested Files Panel -->
        <div id="panel-requested" class="tab-panel active-panel">
            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                            <i data-lucide="clipboard-list" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Requested</p>
                            <h3 class="text-2xl font-bold text-gray-900" id="requested-total">0</h3>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-gray-500">
                        <i data-lucide="filter" class="h-4 w-4 text-blue-500 mr-1"></i>
                        <span id="requested-period-label">All requests</span>
                    </div>
                </div>

                <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4">
                            <i data-lucide="alert-triangle" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">High Priority</p>
                            <h3 class="text-2xl font-bold text-gray-900" id="requested-high">0</h3>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-gray-500">
                        <i data-lucide="alert-circle" class="h-4 w-4 text-red-500 mr-1"></i>
                        <span>Requires immediate attention</span>
                    </div>
                </div>

                <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-amber-100 text-amber-600 mr-4">
                            <i data-lucide="gauge" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Medium / Low</p>
                            <h3 class="text-2xl font-bold text-gray-900" id="requested-medium">0</h3>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-gray-500">
                        <i data-lucide="list" class="h-4 w-4 text-amber-500 mr-1"></i>
                        <span>Routine requests</span>
                    </div>
                </div>

                <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                            <i data-lucide="building-2" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Departments</p>
                            <h3 class="text-2xl font-bold text-gray-900" id="requested-departments">0</h3>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-gray-500">
                        <i data-lucide="users" class="h-4 w-4 text-purple-500 mr-1"></i>
                        <span id="requested-top-department">Across all departments</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                            <i data-lucide="clipboard-list" class="h-5 w-5"></i>
                            Requested Files
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Grouped by department — click a department to see its files</p>
                    </div>
                    <div class="flex gap-2 items-center flex-wrap">
                        <div class="relative">
                            <i data-lucide="search" class="h-4 w-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input type="text" id="requested-search" placeholder="Search file no., title or requester..."
                                class="pl-9 pr-3 py-2 w-56 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <select id="requested-filter" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="ALL">All Requests</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                        </select>
                        {{-- This tab paginates by department group, not by row --}}
                        <select id="requested-per-page" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="25">25 departments</option>
                            <option value="50">50 departments</option>
                            <option value="10">10 departments</option>
                            <option value="5">5 departments</option>
                        </select>
                        <button id="requested-toggle-groups" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            <i data-lucide="chevrons-up-down" class="h-4 w-4 mr-1"></i>
                            <span id="requested-toggle-groups-label">Expand all</span>
                        </button>
                        <button id="generateRequestedSheet" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            <i data-lucide="printer" class="h-4 w-4 mr-2"></i>
                            Generate Sheet
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requester Department</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requester</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="requested-table-body" class="bg-white divide-y divide-gray-200">
                            <!-- Requested files will be dynamically added here -->
                        </tbody>
                    </table>
                </div>
                <div id="requested-loading" class="hidden p-12 text-center text-gray-500">
                    <i data-lucide="loader-2" class="h-8 w-8 mx-auto mb-3 animate-spin text-gray-400"></i>
                    Loading requested files...
                </div>
                <div id="requested-no-results" class="hidden p-12 text-center">
                    <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <i data-lucide="clipboard-x" class="h-12 w-12 text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No requested files</h3>
                    <p class="text-gray-500">Files requested by the Commissioner will appear here.</p>
                </div>
                <div id="requested-pagination" class="hidden px-6 py-3 border-t border-gray-200 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-600" id="requested-pagination-summary"></p>
                    <div class="flex items-center gap-1" id="requested-pagination-controls"></div>
                </div>
            </div>
        </div>

        <!-- In Transit Panel -->
        <div id="panel-transit" class="tab-panel">
            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                            <i data-lucide="file-text" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Files</p>
                            <h3 class="text-2xl font-bold text-gray-900" id="total-files">0</h3>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-gray-500">
                        <i data-lucide="trending-up" class="h-4 w-4 text-green-500 mr-1"></i>
                        <span>12% increase from last week</span>
                    </div>
                </div>

                <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                            <i data-lucide="activity" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Active Files</p>
                            <h3 class="text-2xl font-bold text-gray-900" id="active-files">0</h3>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-gray-500">
                        <i data-lucide="clock" class="h-4 w-4 text-blue-500 mr-1"></i>
                        <span>Currently being processed</span>
                    </div>
                </div>

                <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-600 mr-4">
                            <i data-lucide="alert-triangle" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">High Priority</p>
                            <h3 class="text-2xl font-bold text-gray-900" id="high-priority">0</h3>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-gray-500">
                        <i data-lucide="alert-circle" class="h-4 w-4 text-red-500 mr-1"></i>
                        <span>Requires immediate attention</span>
                    </div>
                </div>

                <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                            <i data-lucide="building" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Offices Involved</p>
                            <h3 class="text-2xl font-bold text-gray-900" id="offices-involved">0</h3>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <div class="flex items-center text-sm text-gray-500">
                            <i data-lucide="map-pin" class="h-4 w-4 text-purple-500 mr-1"></i>
                            <span>Across all departments</span>
                        </div>
                        <button id="toggleChartsBtn" class="toggle-charts-btn inline-flex items-center px-3 py-1.5 border border-purple-300 rounded-md text-sm font-medium text-purple-700 bg-purple-50 hover:bg-purple-100">
                            <i data-lucide="bar-chart-3" class="h-4 w-4 mr-1"></i>
                            <span>Show Charts</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div id="charts-section" class="mb-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Priority Distribution</h3>
                            <div class="flex items-center space-x-2">
                                <button class="priority-filter-btn px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700" data-priority="ALL">All</button>
                                <button class="priority-filter-btn px-3 py-1 text-xs font-medium rounded-full text-gray-500 hover:bg-gray-100" data-priority="HIGH">High</button>
                                <button class="priority-filter-btn px-3 py-1 text-xs font-medium rounded-full text-gray-500 hover:bg-gray-100" data-priority="MEDIUM">Medium</button>
                                <button class="priority-filter-btn px-3 py-1 text-xs font-medium rounded-full text-gray-500 hover:bg-gray-100" data-priority="LOW">Low</button>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="priority-chart"></canvas>
                        </div>
                    </div>

                    <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Office Distribution</h3>
                            <div class="flex items-center space-x-2">
                                <button class="office-filter-btn px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700" data-office="ALL">All</button>
                                <button class="office-filter-btn px-3 py-1 text-xs font-medium rounded-full text-gray-500 hover:bg-gray-100" data-office="TOP5">Top 5</button>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="office-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="search" class="h-4 w-4 text-gray-400"></i>
                            </div>
                            <input type="text" id="search-input" placeholder="Search files by name, number, or tracking ID..." class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <select id="priority-filter" class="block w-full md:w-auto px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="ALL">All Priorities</option>
                            <option value="HIGH">High Priority</option>
                            <option value="MEDIUM">Medium Priority</option>
                            <option value="LOW">Low Priority</option>
                        </select>
                        {{-- Offices are populated from the live in-transit distribution --}}
                        <select id="office-filter" class="block w-full md:w-auto px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="ALL">All Offices</option>
                        </select>
                        {{-- This tab paginates by requester-office group, not by row --}}
                        <select id="transit-per-page" class="block w-full md:w-auto px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="25">25 offices</option>
                            <option value="50">50 offices</option>
                            <option value="10">10 offices</option>
                            <option value="5">5 offices</option>
                        </select>
                        <button id="transit-toggle-groups" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            <i data-lucide="chevrons-up-down" class="h-4 w-4 mr-1"></i>
                            <span id="transit-toggle-groups-label">Expand all</span>
                        </button>
                        <button id="clear-filters" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            <i data-lucide="x" class="h-4 w-4 mr-1"></i>
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Files Table -->
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <i data-lucide="files" class="h-5 w-5"></i>
                        Files in Transit
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Grouped by requester's office — click an office to see its files</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requester's Office</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time in Office</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="files-table-body" class="bg-white divide-y divide-gray-200">
                            <!-- Files will be dynamically added here -->
                        </tbody>
                    </table>
                </div>
                <div id="transit-loading" class="hidden p-12 text-center text-gray-500">
                    <i data-lucide="loader-2" class="h-8 w-8 mx-auto mb-3 animate-spin text-gray-400"></i>
                    Loading files in transit...
                </div>
                <div id="no-results" class="hidden p-12 text-center">
                    <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <i data-lucide="file-x" class="h-12 w-12 text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No files found</h3>
                    <p class="text-gray-500">Try adjusting your search or filter to find what you're looking for.</p>
                </div>
                <div id="transit-pagination" class="hidden px-6 py-3 border-t border-gray-200 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-600" id="transit-pagination-summary"></p>
                    <div class="flex items-center gap-1" id="transit-pagination-controls"></div>
                </div>
            </div>
        </div>

        <!-- Not in Transit Panel -->
        <div id="panel-idle" class="tab-panel">
            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-gray-100 text-gray-600 mr-4">
                            <i data-lucide="archive" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Files in Registry</p>
                            <h3 class="text-2xl font-bold text-gray-900" id="idle-total">0</h3>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                            <i data-lucide="clock" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Never Moved</p>
                            <h3 class="text-2xl font-bold text-gray-900" id="idle-awaiting">0</h3>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                            <i data-lucide="check-circle" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Returned to Registry</p>
                            <h3 class="text-2xl font-bold text-gray-900" id="idle-completed">0</h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                            <i data-lucide="archive" class="h-5 w-5"></i>
                            Files Not in Transit
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">Every indexed file currently sitting in the registry</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <i data-lucide="search" class="h-4 w-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input type="text" id="idle-search" placeholder="Search file no. or title..."
                                class="pl-9 pr-3 py-2 w-64 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <select id="idle-per-page" class="py-2 px-3 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                            <option value="100">100 / page</option>
                            <option value="200">200 / page</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody id="idle-table-body" class="bg-white divide-y divide-gray-200">
                            <!-- Idle files will be dynamically added here -->
                        </tbody>
                    </table>
                </div>
                <div id="idle-loading" class="hidden p-12 text-center text-gray-500">
                    <i data-lucide="loader-2" class="h-8 w-8 mx-auto mb-3 animate-spin text-gray-400"></i>
                    Loading files...
                </div>
                <div id="idle-no-results" class="hidden p-12 text-center">
                    <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <i data-lucide="inbox" class="h-12 w-12 text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No files found</h3>
                    <p class="text-gray-500">No indexed files match the current search.</p>
                </div>
                <div id="idle-pagination" class="hidden px-6 py-3 border-t border-gray-200 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-600" id="idle-pagination-summary"></p>
                    <div class="flex items-center gap-1" id="idle-pagination-controls"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Sheet Modal -->
    <div id="requestSheetOverlay" class="request-sheet-overlay">
        <div class="request-sheet-container">
            <div class="flex justify-between items-center mb-4 no-print">
                <h2 class="text-xl font-bold text-gray-900">File Request Sheet</h2>
                <div class="flex gap-2 items-center">
                    <label for="sheet-department" class="text-sm font-medium text-gray-600">Department</label>
                    <select id="sheet-department" class="border border-gray-300 rounded-md shadow-sm px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All departments</option>
                    </select>
                    <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        <i data-lucide="printer" class="h-4 w-4 mr-2"></i>
                        Print
                    </button>
                    <button id="closeRequestSheet" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i data-lucide="x" class="h-4 w-4 mr-2"></i>
                        Close
                    </button>
                </div>
            </div>
            <div id="requestSheetContent" class="request-sheet">
                <!-- Request sheet content will be dynamically generated -->
            </div>
        </div>
    </div>

    <!-- View Details Dialog -->
    <div id="details-dialog" class="dialog-overlay">
        <div class="dialog-content details-dialog">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">File Tracker Details</h2>
                <button id="close-details" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>
            <div id="details-content">
                <!-- Details content will be dynamically generated -->
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button id="close-details-btn" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Close
                </button>
                <button id="print-details-btn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    <i data-lucide="printer" class="h-4 w-4 mr-2"></i>
                    Request Sheet
                </button>
            </div>
        </div>
    </div>

