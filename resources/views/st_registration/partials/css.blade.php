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
      position: fixed; /* Keep this as fixed */
      z-index: 1000; /* Make sure this is high enough */
      min-width: 12rem;
      border-radius: 0.375rem;
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
      background-color: white;
      border: 1px solid #e5e7eb;
      transition: opacity 0.1s ease-in-out, transform 0.1s ease-in-out;
      transform-origin: top right;
      max-height: calc(100vh - 20px); /* Prevent menu from extending beyond viewport */
      overflow-y: auto; /* Add scrolling if menu is too tall */
    }
    
    .action-menu a {
      display: flex;
      align-items: center;
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
      color: #374151;
      transition: background-color 0.2s;
    }
    
    .action-menu a:hover {
      background-color: #f3f4f6;
    }

    /* Hide Alpine components before being initialized */
    [x-cloak] {
      display: none !important;
    }
  </style>