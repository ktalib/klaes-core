  <style>
    /* Base styles */
  
    /* Card styles */
    .card {
      background-color: white;
      border-radius: 0.5rem;
      border: 1px solid #e5e7eb;
      overflow: hidden;
    }
    
    .card-header {
      padding: 1rem 1.25rem;
    }
    
    .card-title {
      font-size: 1rem;
      font-weight: 500;
      color: #111827;
    }
    
    .card-content {
      padding: 0 1.25rem 1.25rem;
    }
    
    /* Badge styles */
    .badge {
      display: inline-flex;
      align-items: center;
      border-radius: 9999px;
      padding: 0.25rem 0.75rem;
      font-size: 0.75rem;
      font-weight: 500;
      line-height: 1;
    }
    
    .badge-blue {
      background-color: #3b82f6;
      color: white;
    }
    
    .badge-yellow {
      background-color: #f59e0b;
      color: white;
    }
    
    .badge-green {
      background-color: #10b981;
      color: white;
    }

    /* Enhanced Search Input Styles */
    .input[type="search"] {
      transition: all 0.2s ease-in-out;
    }

    .input[type="search"]:focus {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
    }

    /* Search indicator animations */
    @keyframes pulse-green {
      0%, 100% {
        opacity: 1;
        transform: scale(1);
      }
      50% {
        opacity: 0.8;
        transform: scale(0.95);
      }
    }

    .search-count-animate {
      animation: pulse-green 0.3s ease-in-out;
    }

    /* Search result highlighting */
    .search-highlight {
      background-color: #fef3c7;
      padding: 0.125rem 0.25rem;
      border-radius: 0.25rem;
      font-weight: 500;
    }

    /* Improved search container */
    .search-container {
      position: relative;
      transition: all 0.2s ease-in-out;
    }

    .search-container:hover {
      transform: translateY(-1px);
    }

    .search-container.searching {
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }
    
    .badge-purple {
      background-color: #8b5cf6;
      color: white;
    }
    
    .badge-outline {
      background-color: transparent;
      border: 1px solid #e5e7eb;
      color: #6b7280;
    }
    
    /* Tab styles */
    .tabs {
      display: flex;
      border-radius: 0.375rem;
      background-color: #f9fafb;
      padding: 0.25rem;
    }
    
    .tab {
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      border-radius: 0.25rem;
      color: #6b7280;
    }
    
    .tab.active {
      background-color: white;
      color: #111827;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .tab-content {
      display: none;
    }
    
    .tab-content.active {
      display: block;
    }
    
    /* Input styles */
    .input {
      width: 100%;
      padding: 0.5rem 0.75rem;
      font-size: 0.875rem;
      line-height: 1.25rem;
      border: 1px solid #e5e7eb;
      border-radius: 0.375rem;
      background-color: white;
    }
    
    .input:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }
    
    /* Custom checkbox styles */
    input[type="checkbox"] {
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      width: 1rem;
      height: 1rem;
      border: 2px solid #d1d5db;
      border-radius: 0.25rem;
      background-color: white;
      cursor: pointer !important;
      position: relative;
      transition: all 0.2s ease;
      opacity: 1 !important;
      pointer-events: auto !important;
    }
    
    input[type="checkbox"]:hover {
      border-color: #3b82f6;
    }
    
    input[type="checkbox"]:checked {
      background-color: #3b82f6;
      border-color: #3b82f6;
    }
    
    input[type="checkbox"]:checked::after {
      content: '';
      position: absolute;
      left: 0.125rem;
      top: 0.0625rem;
      width: 0.375rem;
      height: 0.625rem;
      border: solid white;
      border-width: 0 2px 2px 0;
      transform: rotate(45deg);
    }
    
    input[type="checkbox"]:focus {
      outline: none;
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }
    
    input[type="checkbox"]:disabled {
      opacity: 0.5;
      cursor: not-allowed !important;
      background-color: #f3f4f6;
      border-color: #d1d5db;
    }
    
    input[type="checkbox"]:disabled:checked {
      background-color: #9ca3af;
      border-color: #9ca3af;
    }
    
    /* Button styles */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
      font-weight: 500;
      border-radius: 0.375rem;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .btn-primary {
      background-color: #111827;
      color: white;
    }
    
    .btn-primary:hover {
      background-color: #1f2937;
    }
    
    .btn-blue {
      background-color: #3b82f6;
      color: white;
    }
    
    .btn-blue:hover {
      background-color: #2563eb;
    }
    
    /* File item styles */
    .file-item {
      display: flex;
      align-items: center;
      padding: 1rem;
      border-bottom: 1px solid #e5e7eb;
    }
    
    .file-item:last-child {
      border-bottom: none;
    }
    
    .file-icon {
      color: #3b82f6;
      margin-right: 1rem;
    }
    
    .file-details {
      flex: 1;
    }
    
    .file-number {
      color: #3b82f6;
      font-weight: 500;
      margin-bottom: 0.25rem;
    }
    
    .file-name {
      color: #4b5563;
      margin-bottom: 0.5rem;
    }
    
    .file-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
    
    .file-tag {
      background-color: #f3f4f6;
      color: #4b5563;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      font-size: 0.75rem;
    }
    
    .file-date {
      color: #6b7280;
      font-size: 0.75rem;
    }
    
    /* Progress bar styles */
    .progress {
      width: 100%;
      height: 0.5rem;
      background-color: #e5e7eb;
      border-radius: 9999px;
      overflow: hidden;
    }
    
    .progress-bar {
      height: 100%;
      border-radius: 9999px;
      transition: width 0.3s ease;
      background-color: #3b82f6;
    }
    
    /* AI Pipeline styles */
    .pipeline {
      display: flex;
      justify-content: space-between;
      position: relative;
      margin: 1.5rem 0;
      padding-top: 0.5rem;
    }
    
    .pipeline-line {
      position: absolute;
      top: 0.75rem;
      left: 0;
      right: 0;
      height: 0.25rem;
      background-color: #e5e7eb;
      z-index: 0;
    }
    
    .pipeline-progress {
      position: absolute;
      top: 0.75rem;
      left: 0;
      height: 0.25rem;
      background-color: #3b82f6;
      z-index: 1;
      transition: width 0.3s ease;
    }
    
    .pipeline-stage {
      display: flex;
      flex-direction: column;
      align-items: center;
      z-index: 2;
    }
    
    .pipeline-dot {
      width: 1rem;
      height: 1rem;
      border-radius: 50%;
      margin-bottom: 0.5rem;
    }
    
    .pipeline-dot.active {
      background-color: #3b82f6;
    }
    
    .pipeline-dot.completed {
      background-color: #3b82f6;
    }
    
    .pipeline-dot.pending {
      background-color: #d1d5db;
    }
    
    .pipeline-label {
      font-size: 0.75rem;
      font-weight: 500;
    }
    
    .pipeline-label.active {
      color: #3b82f6;
    }
    
    .pipeline-label.completed {
      color: #3b82f6;
    }
    
    .pipeline-label.pending {
      color: #6b7280;
    }
    
    /* AI Insights styles */
    .insight-card {
      border: 1px solid #e5e7eb;
      border-radius: 0.5rem;
      padding: 1.25rem;
      margin-bottom: 1rem;
    }
    
    .insight-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 1rem;
    }
    
    .insight-confidence {
      background-color: #ecfdf5;
      color: #047857;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      font-size: 0.75rem;
      font-weight: 500;
    }
    
    .insight-analysis {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
    }
    
    .insight-field {
      display: flex;
      justify-content: space-between;
      margin-bottom: 0.5rem;
    }
    
    .insight-field-label {
      color: #6b7280;
    }
    
    .insight-field-value {
      font-weight: 500;
      display: flex;
      align-items: center;
    }
    
    .insight-confidence-pill {
      background-color: #f3f4f6;
      color: #4b5563;
      padding: 0.125rem 0.375rem;
      border-radius: 0.25rem;
      font-size: 0.625rem;
      margin-left: 0.375rem;
    }
    
    .insight-keywords {
      display: flex;
      flex-wrap: wrap;
      gap: 0.375rem;
      margin-top: 0.5rem;
    }
    
    .insight-keyword {
      background-color: #f3f4f6;
      color: #4b5563;
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      font-size: 0.75rem;
    }
    
    .insight-issues {
      background-color: #fffbeb;
      border: 1px solid #fef3c7;
      border-radius: 0.375rem;
      padding: 0.75rem;
      margin-top: 1rem;
    }
    
    .insight-issues-title {
      color: #92400e;
      font-weight: 500;
      margin-bottom: 0.5rem;
    }
    
    .insight-issues-list {
      color: #b45309;
      font-size: 0.875rem;
      list-style-type: disc;
      padding-left: 1.25rem;
    }
    
    /* Dialog styles */
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
    
    .dialog {
      background-color: white;
      border-radius: 0.5rem;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      width: 100%;
      max-width: 650px;
      max-height: 90vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    
    .dialog-header {
      background-color: #1f2937;
      padding: 1.5rem;
      color: white;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .dialog-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: white;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    
    .dialog-description {
      color: #d1d5db;
      margin-top: 0.25rem;
      font-size: 0.875rem;
    }
    
    .dialog-content {
      padding: 1.5rem;
      overflow-y: auto;
    }
    
    .dialog-footer {
      background-color: #f9fafb;
      padding: 1rem 1.5rem;
      border-top: 1px solid #e5e7eb;
      display: flex;
      justify-content: space-between;
    }
    
    /* Form styles */
    .form-section {
      margin-bottom: 1.5rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid #e5e7eb;
    }
    
    .form-section:last-child {
      margin-bottom: 0;
      padding-bottom: 0;
      border-bottom: none;
    }
    
    .form-section-title {
      font-size: 1.125rem;
      font-weight: 600;
      margin-bottom: 1rem;
    }
    
    .form-group {
      margin-bottom: 1rem;
    }
    
    .form-label {
      display: block;
      font-weight: 500;
      margin-bottom: 0.5rem;
    }
    
    .form-label.required::after {
      content: "*";
      color: #ef4444;
      margin-left: 0.25rem;
    }
    
    .form-info {
      background-color: #ecfdf5;
      border: 1px solid #d1fae5;
      border-radius: 0.375rem;
      padding: 0.75rem;
      margin-bottom: 1rem;
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
    }
    
    .form-info-icon {
      color: #10b981;
      flex-shrink: 0;
    }
    
    .form-info-text {
      font-size: 0.875rem;
      color: #065f46;
    }
    
    .form-help-text {
      font-size: 0.75rem;
      color: #6b7280;
      margin-top: 0.25rem;
    }
    
    .form-radio-group {
      display: flex;
      border: 1px solid #e5e7eb;
      border-radius: 0.25rem;
      overflow: hidden;
      margin-bottom: 1rem;
    }
    
    .form-radio-item {
      flex: 1;
      text-align: center;
      padding: 0.5rem;
      background-color: white;
      cursor: pointer;
      border-right: 1px solid #e5e7eb;
    }
    
    .form-radio-item:last-child {
      border-right: none;
    }
    
    .form-radio-item.active {
      background-color: white;
      font-weight: 500;
    }
    
    .form-radio-item input {
      display: none;
    }
    
    .form-checkbox {
      display: flex;
      align-items: center;
      margin-bottom: 0.5rem;
    }
    
    .form-checkbox input {
      margin-right: 0.5rem;
    }
    
    /* Utility classes */
    .hidden {
      display: none;
    }
    
    .flex {
      display: flex;
    }
    
    .flex-col {
      flex-direction: column;
    }
    
    .items-center {
      align-items: center;
    }
    
    .justify-between {
      justify-content: space-between;
    }
    
    .justify-end {
      justify-content: flex-end;
    }
    
    .gap-2 {
      gap: 0.5rem;
    }
    
    .gap-4 {
      gap: 1rem;
    }
    
    .mb-2 {
      margin-bottom: 0.5rem;
    }
    
    .mb-4 {
      margin-bottom: 1rem;
    }
    
    .mt-2 {
      margin-top: 0.5rem;
    }
    
    .mt-4 {
      margin-top: 1rem;
    }
    
    .ml-2 {
      margin-left: 0.5rem;
    }
    
    .mr-2 {
      margin-right: 0.5rem;
    }
    
    .p-4 {
      padding: 1rem;
    }
    
    .p-6 {
      padding: 1.5rem;
    }
    
    .px-4 {
      padding-left: 1rem;
      padding-right: 1rem;
    }
    
    .py-2 {
      padding-top: 0.5rem;
      padding-bottom: 0.5rem;
    }
    
    .py-4 {
      padding-top: 1rem;
      padding-bottom: 1rem;
    }
    
    .text-xs {
      font-size: 0.75rem;
    }
    
    .text-sm {
      font-size: 0.875rem;
    }
    
    .text-lg {
      font-size: 1.125rem;
    }
    
    .text-xl {
      font-size: 1.25rem;
    }
    
    .text-2xl {
      font-size: 1.5rem;
    }
    
    .text-3xl {
      font-size: 1.875rem;
    }
    
    .font-medium {
      font-weight: 500;
    }
    
    .font-semibold {
      font-weight: 600;
    }
    
    .font-bold {
      font-weight: 700;
    }
    
    .text-gray-500 {
      color: #6b7280;
    }
    
    .text-gray-600 {
      color: #4b5563;
    }
    
    .text-gray-700 {
      color: #374151;
    }
    
    .text-blue-500 {
      color: #3b82f6;
    }
    
    .text-blue-600 {
      color: #2563eb;
    }
    
    .text-purple-600 {
      color: #9333ea;
    }
    
    .text-purple-700 {
      color: #7e22ce;
    }
    
    .bg-purple-50 {
      background-color: #f5f3ff;
    }
    
    .bg-purple-100 {
      background-color: #ede9fe;
    }
    
    .bg-yellow-50 {
      background-color: #fffbeb;
    }
    
    .border {
      border-width: 1px;
      border-style: solid;
      border-color: #e5e7eb;
    }
    
    .border-t {
      border-top-width: 1px;
      border-top-style: solid;
      border-top-color: #e5e7eb;
    }
    
    .rounded {
      border-radius: 0.25rem;
    }
    
    .rounded-md {
      border-radius: 0.375rem;
    }
    
    .rounded-lg {
      border-radius: 0.5rem;
    }
    
    .rounded-full {
      border-radius: 9999px;
    }
    
    .w-full {
      width: 100%;
    }
    
    .container {
      width: 100%;
      max-width: 1280px;
      margin-left: auto;
      margin-right: auto;
      padding-left: 1rem;
      padding-right: 1rem;
    }
    
    .grid {
      display: grid;
    }
    
    .grid-cols-1 {
      grid-template-columns: repeat(1, minmax(0, 1fr));
    }
    
    .grid-cols-2 {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    
    .grid-cols-3 {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    
    .gap-6 {
      gap: 1.5rem;
    }
    
    /* Sub-tabs styles */
    .tracking-sub-tabs {
      background-color: #f9fafb;
    }
    
    .tracking-sub-tab-btn {
      border-color: transparent;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    
    .tracking-sub-tab-btn:hover {
      border-color: #d1d5db;
    }
    
    .tracking-sub-tab-btn.active {
      border-color: #3b82f6;
      color: #3b82f6;
    }
    
    .tracking-sub-content {
      display: block;
    }
    
    .tracking-sub-content.hidden {
      display: none;
    }
    
    /* Table responsiveness with dropdown support */
    #indexed-table-container {
      overflow-x: auto !important;
      overflow-y: visible !important;
      position: relative !important;
    }
    
    #indexed-table-container table {
      position: relative !important;
      min-width: 100% !important;
      width: max-content !important;
    }
    
    /* Ensure table cells with action buttons don't get clipped */
    #indexed-table-container td:last-child {
      position: relative !important;
      overflow: visible !important;
    }
    
    /* Container for action button positioning */
    #indexed-table-container .relative.inline-block.text-left {
      position: static !important;
    }
    
    /* Enhanced Action Dropdown Styles - Fixed positioning to escape table overflow */
    .actions-dropdown-menu {
      position: fixed !important;
      z-index: 9999 !important;
      background: white !important;
      border: 1px solid #e5e7eb !important;
      border-radius: 0.375rem !important;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
      min-width: 192px !important;
      max-width: 280px !important;
      overflow: hidden !important;
    }
    
    .actions-dropdown-menu.hidden {
      display: none !important;
    }
    
    .actions-dropdown-menu:not(.hidden) {
      display: block !important;
    }
    
    .actions-dropdown-menu a,
    .actions-dropdown-menu button {
      display: flex !important;
      align-items: center !important;
      width: 100% !important;
      padding: 0.5rem 0.75rem !important;
      text-align: left !important;
      font-size: 0.875rem !important;
      color: #374151 !important;
      text-decoration: none !important;
      border: none !important;
      background: none !important;
      cursor: pointer !important;
      transition: background-color 0.15s ease-in-out !important;
    }
    
    .actions-dropdown-menu a:hover,
    .actions-dropdown-menu button:hover {
      background-color: #f3f4f6 !important;
      color: #111827 !important;
    }
    
    .actions-dropdown-menu a i,
    .actions-dropdown-menu button i {
      margin-right: 0.5rem !important;
      flex-shrink: 0 !important;
    }

    /* Action Button Visibility */
    .actions-dropdown-btn {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      width: 32px !important;
      height: 32px !important;
      border-radius: 0.375rem !important;
      color: #9ca3af !important;
      transition: all 0.15s ease-in-out !important;
      border: none !important;
      background: none !important;
      cursor: pointer !important;
    }
    
    .actions-dropdown-btn:hover {
      color: #4b5563 !important;
      background-color: #f3f4f6 !important;
    }
    
    .actions-dropdown-btn:focus {
      outline: none !important;
      box-shadow: 0 0 0 2px #3b82f6 !important;
    }

    /* Responsive dropdown behavior */
    @media (max-width: 768px) {
      .actions-dropdown-menu {
        min-width: 160px !important;
        max-width: 240px !important;
        font-size: 0.8rem !important;
      }
      
      .actions-dropdown-menu a,
      .actions-dropdown-menu button {
        padding: 0.6rem 0.75rem !important;
        font-size: 0.8rem !important;
      }
      
      .actions-dropdown-btn {
        width: 28px !important;
        height: 28px !important;
      }
    }

    @media (max-width: 768px) {
      .grid-cols-3 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
      }
      
      .grid-cols-2 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
      }
      
      .insight-analysis {
        grid-template-columns: 1fr;
      }
    }
  </style>