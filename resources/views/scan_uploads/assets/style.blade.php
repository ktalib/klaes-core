  <style>
    /* CSS Variables for Color Scheme */
    :root {
      --primary: #3b82f6;
      --primary-hover: #2563eb;
      --primary-light: #dbeafe;
      --success: #10b981;
      --success-hover: #059669;
      --success-light: #d1fae5;
      --warning: #f59e0b;
      --warning-hover: #d97706;
      --warning-light: #fef3c7;
      --danger: #ef4444;
      --danger-hover: #dc2626;
      --danger-light: #fee2e2;
      --info: #06b6d4;
      --info-hover: #0891b2;
      --info-light: #cffafe;
      --border: #e5e7eb;
      --border-dark: #d1d5db;
      --muted: #f3f4f6;
      --muted-dark: #e5e7eb;
      --text-primary: #1f2937;
      --text-secondary: #6b7280;
      --text-muted: #9ca3af;
    }

    /* Card styles - Enhanced */
    .card {
      background-color: white;
      border-radius: 0.75rem;
      border: 1px solid var(--border);
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
    }

    .card:hover {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }

    /* Stats Card Enhancement */
    .card .text-2xl.font-bold {
      color: var(--text-primary);
    }

    .card .text-sm.font-medium {
      color: var(--text-secondary);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-size: 0.8rem;
    }

    .card .text-xs.text-muted-foreground {
      color: var(--text-muted);
    }

    /* Button styles - Enhanced */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 0.5rem;
      font-weight: 600;
      font-size: 0.875rem;
      line-height: 1.25rem;
      padding: 0.625rem 1.25rem;
      transition: all 0.2s ease;
      cursor: pointer;
      border: none;
      text-decoration: none;
      white-space: nowrap;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .btn:hover:not(:disabled) {
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .btn:active:not(:disabled) {
      transform: translateY(1px);
    }

    /* Primary Button */
    .btn-primary {
      background-color: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background-color: var(--primary-hover);
      box-shadow: 0 6px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-primary:focus {
      outline: 2px solid var(--primary-light);
      outline-offset: 2px;
    }

    /* Outline Button */
    .btn-outline {
      background-color: transparent;
      border: 1.5px solid var(--border);
      color: var(--text-primary);
    }

    .btn-outline:hover {
      background-color: var(--muted);
      border-color: var(--border-dark);
    }

    /* Ghost Button */
    .btn-ghost {
      background-color: transparent;
      color: var(--text-primary);
    }

    .btn-ghost:hover {
      background-color: var(--muted);
    }

    /* Destructive Button */
    .btn-destructive {
      background-color: var(--danger);
      color: white;
    }

    .btn-destructive:hover {
      background-color: var(--danger-hover);
      box-shadow: 0 6px 12px rgba(239, 68, 68, 0.3);
    }

    .btn-destructive:focus {
      outline: 2px solid var(--danger-light);
      outline-offset: 2px;
    }

    /* Success Button */
    .btn-success {
      background-color: var(--success);
      color: white;
    }

    .btn-success:hover {
      background-color: var(--success-hover);
      box-shadow: 0 6px 12px rgba(16, 185, 129, 0.3);
    }

    /* Small Button */
    .btn-sm {
      padding: 0.375rem 0.875rem;
      font-size: 0.8125rem;
    }

    /* Disabled Button */
    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      box-shadow: none;
    }

    .btn:disabled:hover {
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    /* Button with Gap */
    .btn.gap-1 { gap: 0.25rem; }
    .btn.gap-2 { gap: 0.5rem; }
    .btn.gap-3 { gap: 0.75rem; }

    /* Badge styles - Enhanced */
    .badge {
      display: inline-flex;
      align-items: center;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      line-height: 1;
      padding: 0.375rem 0.75rem;
      white-space: nowrap;
      transition: all 0.2s ease;
    }

    .badge-outline {
      background-color: transparent;
      border: 1px solid var(--border);
      color: var(--text-secondary);
    }

    .badge-secondary {
      background-color: var(--muted);
      color: var(--text-primary);
    }

    .badge-primary {
      background-color: var(--primary-light);
      color: var(--primary);
    }

    .badge-success {
      background-color: var(--success-light);
      color: var(--success);
    }

    .badge-warning {
      background-color: var(--warning-light);
      color: var(--warning);
    }

    .badge-danger {
      background-color: var(--danger-light);
      color: var(--danger);
    }

    .badge-info {
      background-color: var(--info-light);
      color: var(--info);
    }

    /* Input styles - Enhanced */
    .input {
      display: block;
      width: 100%;
      border-radius: 0.5rem;
      border: 1.5px solid var(--border);
      padding: 0.625rem 0.875rem;
      font-size: 0.875rem;
      line-height: 1.25rem;
      background-color: white;
      color: var(--text-primary);
      transition: all 0.2s ease;
    }

    .input::placeholder {
      color: var(--text-muted);
    }

    .input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: inset 0 0 0 1px var(--primary), 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .input:disabled {
      background-color: var(--muted);
      cursor: not-allowed;
      opacity: 0.6;
    }

    /* Select/Dropdown styles */
    select.input {
      cursor: pointer;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3E%3C/svg%3E");
      background-position: right 0.5rem center;
      background-repeat: no-repeat;
      background-size: 1.5em 1.5em;
      padding-right: 2.5rem;
      appearance: none;
    }

    /* Progress bar - Enhanced */
    .progress {
      position: relative;
      width: 100%;
      height: 0.625rem;
      overflow: hidden;
      background-color: var(--muted);
      border-radius: 9999px;
      border: 1px solid var(--border);
    }

    .progress-bar {
      position: absolute;
      height: 100%;
      background: linear-gradient(90deg, var(--primary), var(--info));
      transition: width 0.3s ease;
      border-radius: 9999px;
    }

    /* Dialog styles - Enhanced */
    .dialog-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      z-index: 50;
      animation: fadeIn 0.2s ease-out;
    }

    .dialog-backdrop.is-fullscreen {
      padding: 0;
    }

    .dialog-backdrop.hidden {
      display: none !important;
      visibility: hidden;
    }

    .dialog-content {
      background-color: white;
      border-radius: 0.75rem;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
      width: min(95vw, 32rem);
      max-width: 32rem;
      max-height: 90vh;
      overflow-y: auto;
      border: 1px solid var(--border);
    }

    /* The indexed-file picker carries several stacked controls plus a scrolling
       list; at 32rem they queue up in one thin column. Widened here (not on
       .dialog-content) so every other dialog on the page keeps its size. */
    .dialog-content.dialog-file-selector {
      width: min(96vw, 60rem);
      max-width: 60rem;
    }

    .dialog-content .border-b {
      border-color: var(--border);
    }

    .dialog-content .border-t {
      border-color: var(--border);
    }

    .dialog-preview {
      max-width: 900px;
      height: 800px;
      display: flex;
      flex-direction: column;
    }

    /* Tab styles - Enhanced */
    .tabs {
      display: flex;
      flex-direction: column;
      width: 100%;
    }

    .tabs-list {
      display: flex;
      border-bottom: 2px solid var(--border);
      gap: 1rem;
    }

    .tab {
      padding: 0.875rem 1.25rem;
      font-size: 0.925rem;
      font-weight: 600;
      border-bottom: 3px solid transparent;
      cursor: pointer;
      color: var(--text-secondary);
      transition: all 0.3s ease;
      margin-bottom: -2px;
      text-decoration: none;
    }

    .tab:hover:not([aria-selected="true"]) {
      color: var(--text-primary);
      background-color: rgba(59, 130, 246, 0.05);
    }

    .tab[aria-selected="true"] {
      border-bottom-color: var(--primary);
      color: var(--primary);
      font-weight: 700;
    }

    .tab-content {
      display: none;
      padding-top: 1.5rem;
      animation: fadeIn 0.3s ease-out;
    }

    .tab-content[aria-hidden="false"] {
      display: block;
    }

    /* Radio group - Enhanced */
    .radio-group {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.75rem;
    }

    .radio-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .radio-item input[type="radio"] {
      width: 1rem;
      height: 1rem;
      cursor: pointer;
      accent-color: var(--primary);
    }

    .radio-item label {
      cursor: pointer;
      user-select: none;
    }

    /* Checkbox - Enhanced */
    input[type="checkbox"] {
      width: 1rem;
      height: 1rem;
      cursor: pointer;
      accent-color: var(--primary);
      border-radius: 0.25rem;
    }

    input[type="checkbox"]:focus {
      outline: 2px solid var(--primary-light);
      outline-offset: 2px;
    }

    /* Custom animations - Enhanced */
    @keyframes fadeIn {
      from { 
        opacity: 0;
        transform: scale(0.98);
      }
      to { 
        opacity: 1;
        transform: scale(1);
      }
    }

    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }

    .animate-fade-in {
      animation: fadeIn 0.3s ease-out;
    }

    .animate-pulse {
      animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Hide scrollbar for Chrome, Safari and Opera */
    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }

    /* Hide scrollbar for IE, Edge and Firefox */
    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }

    /* PDF Conversion Styles - Enhanced */
    .pdf-conversion-info {
      background: var(--info-light);
      border: 1.5px solid var(--info);
      border-radius: 0.5rem;
      padding: 0.875rem;
      margin-top: 0.5rem;
      color: var(--info);
      font-size: 0.85rem;
    }

    .pdf-conversion-badge {
      background: var(--success);
      color: white;
      padding: 3px 10px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
      margin-left: 8px;
      display: inline-block;
    }

    /* Image preview styles - Enhanced */
    .document-image {
      max-height: 160px;
      object-fit: contain;
      background: white;
      border: 1px solid var(--border);
      border-radius: 0.5rem;
      padding: 0.5rem;
    }

    .loading-spinner {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 2.5px solid var(--muted);
      border-top-color: var(--primary);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Selected files preview styles - Enhanced */
    .file-preview-thumbnail {
      width: 64px;
      height: 64px;
      border-radius: 0.5rem;
      border: 1.5px solid var(--border);
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, var(--muted) 0%, var(--muted-dark) 100%);
      flex-shrink: 0;
      transition: all 0.2s ease;
    }

    .file-preview-thumbnail img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }

    .file-preview-overlay {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: all 0.2s ease;
    }

    .file-preview-overlay:hover {
      background: rgba(0, 0, 0, 0.4);
      opacity: 1;
    }

    .file-preview-container {
      position: relative;
      display: inline-block;
    }

    .file-preview-container:hover .file-preview-thumbnail {
      transform: scale(1.05);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .file-preview-thumbnail {
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    /* Full preview modal styles - Enhanced */
    .full-preview-modal {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.8);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 100;
      padding: 1rem;
      animation: fadeIn 0.3s ease-out;
    }

    .full-preview-content {
      background: white;
      border-radius: 0.75rem;
      max-width: 90vw;
      max-height: 90vh;
      width: auto;
      display: flex;
      flex-direction: column;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .full-preview-image {
      max-width: 100%;
      max-height: 70vh;
      object-fit: contain;
    }

    /* Toast notification - Enhanced */
    .toast-notification {
      position: fixed;
      top: 1rem;
      right: 1rem;
      background: var(--success);
      color: white;
      padding: 1rem 1.25rem;
      border-radius: 0.5rem;
      box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
      z-index: 1000;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      animation: slideIn 0.3s ease-out;
      font-weight: 600;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .toast-notification.error {
      background: var(--danger);
      box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.3);
    }

    .toast-notification.warning {
      background: var(--warning);
      box-shadow: 0 10px 15px -3px rgba(245, 158, 11, 0.3);
    }

    .toast-notification.info {
      background: var(--info);
      box-shadow: 0 10px 15px -3px rgba(6, 182, 212, 0.3);
    }

    @keyframes slideIn {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    /* Alert/Alert box Styles */
    .alert {
      padding: 1rem;
      border-radius: 0.5rem;
      border: 1.5px solid;
      margin-bottom: 1rem;
    }

    .alert-info {
      background-color: var(--info-light);
      border-color: var(--info);
      color: var(--info);
    }

    .alert-success {
      background-color: var(--success-light);
      border-color: var(--success);
      color: var(--success);
    }

    .alert-warning {
      background-color: var(--warning-light);
      border-color: var(--warning);
      color: var(--warning);
    }

    .alert-danger {
      background-color: var(--danger-light);
      border-color: var(--danger);
      color: var(--danger);
    }

    /* Drag and drop area styling */
    .drop-zone {
      transition: all 0.2s ease;
    }

    .drop-zone.drag-over {
      background-color: var(--primary-light);
      border-color: var(--primary);
      transform: scale(1.02);
    }

    .drop-zone.drag-over .upload-icon {
      color: var(--primary);
      transform: scale(1.1);
    }

    /* Icon styling enhancements */
    [data-lucide] {
      display: inline-block;
      vertical-align: -0.125em;
      transition: all 0.2s ease;
    }

    .btn:hover [data-lucide] {
      transform: translateY(-1px);
    }

    /* Utility classes for text colors */
    .text-primary { color: var(--primary); }
    .text-success { color: var(--success); }
    .text-warning { color: var(--warning); }
    .text-danger { color: var(--danger); }
    .text-info { color: var(--info); }
    .text-muted-foreground { color: var(--text-muted); }

    /* File Card Grid Styles - A4 Paper-like Display */
    #selected-files-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1.5rem;
      padding: 1.5rem;
    }

    .file-card {
      display: flex;
      flex-direction: column;
      background: white;
      border: 2px solid var(--border);
      border-radius: 0.75rem;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      height: 100%;
    }

    .file-card:hover {
      border-color: var(--primary);
      box-shadow: 0 8px 24px rgba(59, 130, 246, 0.2);
      transform: translateY(-4px);
    }

    /* File Card Image Container - A4 Aspect Ratio */
    .file-card-image-container {
      width: 100%;
      aspect-ratio: 210 / 297;
      background: linear-gradient(135deg, var(--muted) 0%, var(--muted-dark) 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      position: relative;
      flex-shrink: 0;
      cursor: zoom-in;
    }

    .file-card-image-container img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      padding: 0.75rem;
      background: white;
    }

    .file-card-image-container svg,
    .file-card-image-container i {
      font-size: 2.5rem;
      color: var(--text-muted);
    }

    /* File Card Content */
    .file-card-content {
      padding: 1rem;
      display: flex;
      flex-direction: column;
      flex-grow: 1;
      gap: 0.75rem;
    }

    .file-card-name {
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-primary);
      word-break: break-word;
      line-height: 1.4;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
    }

    .file-card-badges {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      font-size: 0.7rem;
    }

    .file-card-size {
      font-size: 0.75rem;
      color: var(--text-muted);
      margin-top: auto;
    }

    /* File Card Actions */
    .file-card-actions {
      display: flex;
      gap: 0.5rem;
      padding: 0.75rem;
      border-top: 1px solid var(--border);
      background: var(--muted);
      flex-shrink: 0;
    }

    .file-card-actions button {
      flex: 1;
      padding: 0.5rem 0.75rem;
      font-size: 0.75rem;
      border: 1px solid var(--border);
      border-radius: 0.375rem;
      background: white;
      color: var(--text-primary);
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.25rem;
    }

    .file-card-actions button:hover {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }

    .file-card-actions button.remove-btn:hover {
      background: var(--danger);
      border-color: var(--danger);
    }

    .file-card-actions button i {
      width: 1rem;
      height: 1rem;
    }

    /* Paper Size Badge Colors */
    .badge-a4 {
      background: #e0f2fe;
      color: #0284c7;
    }

    .badge-a5 {
      background: #f5f3ff;
      color: #7c3aed;
    }

    .badge-a3 {
      background: #fef3c7;
      color: #d97706;
    }

    .badge-letter {
      background: #dbeafe;
      color: #3b82f6;
    }

    .badge-legal {
      background: #fce7f3;
      color: #db2777;
    }

    .badge-custom {
      background: #f0fdf4;
      color: #16a34a;
    }

    /* Responsive Grid */
    @media (max-width: 1024px) {
      #selected-files-list {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 1rem;
        padding: 1rem;
      }

      .file-card-content {
        padding: 0.75rem;
      }

      .file-card-actions {
        padding: 0.5rem;
        gap: 0.25rem;
      }
    }

    @media (max-width: 768px) {
      #selected-files-list {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 0.75rem;
        padding: 0.75rem;
      }

      .file-card-image-container {
        aspect-ratio: 210 / 297;
      }

      .file-card-content {
        padding: 0.5rem;
        gap: 0.5rem;
      }

      .file-card-name {
        font-size: 0.75rem;
      }

      .file-card-badges {
        font-size: 0.65rem;
      }

      .file-card-actions button {
        padding: 0.375rem 0.5rem;
        font-size: 0.65rem;
      }
    }

    /* File Selector Styles - Enhanced Selection Indicators */
    #indexed-files-list > div {
      position: relative;
      transition: all 0.2s ease;
      border-left: 3px solid transparent;
    }

    #indexed-files-list > div:hover {
      background-color: rgba(59, 130, 246, 0.08);
      border-left-color: var(--primary);
    }

    #indexed-files-list > div.bg-muted {
      background-color: rgba(59, 130, 246, 0.12);
      border-left: 3px solid var(--primary);
      box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.2);
    }

    #indexed-files-list > div.bg-muted::after {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      bottom: 0;
      left: 0;
      background: linear-gradient(90deg, rgba(59, 130, 246, 0.05) 0%, transparent 100%);
      pointer-events: none;
    }

    /* Selected file badge in header */
    #selected-file-badge {
      animation: slideInRight 0.3s ease-out;
    }

    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(20px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    /* Preview dialog enhancements */
    .dialog-preview-lg {
      width: min(1400px, 98vw);
      max-width: none;
      height: 92vh;
      max-height: 92vh;
      display: flex;
      flex-direction: column;
      padding: 0;
      overflow: hidden;
    }

    .dialog-preview-lg.fullscreen {
      width: 100vw;
      height: 100vh;
      max-width: 100vw;
      max-height: 100vh;
      border-radius: 0;
      border: none;
    }

    .preview-header {
      padding: 1rem 1.5rem;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      background: #fff;
    }

    .preview-header-info {
      display: flex;
      flex-direction: column;
      gap: 0.15rem;
      min-width: 0;
    }

    .preview-title {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--text-primary);
      word-break: break-word;
    }

    .preview-filename {
      font-size: 0.85rem;
      color: var(--text-muted);
      word-break: break-all;
    }

    .preview-header-actions {
      display: flex;
      align-items: center;
      gap: 0.35rem;
    }

    .preview-header-btn {
      width: 38px;
      height: 38px;
      border-radius: 0.5rem;
      border: 1px solid var(--border);
      background: #fff;
      color: var(--text-primary);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s ease;
    }

    .preview-header-btn:hover {
      border-color: var(--primary);
      color: var(--primary);
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }

    .preview-header-btn.is-danger {
      border-color: transparent;
      background: var(--danger-light);
      color: var(--danger);
    }

    .preview-shell {
      flex: 1;
      display: flex;
      min-height: 0;
      background: #f8fafc;
      transition: all 0.3s ease;
    }

    .preview-shell.sidebar-hidden .preview-sidebar {
      transform: translateX(-100%);
      opacity: 0;
      pointer-events: none;
    }

    .preview-shell.sidebar-hidden .preview-stage {
      border-left: none;
    }

    .preview-sidebar {
      flex: 0 0 clamp(180px, 18vw, 280px);
      max-width: 320px;
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      background: #fff;
      transition: transform 0.3s ease, opacity 0.2s ease;
      will-change: transform;
    }

    .preview-sidebar-header {
      padding: 0.85rem 1rem;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-primary);
      display: flex;
      justify-content: space-between;
      border-bottom: 1px solid var(--border);
    }

    .preview-thumbnails {
      flex: 1;
      overflow-y: auto;
      padding: 0.85rem;
      display: flex;
      flex-direction: column;
      gap: 0.65rem;
    }

    .preview-thumbnail {
      border: 1px solid var(--border);
      border-radius: 0.75rem;
      background: #fff;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.5rem 0.65rem;
      transition: all 0.2s ease;
      text-align: left;
    }

    .preview-thumbnail:hover {
      border-color: var(--primary);
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
    }

    .preview-thumbnail.active {
      border-color: var(--primary);
      background: rgba(59, 130, 246, 0.08);
    }

    .preview-thumbnail img {
      width: 48px;
      height: 64px;
      object-fit: cover;
      border-radius: 0.35rem;
      border: 1px solid var(--border);
      background: #fff;
      flex-shrink: 0;
    }

    .preview-thumbnail span {
      font-size: 0.72rem;
      font-weight: 600;
      color: var(--text-primary);
      line-height: 1.2;
      display: block;
    }

    /* Multi-select thumbnail wrapper */
    .preview-thumbnail-wrapper {
      position: relative;
      display: flex;
      align-items: center;
      gap: 0.35rem;
    }

    .preview-thumbnail-wrapper.is-dragging {
      opacity: 0.45;
    }

    .preview-thumbnail-wrapper.is-drag-over .preview-thumbnail {
      border-color: var(--primary);
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
    }

    .preview-thumbnail-wrapper .preview-thumbnail {
      flex: 1;
      min-width: 0;
    }

    .preview-thumbnail-wrapper.is-selected .preview-thumbnail {
      border-color: #ef4444;
      background: rgba(239, 68, 68, 0.06);
    }

    .preview-thumb-checkbox {
      flex-shrink: 0;
      display: flex;
      align-items: center;
      cursor: pointer;
    }

    .preview-thumb-checkbox input[type="checkbox"] {
      width: 16px;
      height: 16px;
      accent-color: #ef4444;
      cursor: pointer;
    }

    /* Sidebar header action buttons */
    .preview-sidebar-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .preview-sidebar-action-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.2rem;
      padding: 0.2rem 0.4rem;
      border: 1px solid var(--border);
      border-radius: 0.375rem;
      background: transparent;
      color: var(--text-secondary, #64748b);
      cursor: pointer;
      font-size: 0.7rem;
      font-weight: 500;
      transition: all 0.15s ease;
    }

    .preview-sidebar-action-btn:hover {
      background: #f1f5f9;
      border-color: var(--primary);
      color: var(--primary);
    }

    .preview-sidebar-action-btn.is-active {
      background: rgba(59, 130, 246, 0.1);
      border-color: var(--primary);
      color: var(--primary);
    }

    .preview-sidebar-action-btn.delete-selected-btn {
      color: #ef4444;
      border-color: #fca5a5;
    }

    .preview-sidebar-action-btn.delete-selected-btn:hover {
      background: #fef2f2;
      border-color: #ef4444;
    }

    .preview-stage {
      flex: 1;
      display: flex;
      flex-direction: column;
      min-width: 0;
      border-left: 1px solid var(--border);
    }

    .preview-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
      padding: 0.85rem 1.25rem;
      border-bottom: 1px solid var(--border);
      background: #fff;
    }

    .preview-toolbar-left,
    .preview-toolbar-right {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    .toolbar-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 0.45rem;
      border: 1px solid var(--border);
      background: #fff;
      color: var(--text-primary);
      transition: all 0.2s ease;
    }

    .toolbar-btn:hover {
      border-color: var(--primary);
      color: var(--primary);
      box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
    }

    .toolbar-btn-edit {
      border-color: rgba(16, 185, 129, 0.4);
      color: #047857;
      background: rgba(209, 250, 229, 0.65);
    }

    .toolbar-btn-edit:hover {
      border-color: #047857;
      color: #065f46;
      background: rgba(16, 185, 129, 0.2);
    }

    .toolbar-btn-delete {
      border-color: rgba(239, 68, 68, 0.4);
      color: #b91c1c;
      background: rgba(254, 226, 226, 0.7);
    }

    .toolbar-btn-delete:hover {
      border-color: #b91c1c;
      color: #991b1b;
      background: rgba(254, 202, 202, 0.9);
    }

    .toolbar-btn:disabled {
      opacity: 0.4;
      pointer-events: none;
    }

    .toolbar-badge {
      padding: 0.25rem 0.75rem;
      border-radius: 0.35rem;
      border: 1px solid var(--border);
      background: #fff;
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--text-primary);
      min-width: 64px;
      text-align: center;
    }

    .preview-nav {
      display: inline-flex;
      border: 1px solid var(--border);
      border-radius: 999px;
      overflow: hidden;
    }

    .preview-nav .toolbar-btn {
      border: none;
      border-radius: 0;
      width: 40px;
    }

    .preview-canvas {
      flex: 1;
      background: #0f172a;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
      padding: 1.25rem;
    }

    .preview-image-wrapper {
      width: 100%;
      height: 100%;
      max-height: 100%;
      position: relative;
      cursor: grab;
      display: flex;
      align-items: center;
      justify-content: center;
      touch-action: none;
    }

    .preview-image-wrapper.dragging {
      cursor: grabbing;
    }

    .preview-image-wrapper img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
      transition: transform 0.1s ease;
      transform-origin: center center;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.35);
      border-radius: 0.5rem;
      background: #fff;
      user-select: none;
      pointer-events: none;
    }

    .crop-overlay {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.25);
      cursor: crosshair;
      touch-action: none;
    }

    .crop-selection {
      position: absolute;
      border: 2px dashed rgba(59, 130, 246, 0.9);
      background: rgba(59, 130, 246, 0.18);
      pointer-events: none;
    }

    .crop-actions {
      position: absolute;
      bottom: 1rem;
      right: 1rem;
      display: flex;
      gap: 0.5rem;
    }

    .preview-meta {
      padding: 0.85rem 1.25rem;
      border-top: 1px solid var(--border);
      background: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .preview-info-badges span {
      display: inline-flex;
      align-items: center;
      padding: 0.2rem 0.6rem;
      border-radius: 999px;
      font-size: 0.75rem;
      background: var(--muted);
      color: var(--text-primary);
      border: 1px solid var(--border);
      margin-right: 0.35rem;
      margin-bottom: 0.25rem;
    }

    @media (max-width: 1280px) {
      .preview-sidebar {
        flex-basis: clamp(160px, 22vw, 260px);
      }
    }

    @media (max-width: 1024px) {
      .preview-shell {
        flex-direction: column;
      }

      .preview-sidebar {
        width: 100%;
        max-width: 100%;
        border-right: none;
        border-bottom: 1px solid var(--border);
        flex-direction: column;
      }

      .preview-thumbnails {
        flex-direction: row;
        overflow-x: auto;
        overflow-y: hidden;
      }

      .preview-thumbnail {
        min-width: 120px;
      }

      .preview-shell.sidebar-hidden .preview-sidebar {
        transform: translateY(-100%);
      }
    }

    @media (max-width: 640px) {
      .preview-header {
        flex-direction: column;
        align-items: flex-start;
      }

      .preview-toolbar {
        flex-direction: column;
        align-items: flex-start;
      }

      .preview-meta {
        flex-direction: column;
        align-items: flex-start;
      }
    }

    /* File item styling */
    #indexed-files-list [data-lucide="folder"] {
      transition: all 0.2s ease;
    }

    #indexed-files-list > div p {
      transition: color 0.2s ease;
    }

    #indexed-files-list > div.bg-muted p:first-child {
      color: var(--primary);
      font-weight: 700;
    }

    /* Confirm button styling */
    #confirm-file-select-btn {
      transition: all 0.3s ease;
    }

    #confirm-file-select-btn:not(:disabled) {
      animation: pulse-subtle 2s ease-in-out infinite;
    }

    @keyframes pulse-subtle {
      0%, 100% {
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
      }
      50% {
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
      }
    }

    #confirm-file-select-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .btn-sm {
        padding: 0.325rem 0.75rem;
        font-size: 0.75rem;
      }

      .radio-group {
        grid-template-columns: 1fr;
      }

      .tabs-list {
        gap: 0.5rem;
      }

      .tab {
        padding: 0.625rem 1rem;
        font-size: 0.8125rem;
      }

      .dialog-content {
        max-width: 95vw;
      }

      .dialog-content.dialog-file-selector {
        width: 95vw;
        max-width: 95vw;
      }

      .dialog-preview {
        max-width: 95vw;
      }

      #selected-file-badge {
        display: none !important;
      }

    }
    #upload-method-options label[for="upload-method-blind"] {
      --source-color: #1d4ed8;
      --source-border: #93c5fd;
      --source-background: #eff6ff;
      --source-hover: #dbeafe;
    }

    #upload-method-options label[for="upload-method-direct"] {
      --source-color: #047857;
      --source-border: #6ee7b7;
      --source-background: #ecfdf5;
      --source-hover: #d1fae5;
    }

    #upload-method-options label {
      background: var(--source-background);
      border-color: var(--source-border);
    }

    #upload-method-options label:hover {
      background: var(--source-hover);
      border-color: var(--source-color);
    }

    #upload-method-options .scan-source-title,
    #upload-method-options input {
      color: var(--source-color);
      accent-color: var(--source-color);
    }

    #upload-method-options label.scan-source-selected,
    #upload-method-options label:focus-within {
      border-color: var(--source-color);
      box-shadow: 0 0 0 2px var(--source-border);
    }

    #browse-large-format-scans {
      background: #7c3aed;
      border-color: #7c3aed;
      color: #fff;
    }

    #browse-large-format-scans:hover:not(:disabled) {
      background: #6d28d9;
      border-color: #6d28d9;
    }

    #browse-large-format-scans:focus-visible {
      outline: 2px solid #7c3aed;
      outline-offset: 3px;
    }

    #browse-large-format-scans:disabled {
      background: #ede9fe;
      border-color: #c4b5fd;
      color: #6d28d9;
      opacity: 0.65;
      cursor: not-allowed;
    }
  </style>
