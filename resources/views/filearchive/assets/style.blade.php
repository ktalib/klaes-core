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

  /* Clickable status legend (filter toggles) */
  .status-legend {
    cursor: pointer;
    border: 1px solid transparent;
    text-decoration: none;
    color: inherit;
  }
  .status-legend:hover {
    text-decoration: none;
  }
  .status-legend[aria-pressed="true"] {
    background-color: #eef2ff;
    border-color: #c7d2fe;
    color: #1f2937;
    font-weight: 600;
  }
  .status-legend[aria-pressed="true"] .status-legend-count {
    color: #4f46e5;
  }

  /* File card hover tooltip */
  .file-card-status-header {
    position: relative;
  }
  .file-card-status-header .status-tooltip {
    visibility: hidden;
    opacity: 0;
    position: absolute;
    z-index: 50;
    /* Drop the tooltip DOWN over the cover so it is always visible (never clipped
       above the top row) and clearly attached to the file the user is hovering. */
    top: calc(100% + 8px);
    bottom: auto;
    /* The card is overflow-hidden, so the tooltip can never extend past its
       edges — span the card's own width and wrap content instead. */
    left: 0;
    right: 0;
    transform: none;
    background-color: #1f2937;
    color: #fff;
    font-size: 12px;
    line-height: 1.4;
    padding: 8px 12px;
    border-radius: 6px;
    white-space: normal;
    width: auto;
    max-width: none;
    box-shadow: 0 4px 16px rgba(0,0,0,0.18);
    transition: opacity 0.18s ease, visibility 0.18s ease;
    pointer-events: none;
    text-align: left;
  }
  /* Label/value rows inside the tooltip (Registry, Department, holder, ...) */
  .file-card-status-header .status-tooltip .tt-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 10px;
    padding: 5px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.14);
  }
  .file-card-status-header .status-tooltip .tt-label {
    color: #ffffff;
    font-size: 11px;
    flex-shrink: 0;
  }
  .file-card-status-header .status-tooltip .tt-value {
    color: #ffffff;
    font-weight: 700;
    font-size: 11px;
    text-align: right;
    min-width: 0;
    overflow-wrap: break-word;
  }
  .file-card-status-header .status-tooltip::after {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-bottom-color: #1f2937;
  }
  .file-card-status-header .status-tooltip.status-tooltip--in-registry {
    background-color: #4ade80;
    box-shadow: 0 4px 16px rgba(74, 222, 128, 0.22);
  }
  .file-card-status-header .status-tooltip.status-tooltip--in-registry::after {
    border-bottom-color: #4ade80;
  }
  .file-card-status-header .status-tooltip.status-tooltip--out {
    background-color: #fb923c;
    box-shadow: 0 4px 16px rgba(251, 146, 60, 0.22);
  }
  .file-card-status-header .status-tooltip.status-tooltip--out::after {
    border-bottom-color: #fb923c;
  }
  /* Keep every line of tooltip text white regardless of inline utility classes. */
  .file-card-status-header .status-tooltip,
  .file-card-status-header .status-tooltip * {
    color: #ffffff !important;
  }
  /* Reveal the location tooltip when hovering anywhere on the file card, not just
     the thin status bar — users naturally hover the cover/body of the file. */
  .file-card:hover .status-tooltip,
  .file-card-status-header:hover .status-tooltip {
    visibility: visible;
    opacity: 1;
  }
  .file-card {
    position: relative;
  }
  .file-card .file-movement-info {
    display: none;
    position: absolute;
    z-index: 40;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-top: none;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    padding: 10px 12px;
    font-size: 11px;
    line-height: 1.5;
    color: #374151;
    max-height: 220px;
    overflow-y: auto;
  }
  .file-card:hover .file-movement-info {
    display: block;
  }
  .file-movement-info .mv-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 3px 0;
    border-bottom: 1px dashed #f3f4f6;
  }
  .file-movement-info .mv-row:last-child {
    border-bottom: none;
  }
  .file-movement-info .mv-date {
    color: #6b7280;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
  }
  .file-movement-info .mv-location {
    color: #111827;
    font-weight: 500;
    text-align: right;
  }
  .file-movement-info .mv-empty {
    color: #9ca3af;
    font-style: italic;
    text-align: center;
    padding: 6px 0;
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

  .badge-default {
    background-color: var(--primary);
    color: var(--primary-foreground);
  }

  .badge-destructive {
    background-color: #ef4444;
    color: white;
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

  /* Select styles */
  .select {
    display: block;
    width: 100%;
    border-radius: 0.375rem;
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    background-color: white;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
  }

  .select:focus {
    outline: none;
    border-color: var(--ring);
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
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
    padding: 1rem;
  }

  .dialog-content {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
    margin: auto;
    position: relative;
  }

  /* Responsive modal adjustments */
  @media (max-width: 768px) {
    .dialog-backdrop {
      padding: 0.5rem;
    }
    
    .dialog-content {
      max-width: 100%;
      max-height: 95vh;
      margin: 0;
    }
  }

  @media (max-width: 640px) {
    .dialog-backdrop {
      padding: 0.25rem;
      align-items: flex-start;
      padding-top: 2rem;
    }
    
    .dialog-content {
      max-height: calc(100vh - 2rem);
      width: 100%;
    }
  }

  /* Mark style for search highlighting */
  mark {
    background-color: #fef08a;
    padding: 0.1em 0.2em;
    border-radius: 0.2em;
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