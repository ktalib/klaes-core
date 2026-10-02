  <style>
        /* Custom styles for the document */
    body {
      font-family: Arial, sans-serif;
      line-height: 1.6;
      color: #333;
    }
    .alpine-modal {
      display: none; /* Hide by default */
    }
    body {
      background-color: #c6e4f9;
      font-family: Arial, sans-serif;
    }

    table {
      border-collapse: collapse;
      width: 100%;
    }
    th, td {
      border: 1px solid #718096;
      padding: 8px;
      text-align: center;
    }
    th {
      background-color: #cbd5e0;
    } 
 
    /* Updated modal styles for Alpine.js compatibility */
    [x-cloak] { display: none !important; }
    
    .alpine-modal {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: 9999;
      background-color: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    
    .alpine-modal-content {
      background-color: white;
      border-radius: 0.5rem;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 500px;
      padding: 1.5rem;
      position: relative;
    }

    .btn {
      padding: 8px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 500;
    }
    .btn-primary {
      background-color: #4299e1;
      color: white;
    }
    .btn-danger {
      background-color: #f56565;
      color: white;
    }
    .btn-success {
      background-color: #48bb78;
      color: white;
    }
    .edit-mode .admin-controls {
      display: block;
    }
    .view-mode .admin-controls {
      display: none;
    }

    /* Print-specific styles */
    @media print {
      /* Set A4 size - 210mm x 297mm */
      @page {
        size: A4 portrait;
        margin: 1cm;
      }
      
      /* Reset body styles for print */
      body {
        background-color: white !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
      }
      
      /* Fix container width to A4 printable area */
      .min-h-screen {
        min-height: auto !important;
        max-width: 100% !important;
        padding: 0.5cm !important;
        margin: 0 !important;
      }
      
      /* Logo image print styles */
      img[src*="logo3.jpeg"] {
        width: 80px !important;
        height: 80px !important;
        object-fit: contain !important;
        display: block !important;
        margin: 0 auto 10px !important;
        print-color-adjust: exact !important;
        -webkit-print-color-adjust: exact !important;
      }
      
      /* Center the ministry header information */
      .flex.flex-col.items-center {
        display: block !important;
        text-align: center !important;
        margin-bottom: 20pt !important;
      }
      
      /* Hide inputs in utilities table and show only text when printing */
      #utilities-table input[type="text"],
      #utilities-table input[type="number"] {
        border: none !important;
        background: transparent !important;
        -webkit-appearance: none !important;
        appearance: none !important;
        padding: 0 !important;
        width: auto !important;
        font-family: inherit !important;
        font-size: inherit !important;
        color: black !important;
        pointer-events: none !important;
      }
      
      /* Hide hidden inputs completely */
      #utilities-table input[type="hidden"] {
        display: none !important;
      }
      
      /* Hide all admin/control elements */
      .admin-controls, #toggle-edit-mode, button, .btn, 
      th.admin-controls, td.admin-controls {
        display: none !important;
      }
      
      /* Ensure tables print properly and don't get split across pages */
      table {
        page-break-inside: avoid;
        width: 100%;
      }
      
      /* Slightly reduce font size to ensure fit */
      body, p, td, th {
        font-size: 11pt !important;
      }
      
      /* Ensure proper space between sections */
      .mb-6 {
        margin-bottom: 16pt !important;
      }
    }
  </style>