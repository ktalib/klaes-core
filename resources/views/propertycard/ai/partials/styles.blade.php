<script>
      // Tailwind config
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              primary: "#3b82f6",
              "primary-foreground": "#ffffff",
              muted: "#f3f4f6",
              "muted-foreground": "#6b7280",
              border: "#e5e7eb",
              destructive: "#ef4444",
              "destructive-foreground": "#ffffff",
              secondary: "#f1f5f9",
              "secondary-foreground": "#0f172a",
            },
          },
        },
      };
    </script>

    <style>
      /* Loading spinner animation */
      .loading-spinner {
        width: 1rem;
        height: 1rem;
        border: 2px solid #e5e7eb;
        border-top: 2px solid #3b82f6;
        border-radius: 50%;
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

      /* File drop zone styles */
      .file-drop-zone {
        border: 2px dashed #d1d5db;
        transition: all 0.3s ease;
      }

      .file-drop-zone:hover {
        border-color: #3b82f6;
        background-color: #f8fafc;
      }

      .file-drop-zone.dragover {
        border-color: #3b82f6;
        background-color: #eff6ff;
      }

      /* Progress bar animation */
      .progress-bar {
        transition: width 0.5s ease-in-out;
      }

      /* AI stage indicator animations */
      .stage-indicator {
        transition: all 0.3s ease;
      }

      .stage-indicator.active {
        animation: pulse 2s infinite;
      }

      @keyframes pulse {
        0%,
        100% {
          opacity: 1;
        }
        50% {
          opacity: 0.5;
        }
      }

      /* Modal backdrop */
      .modal-backdrop {
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
      }

      /* Badge styles */
      .badge {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 500;
      }

      .badge-success {
        background-color: "#dcfce7";
        color: "#166534";
      }

      .badge-warning {
        background-color: "#fef3c7";
        color: "#92400e";
      }

      .badge-error {
        background-color: "#fee2e2";
        color: "#991b1b";
      }

      .badge-default {
        background-color: "#f3f4f6";
        color: "#374151";
      }

      /* Collapsible content */
      .collapsible-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
      }

      .collapsible-content.expanded {
        max-height: 2000px;
      }

      /* Instrument card styles */
      .instrument-card {
        border-left: 4px solid #3b82f6;
      }

      .instrument-card.editing {
        border-left-color: #10b981;
      }
    </style>

    