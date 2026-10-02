<style>
    /* Custom styles */
    .dialog-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 50;
    }
    
    .dialog-content {
        background-color: white;
        border-radius: 0.5rem;
        padding: 1.5rem;
        max-width: 900px;
        width: 90%;
        max-height: 90vh;
        overflow: hidden; /* Changed from overflow-y: auto to prevent double scrollbars */
        display: flex;
        flex-direction: column;
    }
    
    /* Make internal container scrollable instead of entire dialog */
    .dialog-content form {
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
    }

    .dialog-content .max-h-\[75vh\] {
        overflow-y: auto;
        flex-grow: 1;
        padding-right: 0.75rem; /* Give space for scrollbar */
    }
    
    /* Dialog content for property form specifically */
    .property-form-content {
        max-width: 1000px; /* Even wider for property forms */
    }
    
    /* Add this to ensure close buttons are clickable */
    .dialog-content button[id^="close-"], 
    .dialog-content button[id^="cancel-"] {
        cursor: pointer;
        z-index: 100;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .hidden {
        display: none !important; /* Use !important to override any other styles */
    }
    
    /* Fix for tab content display issues */
    .tab-content {
        display: block; /* Always visible since no more tabs */
    }
    
    /* File number tab content styles */
    .tabcontent {
        display: none !important; /* Default hidden - with important to override any inline styles */
        width: 100%; 
        visibility: hidden;
    }
    
    .tabcontent.active {
        display: block !important; /* Shown when active - with important to override any inline styles */
        visibility: visible;
    }
    
    .badge {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .badge-green {
        background-color: #10b981;
        color: white;
    }
    
    .badge-outline {
        background-color: transparent;
        border: 1px solid #e5e7eb;
    }

    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        margin-bottom: 0.5rem;
        color: #374151;
    }

    .form-input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 0.25rem;
        font-size: 0.875rem;
    }

    .form-select {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 0.25rem;
        font-size: 0.875rem;
        background-color: white;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        border-radius: 0.375rem;
        transition: all 0.2s;
    }

    .btn-primary {
        background-color: #2563eb;
        color: white;
    }

    .btn-primary:hover {
        background-color: #1d4ed8;
    }

    .btn-secondary {
        background-color: white;
        color: #374151;
        border: 1px solid #d1d5db;
    }

    .btn-secondary:hover {
        background-color: #f9fafb;
    }

    .card {
        background-color: white;
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }

    .card-header {
        padding: 1rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .card-body {
        padding: 1rem;
    }

    .table-container {
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th {
        background-color: #f9fafb;
        padding: 0.75rem 1rem;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 500;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid #e5e7eb;
    }

    .table td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #e5e7eb;
        font-size: 0.875rem;
        color: #374151;
    }

    .form-section {
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }

    .form-section-title {
        font-weight: 600;
        margin-bottom: 1rem;
        color: #1e40af;
    }

    /* Special styling for file number fields in edit/view mode */
    .disabled-tab {
        opacity: 0.8;
        cursor: not-allowed;
    }

    .disabled-tab input,
    .disabled-tab select {
        background-color: #f3f4f6;
        color: #6b7280;
        cursor: not-allowed;
    }

    /* Special style for all file tabs being visible */
    .all-tabs-visible .tabcontent {
        display: block !important;
        visibility: visible !important;
        margin-bottom: 1rem;
        opacity: 1 !important;
    }

    /* Ensure file tab headers are properly displayed */
    .file-tab-header {
        font-weight: 500;
        font-size: 0.875rem;
        color: #4b5563;
        margin-bottom: 0.5rem;
    }

    /* Hide tab buttons when all tabs are visible */
    .all-tabs-visible .tab {
        display: none;
    }

    .all-tabs-visible .tabcontent:last-child {
        border-bottom: none;
    }

    /* File tab header in view/edit mode */
    .file-tab-header {
        font-weight: 500;
        font-size: 0.875rem;
        color: #4b5563;
        margin-bottom: 0.5rem;
    }

    /* CSS to help click handling and overlay behavior */
    button svg, 
    button line, 
    button path {
        pointer-events: none;
    }

    /* Make readonly inputs still visible */
    input[readonly].form-input {
        background-color: #f9fafb !important;
        opacity: 1 !important;
        color: #374151;
    }

    /* Assistant Toggle Styles */
    .assistant-toggle {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }

    .assistant-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
        color: #666;
    }

    /* Manual Assistant state (unchecked) */
    .assistant-toggle input:not(:checked) + .slider:before {
        content: "M";
        color: #666;
    }

    /* AI Assistant state (checked) */
    .assistant-toggle input:checked + .slider {
        background-color: #2196F3;
    }

    .assistant-toggle input:checked + .slider:before {
        transform: translateX(26px);
        content: "AI";
        color: #2196F3;
        font-weight: bold;
    }

    .slider.round {
        border-radius: 34px;
    }

    .slider.round:before {
        border-radius: 50%;
    }

    /* Table row selection styles */
    .table tbody tr {
        transition: background-color 0.2s ease;
    }

    .table tbody tr:hover {
        background-color: #f8fafc;
    }

    .table tbody tr.selected-row {
        background-color: #dbeafe !important;
        border-left: 4px solid #3b82f6;
    }

    .table tbody tr.selected-row:hover {
        background-color: #bfdbfe !important;
    }

    /* Selected property detail card styles */
    #selected-property-detail-card {
        animation: slideIn 0.3s ease-out;
        border: 2px solid #3b82f6;
        box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Loading and error card styles */
    #loading-property-card {
        animation: fadeIn 0.2s ease-in;
    }

    #error-property-card {
        animation: fadeIn 0.2s ease-in;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    /* Enhance the selected property card appearance */
    #selected-property-detail-card .bg-blue-100 {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    }

    /* Cursor pointer for clickable table rows */
    .table tbody tr[style*="cursor: pointer"] {
        cursor: pointer !important;
    }

    /* Button hover effects in the detail card */
    #selected-property-detail-card .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
</style>