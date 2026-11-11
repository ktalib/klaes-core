<!-- JsBarcode Library -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<style>
   

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
        border: none;
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
        color: #374151;
    }

    .btn-outline:hover {
        background-color: var(--muted);
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        height: 2rem;
        width: 2rem;
    }

    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
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

    .badge-default {
        background-color: var(--primary);
        color: var(--primary-foreground);
    }

    .badge-outline {
        background-color: transparent;
        border: 1px solid var(--border);
        color: #374151;
    }

    .badge-secondary {
        background-color: var(--secondary);
        color: var(--secondary-foreground);
    }

    .badge-success {
        background-color: var(--success);
        color: white;
    }

    .badge-green {
        background-color: #22c55e;
        color: white;
    }

    /* Checkbox styles */
    .checkbox {
        width: 1rem;
        height: 1rem;
        border: 1px solid var(--border);
        border-radius: 0.25rem;
        background-color: white;
        cursor: pointer;
    }

    .checkbox:checked {
        background-color: var(--primary);
        border-color: var(--primary);
    }

    /* Radio styles */
    .radio {
        width: 1rem;
        height: 1rem;
        border: 1px solid var(--border);
        border-radius: 50%;
        background-color: white;
        cursor: pointer;
    }

    .radio:checked {
        background-color: var(--primary);
        border-color: var(--primary);
    }

    /* Tab styles */
    .tabs-list {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        background-color: var(--muted);
        border-radius: 0.375rem;
        padding: 0.25rem;
    }

    .tab-trigger {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        border-radius: 0.25rem;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        background-color: transparent;
        color: var(--muted-foreground);
        border: none;
    }

    .tab-trigger.active {
        background-color: white;
        color: var(--primary);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    /* Tooltip styles */
    .tooltip {
        position: relative;
        display: inline-block;
    }

    .tooltip-content {
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background-color: #1f2937;
        color: white;
        padding: 0.5rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s;
        margin-bottom: 0.25rem;
        z-index: 50;
    }

    .tooltip:hover .tooltip-content {
        opacity: 1;
        visibility: visible;
    }

    /* Format selection styles */
    .format-option {
        border: 1px solid var(--border);
        border-radius: 0.375rem;
        padding: 0.75rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .format-option.selected {
        border-color: var(--primary);
        background-color: rgba(59, 130, 246, 0.05);
    }

    .format-option:hover {
        background-color: var(--muted);
    }

</style>