  <style>
    /* Base styles */
    :root {
      --primary: #3b82f6;
      --primary-foreground: #ffffff;
      --muted: #f3f4f6;
      --muted-foreground: #6b7280;
      --border: #e5e7eb;
      --ring: #3b82f6;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: #0f172a;
      background-color: #f8fafc;
    }

    /* Card styles */
    .card {
      background-color: white;
      border-radius: 0.5rem;
      border: 1px solid var(--border);
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* Button styles */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 0.375rem;
      font-weight: 500;
      font-size: 0.875rem;
      line-height: 1.25rem;
      padding: 0.5rem 1rem;
      transition: all 0.2s;
      cursor: pointer;
    }

    .btn-primary {
      background-color: var(--primary);
      color: var(--primary-foreground);
    }

    .btn-primary:hover {
      background-color: #2563eb;
    }

    .btn-outline {
      background-color: transparent;
      border: 1px solid var(--border);
    }

    .btn-outline:hover {
      background-color: var(--muted);
    }

    .btn-ghost {
      background-color: transparent;
    }

    .btn-ghost:hover {
      background-color: var(--muted);
    }

    .btn-sm {
      padding: 0.25rem 0.5rem;
      font-size: 0.75rem;
    }

    .btn-lg {
      padding: 0.75rem 1.5rem;
      font-size: 1rem;
    }

    .btn-icon {
      padding: 0.25rem;
    }

    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Badge styles */
    .badge {
      display: inline-flex;
      align-items: center;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
      line-height: 1;
      padding: 0.25rem 0.5rem;
      white-space: nowrap;
    }

    .badge-outline {
      background-color: transparent;
      border: 1px solid var(--border);
    }

    .badge-secondary {
      background-color: #f3f4f6;
      color: #1f2937;
    }

    /* Input styles */
    .input {
      display: block;
      width: 100%;
      border-radius: 0.375rem;
      border: 1px solid var(--border);
      padding: 0.5rem 0.75rem;
      font-size: 0.875rem;
      line-height: 1.25rem;
      background-color: white;
    }

    .input:focus {
      outline: none;
      border-color: var(--ring);
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
    }

    /* Textarea styles */
    .textarea {
      display: block;
      width: 100%;
      border-radius: 0.375rem;
      border: 1px solid var(--border);
      padding: 0.5rem 0.75rem;
      font-size: 0.875rem;
      line-height: 1.25rem;
      background-color: white;
      resize: vertical;
    }

    .textarea:focus {
      outline: none;
      border-color: var(--ring);
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
    }

    /* Progress bar */
    .progress {
      position: relative;
      width: 100%;
      height: 0.5rem;
      overflow: hidden;
      background-color: var(--muted);
      border-radius: 9999px;
    }

    .progress-bar {
      position: absolute;
      height: 100%;
      background-color: var(--primary);
      transition: width 0.3s ease;
    }

    /* Tab styles */
    .tabs {
      display: flex;
      flex-direction: column;
      width: 100%;
    }

    .tabs-list {
      display: flex;
      border-bottom: 1px solid var(--border);
    }

    .tab {
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
      font-weight: 500;
      border-bottom: 2px solid transparent;
      cursor: pointer;
    }

    .tab[aria-selected="true"] {
      border-bottom-color: var(--primary);
      color: var(--primary);
    }

    .tab[aria-disabled="true"] {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .tab-content {
      display: none;
      padding-top: 1.5rem;
    }

    .tab-content[aria-hidden="false"] {
      display: block;
    }

    /* Custom animations */
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .animate-spin {
      animation: spin 1s linear infinite;
    }

    /* Hide scrollbar for Chrome Safari and Opera */
    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }

    /* Hide scrollbar for IE Edge and Firefox */
    .no-scrollbar {
      -ms-overflow-style: none;  /* IE and Edge */
      scrollbar-width: none;  /* Firefox */
    }

    /* Dialog/Modal styles */
    .dialog-backdrop {
      position: fixed;
      inset: 0;
      z-index: 50;
      background-color: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }

    .dialog-content {
      background-color: white;
      border-radius: 0.5rem;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      display: flex;
      flex-direction: column;
      max-height: 90vh;
      overflow: hidden;
    }

    .dialog-fullscreen {
      width: 100vw;
      height: 100vh;
      max-width: none;
      max-height: none;
      border-radius: 0;
      margin: 0;
    }

    /* Enhanced document viewer styles */
    #document-viewer-container {
      position: relative;
      background: #f8fafc;
    }

    #document-viewer {
      transition: transform 0.2s ease;
      transform-origin: center center;
    }

    #document-viewer img {
      max-width: 100%;
      height: auto;
      border-radius: 0.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    #document-viewer iframe {
      border-radius: 0.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    /* Quick type buttons */
    .quick-type-btn {
      transition: all 0.2s ease;
      border: 1px solid #e5e7eb;
    }

    .quick-type-btn:hover {
      background-color: #f3f4f6;
      border-color: #d1d5db;
    }

    .quick-type-btn.active {
      background-color: var(--primary);
      color: white;
      border-color: var(--primary);
    }

    /* Success button styles */
    .btn-success {
      background-color: #10b981;
      color: white;
    }

    .btn-success:hover {
      background-color: #059669;
    }

    .btn-success:disabled {
      background-color: #d1d5db;
      color: #9ca3af;
    }

    /* Animation styles */
    .animate-fade-in {
      animation: fadeIn 0.3s ease-out;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: scale(0.95);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    /* Responsive adjustments */
    @media (max-width: 1280px) {
      .dialog-fullscreen .grid {
        grid-template-columns: 1fr;
      }
      
      .xl\:col-span-2 {
        grid-column: span 1;
      }
    }
  </style>