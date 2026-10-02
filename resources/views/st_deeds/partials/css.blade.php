<style>
    /* Custom styles to match the React components */
    .badge {
      display: inline-flex;
      align-items: center;
      border-radius: 9999px;
      padding: 0.125rem 0.5rem;
      font-size: 0.75rem;
      font-weight: 500;
      line-height: 1;
      white-space: nowrap;
    }
    .badge-pending {
      background-color: #fef9c3;
      color: #854d0e;
      border: 1px solid #fef08a;
    }
    .badge-registered {
      background-color: #dcfce7;
      color: #166534;
      border: 1px solid #bbf7d0;
    }
    .badge-rejected {
      background-color: #fee2e2;
      color: #b91c1c;
      border: 1px solid #fecaca;
    }
    
    /* Instrument Type Badges */
    .badge-st-fragmentation {
      background-color: #ddd6fe;
      color: #5b21b6;
      border: 1px solid #c4b5fd;
    }
    .badge-st-assignment {
      background-color: #dbeafe;
      color: #1e40af;
      border: 1px solid #93c5fd;
    }
    .badge-sectional-titling {
      background-color: #fef3c7;
      color: #d97706;
      border: 1px solid #fcd34d;
    }
    .badge-other-instrument {
      background-color: #f3f4f6;
      color: #374151;
      border: 1px solid #d1d5db;
    }
    
    /* Enhanced Table Styles */
    .enhanced-table {
      border-collapse: separate;
      border-spacing: 0;
    }
    
    .enhanced-table thead th {
      background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
      border-bottom: 2px solid #e2e8f0;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    
    .enhanced-table tbody tr {
      transition: all 0.2s ease-in-out;
    }
    
    .enhanced-table tbody tr:hover {
      background-color: #f8fafc;
      transform: translateY(-1px);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    .enhanced-table tbody tr:nth-child(even) {
      background-color: #fafbfc;
    }
    
    .enhanced-table tbody tr:nth-child(even):hover {
      background-color: #f1f5f9;
    }
    
    .enhanced-table td {
      border-bottom: 1px solid #f1f5f9;
      vertical-align: middle;
    }
    
    /* Status Badge Enhancements */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.025em;
    }
    
    .status-badge::before {
      content: '';
      width: 0.5rem;
      height: 0.5rem;
      border-radius: 50%;
      background-color: currentColor;
    }
    
    /* File Number Styling */
    .file-number {
      font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
      font-weight: 600;
      color: #1e40af;
      background-color: #eff6ff;
      padding: 0.25rem 0.5rem;
      border-radius: 0.375rem;
      border: 1px solid #dbeafe;
    }
    
    /* Action Button Enhancement */
    .action-button {
      transition: all 0.2s ease-in-out;
      border-radius: 0.375rem;
      padding: 0.5rem;
    }
    
    .action-button:hover {
      background-color: #f3f4f6;
      transform: scale(1.1);
    }
    
    /* Search Input Enhancement */
    .search-input {
      transition: all 0.2s ease-in-out;
      border: 2px solid #e5e7eb;
    }
    
    .search-input:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
      outline: none;
    }
    
    /* Table Container Enhancement */
    .table-container {
      border-radius: 0.75rem;
      overflow: hidden;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      border: 1px solid #e5e7eb;
    }
    
    /* Header Enhancement */
    .table-header {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      border-bottom: 2px solid #e2e8f0;
    }
    .tab-active {
      border-bottom: 2px solid #2563eb;
      color: #2563eb;
    }
    .modal {
      display: none;
      position: fixed;
      z-index: 50;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0, 0, 0, 0.5);
    }
    .modal-content {
      background-color: #fefefe;
      margin: 5% auto;
      padding: 20px;
      border: 1px solid #888;
      border-radius: 0.5rem;
      width: 80%;
      max-width: 700px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    .dropdown {
      position: relative;
      display: inline-block;
    }
    .dropdown-content {
      display: none;
      position: absolute;
      right: 0;
      background-color: #f9f9f9;
      min-width: 160px;
      box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
      z-index: 1;
      border-radius: 0.375rem;
    }
    .dropdown-content a {
      color: black;
      padding: 12px 16px;
      text-decoration: none;
      display: block;
    }
    .dropdown-content a:hover {
      background-color: #f1f1f1;
      border-radius: 0.375rem;
    }
    .show {
      display: block;
    }
    .calendar-popup {
      display: none;
      position: absolute;
      background-color: white;
      border: 1px solid #ccc;
      border-radius: 0.375rem;
      padding: 1rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      z-index: 10;
      width: 280px;
    }
    .calendar {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 0.25rem;
    }
    .calendar-header {
      grid-column: span 7;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.5rem;
    }
    .calendar-day {
      width: 2rem;
      height: 2rem;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      border-radius: 9999px;
    }
    .calendar-day:hover {
      background-color: #e5e7eb;
    }
    .calendar-day.selected {
      background-color: #2563eb;
      color: white;
    }
    .calendar-day.today {
      border: 1px solid #2563eb;
    }
    .calendar-weekday {
      text-align: center;
      font-size: 0.75rem;
      color: #6b7280;
      padding: 0.25rem 0;
    }
    /* Add styles for the serial number section */
    .badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      font-size: 0.75rem;
      font-weight: 500;
      border-radius: 0.375rem;
    }
  
    /* Form validation styles */
    input:invalid {
      border-color: #f56565;
    }
  
    .required-asterisk {
      color: #f56565;
    }
  
    /* Batch entry styles */
    .batch-entry {
      transition: all 0.2s ease-in-out;
    }
  
    .batch-entry:hover {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
  
    /* Success & error toast styles */
    #toast {
      transition: all 0.3s ease-in-out;
      transform: translateY(20px);
      opacity: 0;
    }
  
    #toast.show {
      transform: translateY(0);
      opacity: 1;
    }
  
    /* Animation for processing */
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  
    .fa-spin {
      animation: spin 1s linear infinite;
    }
  
    /* Responsive fix for modals */
    @media (max-width: 640px) {
      .modal-content {
        width: 95%;
        margin: 10% auto;
      }
    }

    .action-menu {
      position: fixed;
      z-index: 9999;
      min-width: 12rem;
      border-radius: 0.5rem;
      box-shadow: 0 10px 25px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
      background-color: white;
      border: 1px solid #e5e7eb;
      transition: opacity 0.15s ease-in-out, transform 0.15s ease-in-out;
      transform-origin: top right;
      max-height: calc(100vh - 40px);
      overflow-y: auto;
      backdrop-filter: blur(8px);
    }
    
    .action-menu a {
      display: flex;
      align-items: center;
      padding: 0.75rem 1rem;
      font-size: 0.875rem;
      color: #374151;
      text-decoration: none;
      transition: all 0.2s ease-in-out;
      border-bottom: 1px solid #f3f4f6;
    }
    
    .action-menu a:last-child {
      border-bottom: none;
    }
    
    .action-menu a:hover:not(.cursor-not-allowed) {
      background-color: #f8fafc;
      color: #1f2937;
      transform: translateX(2px);
    }
    
    .action-menu a:first-child {
      border-top-left-radius: 0.5rem;
      border-top-right-radius: 0.5rem;
    }
    
    .action-menu a:last-child {
      border-bottom-left-radius: 0.5rem;
      border-bottom-right-radius: 0.5rem;
    }
    
    .action-menu a.cursor-not-allowed {
      opacity: 0.5;
      cursor: not-allowed;
    }
    
    .action-menu a i {
      width: 1rem;
      margin-right: 0.5rem;
      text-align: center;
    }

    /* Hide Alpine components before being initialized */
    [x-cloak] {
      display: none !important;
    }

    /* Dropdown Menu Styles */
    .dropdown-menu {
        position: fixed;
        z-index: 9999;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        min-width: 160px;
        padding: 4px 0;
        opacity: 0;
        transform: scale(0.95);
        transition: opacity 0.1s ease, transform 0.1s ease;
    }
    
    .dropdown-menu:not(.hidden) {
        opacity: 1;
        transform: scale(1);
    }
    
    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        text-decoration: none;
        color: #374151;
        font-size: 14px;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
        cursor: pointer;
        transition: background-color 0.15s ease;
    }
    
    .dropdown-item:hover:not(.cursor-not-allowed) {
        background-color: #f3f4f6;
    }
    
    .dropdown-item.cursor-not-allowed {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .dropdown-item i {
        flex-shrink: 0;
        width: 16px;
        height: 16px;
    }
  </style>