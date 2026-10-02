  <style>
    /* Add custom styles here */
    .badge {
      display: inline-flex;
      align-items: center;
      border-radius: 9999px;
      padding: 0.25rem 0.75rem;
      font-size: 0.75rem;
      font-weight: 500;
    }
    
    .badge-default {
      background-color: #3b82f6;
      color: white;
    }
    
    .badge-outline {
      background-color: transparent;
      border: 1px solid #e5e7eb;
      color: #374151;
    }
    
    .badge-secondary {
      background-color: #e5e7eb;
      color: #374151;
    }
    
    .badge-destructive {
      background-color: #ef4444;
      color: white;
    }
    
    .badge-warning {
      background-color: #f59e0b;
      color: white;
    }

    /* Tab content styles */
    .tab-content {
      display: none;
    }
    
    .tab-content.active {
      display: block;
    }

    /* Batch form styles */
    .file-form.collapsed .form-content {
      display: none;
    }
    
    .file-form .form-header:hover {
      background-color: #f9fafb;
    }
    
    .form-chevron {
      transition: transform 0.2s ease;
    }
    
    .file-form.collapsed .form-chevron {
      transform: rotate(-90deg);
    }

    /* Indexed files table styles */
    .indexed-file-row:hover {
      background-color: #f9fafb;
    }
    
    .indexed-file-checkbox:checked + label {
      background-color: #dbeafe;
    }

    /* Loading animation */
    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }
    
    .animate-spin {
      animation: spin 1s linear infinite;
    }

    /* Responsive grid adjustments */
    @media (max-width: 768px) {
      .grid-cols-7 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.25rem;
      }
      
      .tab-button {
        padding: 0.5rem 0.25rem;
        font-size: 0.75rem;
      }
    }
    
    /* Print styles */
    @media print {
      body * {
        visibility: hidden;
      }
      #print-content, #print-content * {
        visibility: visible;
      }
      #print-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 20px;
        font-size: 12px;
      }
      #print-content h2 {
        font-size: 18px;
        margin: 0;
      }
      #print-content h3 {
        font-size: 16px;
        margin: 0;
      }
      #print-content h4 {
        font-size: 14px;
        margin: 5px 0;
      }
      #print-content table {
        width: 100%;
        border-collapse: collapse;
      }
      #print-content table th,
      #print-content table td {
        border: 1px solid #ddd;
        padding: 6px;
        text-align: left;
      }
      #print-content table th {
        background-color: #f5f5f5;
      }
      #print-content .header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #333;
      }
      #print-content .file-details {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20px;
      }
      #print-content .file-info {
        width: 65%;
      }
      #print-content .qr-section {
        width: 30%;
        text-align: center;
      }
      #print-content .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
      }
      #print-content .info-box {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 10px;
      }
      #print-content .info-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
      }
      #print-content .info-label {
        color: #666;
        font-weight: normal;
      }
      #print-content .info-value {
        font-weight: bold;
      }
      #print-content .signature-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
      }
      #print-content .signature-box {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 10px;
      }
      #print-content .signature-line {
        border-bottom: 1px dashed #000;
        height: 40px;
        margin: 15px 0 10px 0;
      }
      #print-content .footer {
        margin-top: 20px;
        padding-top: 10px;
        border-top: 1px solid #ddd;
        text-align: center;
        font-size: 10px;
        color: #666;
      }
      #print-content .section-title {
        font-weight: bold;
        border-bottom: 1px solid #ddd;
        padding-bottom: 5px;
        margin-bottom: 10px;
      }
      #print-content .rfid-tag {
        display: inline-block;
        padding: 4px 8px;
        background-color: #EFF6FF;
        color: #1D4ED8;
        border: 1px solid #BFDBFE;
        border-radius: 4px;
        font-weight: 500;
      }
    }
  </style>