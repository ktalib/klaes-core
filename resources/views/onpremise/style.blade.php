<style>
    /* Base styles */
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: #111827;
      background-color: #f9fafb;
    }
     
    .watermark {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) rotate(-45deg);
      font-size: 80px;
      color: #cccccc;
      opacity: 0.2;
      z-index: 0;
      white-space: nowrap;
      pointer-events: none;
    }
    
    /* Enhanced Print styles for A4 landscape responsive printing */
    @media print {
      @page {
        size: A4 landscape;
        margin: 8mm 12mm; /* Optimized A4 landscape margins */
        orphans: 3;
        widows: 3;
      }
      
      @page :first {
        size: A4 landscape !important;
        margin: 8mm 12mm !important;
      }
      
      @page :left {
        size: A4 landscape !important;
        margin: 8mm 12mm !important;
      }
      
      @page :right {
        size: A4 landscape !important;
        margin: 8mm 12mm !important;
      }

      * {
        -webkit-print-color-adjust: exact !important;
        color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      html {
        width: 297mm !important;
        height: 210mm !important;
      }

      body {
        width: 297mm !important;
        height: 210mm !important;
        margin: 0 !important;
        padding: 0 !important;
        font-size: 11pt;
        line-height: 1.3;
        color: #000;
        background: white;
        transform: none !important;
        orientation: landscape !important;
      }

      body * {
        visibility: hidden;
        margin: 0;
        padding: 0;
      }
      
      .print-div, .print-div * {
        visibility: visible;
      }
      
      .print-div {
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        max-width: 297mm !important;
        min-height: 210mm !important;
        padding: 0;
        margin: 0;
        background: white;
        font-family: 'Times New Roman', serif;
        transform: none !important;
        page-break-after: auto !important;
      }

      /* Responsive table handling */
      .print-div table {
        width: 100% !important;
        max-width: 100%;
        font-size: 8pt !important;
        border-collapse: collapse;
        page-break-inside: auto;
        margin: 0;
        table-layout: auto !important;
      }

      .print-div th,
      .print-div td {
        padding: 2px 3px !important;
        border: 1px solid #000 !important;
        font-size: 7pt !important;
        line-height: 1.1 !important;
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        word-break: break-word !important;
      }

      .print-div th {
        background-color: #f0f0f0 !important;
        font-weight: bold;
        text-align: center;
      }

      /* Ensure small datasets fit on one page */
      .print-div .space-y-6 {
        page-break-inside: avoid;
        break-inside: avoid;
      }

      /* Header optimization */
      .print-div .flex-wrap {
        display: flex !important;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
      }

      /* Logo positioning - left and right */
      .print-div .h-16 {
        height: 50px !important;
        width: 50px !important;
        max-width: 50px !important;
        max-height: 50px !important;
      }

      .print-div .w-16 {
        width: 50px !important;
        max-width: 50px !important;
      }

      /* Header text optimization */
      .print-div .text-xl {
        font-size: 12pt !important;
        font-weight: bold;
        text-align: center;
        margin: 0 10px;
      }

      .print-div .text-lg {
        font-size: 10pt !important;
        font-weight: bold;
        text-align: center;
        margin: 2px 0;
      }

      .print-div .text-md {
        font-size: 9pt !important;
        font-weight: bold;
        text-align: center;
        margin: 2px 0;
      }

      /* Watermark optimization - FIXED FOR PRINT */
      .watermark {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) rotate(-45deg) !important;
        font-size: 60px !important;
        color: rgba(200, 200, 200, 0.3) !important;
        z-index: 1000 !important;
        white-space: nowrap !important;
        pointer-events: none !important;
        font-weight: bold !important;
        font-family: 'Arial Black', Arial, sans-serif !important;
        text-transform: uppercase !important;
        letter-spacing: 3px !important;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        -webkit-print-color-adjust: exact !important;
        color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
      
      /* Ensure watermark appears on all print pages */
      @page {
        size: A4 landscape;
        margin: 8mm 12mm;
        orphans: 3;
        widows: 3;
        background: white;
      }
      
      /* Alternative watermark positioning for different browsers */
      .print-div .watermark {
        position: absolute !important;
        top: 45% !important;
        left: 45% !important;
        transform: translate(-50%, -50%) rotate(-45deg) !important;
        font-size: 60px !important;
        color: rgba(150, 150, 150, 0.4) !important;
        z-index: 999 !important;
        white-space: nowrap !important;
        pointer-events: none !important;
        font-weight: 900 !important;
        font-family: 'Arial Black', Arial, sans-serif !important;
        text-transform: uppercase !important;
        letter-spacing: 3px !important;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
      }

      /* Hide non-printable elements */
      button, 
      .hidden-print,
      .actions-column,
      .no-print {
        display: none !important;
        visibility: hidden !important;
      }

      /* Ensure proper table layout */
      .overflow-x-auto {
        overflow: visible !important;
      }

      .min-w-[1000px] {
        min-width: unset !important;
      }

      /* Property details section */
      .print-div .border-black {
        border: 2px solid #000 !important;
      }

      .print-div .bg-gray-100 {
        background-color: #f5f5f5 !important;
      }

      .print-div .bg-gray-200 {
        background-color: #e5e5e5 !important;
      }

      /* Responsive font scaling for small datasets */
      .print-div.small-dataset {
        font-size: 12pt;
      }

      .print-div.small-dataset table {
        font-size: 10pt;
      }

      .print-div.small-dataset th,
      .print-div.small-dataset td {
        padding: 6px 8px;
        font-size: 9pt;
      }

      /* Force single page for small datasets */
      .print-div.force-single-page {
        page-break-after: avoid;
        page-break-inside: avoid;
        break-inside: avoid;
      }

      .print-div.force-single-page table {
        page-break-inside: avoid;
        break-inside: avoid;
      }

      /* Signature and footer area */
      .print-div .mt-8 {
        margin-top: 15px !important;
      }

      .print-div .border-t {
        border-top: 1px solid #000 !important;
        padding-top: 10px !important;
      }

      /* QR Code positioning */
      .print-div #report-qr-code {
        max-width: 70px !important;
        max-height: 70px !important;
        width: 70px !important;
        height: 70px !important;
      }

      /* Timestamp positioning */
      .print-div .text-right {
        text-align: right !important;
      }

      /* Ensure proper page breaks */
      .print-div .mb-6 {
        margin-bottom: 8px !important;
      }

      /* Table row height optimization */
      .print-div tbody tr {
        height: auto;
        min-height: 20px;
      }

      /* Text size adjustments for better readability */
      .print-div .text-sm {
        font-size: 9pt !important;
      }

      .print-div .text-xs {
        font-size: 8pt !important;
      }

      /* Border consistency */
      .print-div .border-gray-300 {
        border-color: #000 !important;
      }
    }
    
    /* Custom components */
    .badge {
      display: inline-flex;
      align-items: center;
      border-radius: 9999px;
      padding: 0.25rem 0.75rem;
      font-size: 0.75rem;
      font-weight: 500;
      line-height: 1;
    }
    
    .badge-outline {
      background-color: transparent;
      border: 1px solid #e5e7eb;
    }
    
    .badge-destructive {
      background-color: #ef4444;
      color: white;
    }
    
    /* Modal styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 50;
    }
    
    .modal-content {
      background-color: white;
      border-radius: 0.5rem;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
      width: 95%;
      max-width: 95%;
      max-height: 90vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    
    .modal-header {
      padding: 1.5rem;
      border-bottom: 1px solid #e5e7eb;
    }
    
    .modal-title {
      font-size: 1.25rem;
      font-weight: 600;
    }
    
    .search-section {
      padding: 1rem 1.5rem;
      border-bottom: 1px solid #e5e7eb;
    }
    
    .results-section {
      flex-grow: 1;
      overflow: auto;
      padding: 1.5rem;
    }
    
    /* Loading spinner */
    .spinner {
      border: 4px solid rgba(0, 0, 0, 0.1);
      width: 36px;
      height: 36px;
      border-radius: 50%;
      border-left-color: #000;
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }
      100% {
        transform: rotate(360deg);
      }
    }
    
    /* Tab styles */
    .tabs {
      display: flex;
      border-bottom: 1px solid #e5e7eb;
    }
    
    .tab {
      padding: 0.75rem 1rem;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      color: #6b7280;
    }
    
    .tab.active {
      border-bottom-color: #000;
      color: #000;
      font-weight: 600;
    }
    
    .tab-content {
      display: none;
    }
    
    .tab-content.active {
      display: block;
    }
    
    /* Table styles */
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    th {
      text-align: left;
      padding: 0.75rem 1rem;
      font-size: 0.75rem;
      font-weight: 500;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      background-color: #f9fafb;
      border-bottom: 1px solid #e5e7eb;
    }
    
    td {
      padding: 0.75rem 1rem;
      font-size: 0.875rem;
      border-bottom: 1px solid #e5e7eb;
    }
    
    tr:hover {
      background-color: #f9fafb;
    }
    
    /* Select dropdown fix */
    .select-wrapper {
      position: relative;
      width: 100%;
    }
    
    .select {
      appearance: none;
      width: 100%;
      padding: 0.5rem 2.5rem 0.5rem 0.75rem;
      font-size: 0.875rem;
      line-height: 1.25rem;
      border: 1px solid #e5e7eb;
      border-radius: 0.375rem;
      background-color: white;
    }
    
    .select-icon {
      position: absolute;
      right: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      pointer-events: none;
    }
    
    /* Form field styles */
    .form-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 0.75rem;
    }
    
    .form-label {
      font-size: 0.875rem;
      color: #6b7280;
      width: 40%;
      padding-top: 0.25rem;
    }
    
    .form-value {
      font-size: 0.875rem;
      font-weight: 500;
      width: 60%;
      text-align: right;
    }
    
    /* Status indicator */
    .status-indicator {
      display: inline-block;
      width: 0.75rem;
      height: 0.75rem;
      border-radius: 50%;
      margin-right: 0.5rem;
    }
    
    /* Hide elements */
    .hidden {
      display: none;
    }
</style>