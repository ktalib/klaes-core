<style>
  .stat-card {
    background-color: white;
    border-radius: 0.375rem;
    padding: 1.25rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
  }

  .tab {
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: all 0.2s;
    border-bottom: 2px solid transparent;
  }

  .tab:hover {
    color: #4b5563;
  }

  .tab.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
  }

  .service-card {
    background-color: white;
    border-radius: 0.375rem;
    padding: 1.5rem;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
  }

  .badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    border: 1px solid transparent;
    transition: all 0.2s ease-in-out;
  }

  .badge-primary {
    background-color: #f3f4f6;
    color: #4b5563;
    border-color: #d1d5db;
  }

  .badge-progress {
    background-color: #dbeafe;
    color: #2563eb;
    border-color: #93c5fd;
  }

  .badge-approved {
    background-color: #d1fae5;
    color: #059669;
    border-color: #86efac;
  }

  .badge-pending {
    background-color: #fef3c7;
    color: #d97706;
    border-color: #fcd34d;
  }

  .badge-declined {
    background-color: #fee2e2;
    color: #dc2626;
    border-color: #fca5a5;
  }

  /* Land Use Badge Colors */
  .badge-residential {
    background-color: #dbeafe;
    color: #2563eb;
    border-color: #93c5fd;
  }

  .badge-commercial {
    background-color: #d1fae5;
    color: #059669;
    border-color: #86efac;
  }

  .badge-industrial {
    background-color: #fee2e2;
    color: #dc2626;
    border-color: #fca5a5;
  }

  /* Important Value Badges */
  .badge-high-priority {
    background-color: #fef3c7;
    color: #d97706;
    border-color: #fcd34d;
  }

  .badge-units {
    background-color: #e0e7ff;
    color: #4338ca;
    border-color: #c7d2fe;
  }

  .badge-units-full {
    background-color: #fee2e2;
    color: #dc2626;
    border-color: #fca5a5;
  }

  .badge-new {
    background-color: #ecfdf5;
    color: #16a34a;
    border-color: #bbf7d0;
  }

  /* Badge hover effects */
  .badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  .progress-bar {
    height: 8px;
    border-radius: 4px;
    background-color: #e5e7eb;
    overflow: hidden;
  }

  .progress-bar-fill {
    height: 100%;
    border-radius: 4px;
  }

  .progress-bar-blue {
    background-color: #3b82f6;
  }

  .progress-bar-orange {
    background-color: #f59e0b;
  }

  .progress-bar-red {
    background-color: #ef4444;
  }

  .table-header {
    background-color: #f9fafb;
    font-weight: 500;
    color: #4b5563;
    text-align: left;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
  }

  .table-cell {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e5e7eb;
  }
</style>