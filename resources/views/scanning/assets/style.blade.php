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

    .btn-destructive {
      background-color: #ef4444;
      color: white;
    }

    .btn-destructive:hover {
      background-color: #dc2626;
    }

    .btn-sm {
      padding: 0.25rem 0.5rem;
      font-size: 0.75rem;
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

    /* Dialog styles */
    .dialog-backdrop {
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
    
    /* Make sure hidden class actually hides elements */
    .hidden {
      display: none !important;
      visibility: hidden !important;
      opacity: 0 !important;
    }

    .dialog-content {
      background-color: white;
      border-radius: 0.5rem;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      width: 100%;
      max-width: 32rem;
      max-height: 90vh;
      overflow-y: auto;
    }

    .dialog-preview {
      max-width: 900px;
      height: 800px;
      display: flex;
      flex-direction: column;
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

    .tab-content {
      display: none;
      padding-top: 1.5rem;
    }

    .tab-content[aria-hidden="false"] {
      display: block;
    }

    /* Radio group */
    .radio-group {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.5rem;
    }

    .radio-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .radio-item input[type="radio"] {
      width: 1rem;
      height: 1rem;
    }

    /* Custom animations */
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .animate-fade-in {
      animation: fadeIn 0.3s ease-in-out;
    }

    /* Hide scrollbar for Chrome, Safari and Opera */
    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }

    /* Hide scrollbar for IE, Edge and Firefox */
    .no-scrollbar {
      -ms-overflow-style: none;  /* IE and Edge */
      scrollbar-width: none;  /* Firefox */
    }
  </style>
