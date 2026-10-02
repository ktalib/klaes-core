<style>
    /* Keep SweetAlerts above very high z-index modals (like register dialog at 1000020) */
    .swal2-container {
        z-index: 2000000 !important;
    }

    /* Keep File Number Selector above Instrument Capture modal (registration-dialog uses very high z-index). */
    #global-fileno-modal {
        z-index: 1100000 !important;
    }

    .select2-container--open {
        z-index: 1100010 !important;
    }

    /* Make Select2 (used on District dropdowns) match the native instrument inputs */
    .district-select2.select2-container .select2-selection--single {
        height: 42px;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background-color: #fff;
        padding-left: 1.75rem;
        /* leave room for the map-pin icon */
        display: flex;
        align-items: center;
    }

    .district-select2.select2-container .select2-selection--single .select2-selection__rendered {
        line-height: normal;
        padding-left: 0;
        color: #374151;
        font-size: 0.875rem;
    }

    .district-select2.select2-container .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }

    .district-select2.select2-container--open .select2-selection--single,
    .district-select2.select2-container--focus .select2-selection--single {
        border-color: #60a5fa;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    }

    /* Property LGA is a MULTI select (a property can straddle more than one LGA).
       Select2 renders it as tags; these mirror the single-select rules above so it lines
       up with the District field beside it. */
    .lga-multi-select2.select2-container .select2-selection--multiple {
        min-height: 42px;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background-color: #fff;
        padding-left: 1.75rem;
        /* leave room for the map icon */
        padding-right: 0.5rem;
    }

    .lga-multi-select2.select2-container .select2-selection--multiple .select2-selection__choice {
        background-color: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1d4ed8;
        border-radius: 0.375rem;
        font-size: 0.8125rem;
        margin-top: 6px;
    }

    .lga-multi-select2.select2-container .select2-search--inline .select2-search__field {
        margin-top: 8px;
        font-size: 0.875rem;
    }

    .lga-multi-select2.select2-container--open .select2-selection--multiple,
    .lga-multi-select2.select2-container--focus .select2-selection--multiple {
        border-color: #60a5fa;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    }

    /* Fallback for pages that do not load Select2 (edit/show): keep the native multi-select
       from stretching the row. */
    select[multiple]#desc_lga {
        min-height: 42px;
        max-height: 96px;
    }

    /* File Number dropdown (Select2 over the indexing table) - match the other
       instrument inputs, and give each result a number + identifying line. */
    #file_number_select + .select2-container .select2-selection--single {
        height: 42px;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        background-color: #fff;
        display: flex;
        align-items: center;
    }

    #file_number_select + .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: normal;
        color: #374151;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 0.875rem;
    }

    #file_number_select + .select2-container .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }

    .fileno-option__no {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 0.875rem;
        color: #111827;
    }

    .fileno-option__sub {
        font-size: 0.75rem;
        color: #6b7280;
        margin-top: 0.125rem;
    }

    .select2-container--default .select2-results__option--highlighted .fileno-option__no,
    .select2-container--default .select2-results__option--highlighted .fileno-option__sub {
        color: #fff;
    }

    /* The "you are about to register ..." banner on the capture card. Its colour is
       the chosen type's accent, handed in at runtime as --accent / --accent-soft
       (paintSummaryBanner), so the banner, the dialog header and the submit button
       all read as the same thing. */
    .capture-banner {
        --accent: #4b5563;
        --accent-soft: #f9fafb;
        background: var(--accent-soft);
        border: 1px solid var(--accent);
        /* Softened where color-mix is available; the solid accent above is the
           fallback everywhere else. The left edge stays full strength either way. */
        border-color: color-mix(in srgb, var(--accent) 35%, #ffffff);
        border-left: 3px solid var(--accent);
    }

    .capture-banner__lead {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--accent);
    }

    .capture-banner__line {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.25rem;
        font-size: 0.875rem;
        color: #1f2937;
    }

    .capture-banner__icon {
        display: inline-flex;
        flex-shrink: 0;
        color: var(--accent);
    }

    .capture-banner__meta {
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: #6b7280;
    }

    /* The volume line: it is the number the operator checks before committing, so
       it is sized to be read at a glance - matching the notice popup. */
    .capture-banner__meta--volume {
        margin-top: 0.5rem;
        font-size: 1.125rem;
        font-weight: 700;
        color: #374151;
    }

    .capture-banner__volume {
        font-size: 1.375rem;
        font-weight: 700;
        color: var(--accent);
    }

    .capture-banner__change {
        flex-shrink: 0;
        font-size: 0.75rem;
        font-weight: 600;
        text-decoration: underline;
        color: var(--accent);
    }

    .capture-banner__change:hover {
        filter: brightness(0.85);
    }

    /* "Fill a missing registration number". Deliberately not a capture-banner:
       the banners state what WILL happen, this one asks the officer to override
       it, so it reads as a caution (amber) and never as a status line. */
    .backfill-panel {
        background: #fffbeb;
        border: 1px solid #fcd34d;
        border-left: 4px solid #f59e0b;
        border-radius: 0.5rem;
        padding: 0.75rem 1rem;
    }

    .backfill-panel__toggle {
        display: flex;
        align-items: flex-start;
        gap: 0.625rem;
        cursor: pointer;
    }

    .backfill-panel__title {
        display: block;
        font-size: 0.8125rem;
        font-weight: 700;
        color: #92400e;
    }

    .backfill-panel__hint {
        display: block;
        margin-top: 0.125rem;
        font-size: 0.6875rem;
        color: #a16207;
    }

    .backfill-panel__range {
        margin-top: 0.5rem;
        padding-left: 1.625rem;
        font-size: 0.6875rem;
        color: #a16207;
    }

    .backfill-panel__fields {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
        gap: 0.75rem;
        margin-top: 0.75rem;
        padding-left: 1.625rem;
    }

    .backfill-panel__label {
        display: block;
        margin-bottom: 0.25rem;
        font-size: 0.6875rem;
        font-weight: 600;
        color: #92400e;
    }

    .backfill-panel__chosen {
        margin-top: 0.5rem;
        padding-left: 1.625rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #92400e;
    }

    /* What the survey found. Sits under the checkbox where there is one, and
       alone in the box where there is nothing to fill. */
    .backfill-panel__note {
        margin-top: 0.5rem;
        padding-left: 1.625rem;
        font-size: 0.6875rem;
        line-height: 1.5;
        color: #a16207;
    }

    .backfill-panel__note:empty {
        display: none;
    }

    /* A register with nothing missing. Same box, no question in it: amber and a
       checkbox would ask the officer to choose from an empty list. */
    .backfill-panel--quiet {
        background: #f9fafb;
        border-color: #e5e7eb;
        border-left-color: #9ca3af;
    }

    .backfill-panel--quiet .backfill-panel__note {
        margin-top: 0;
        padding-left: 0;
        color: #4b5563;
    }

    /* The panel reduced to its reminder line, for the instruments whose picker
       lives in the "You are about to register" popup: there is no checkbox above
       it any more, so the indent that lined it up under one has to go. */
    .backfill-panel__chosen--solo {
        margin-top: 0;
        padding-left: 0;
    }

    /* The same panel inside a SweetAlert. Two selects side by side do not fit a
       440px popup, so they stack; everything else is inherited, which is the
       point - the officer sees the same control in both places. */
    .backfill-panel--notice {
        margin-top: 14px;
        text-align: left;
    }

    .backfill-panel--notice .backfill-panel__fields {
        grid-template-columns: minmax(0, 1fr);
        gap: 0.5rem;
    }

    /* Shared styling for the "what are we capturing?" prompts (CofO variant + type,
       OP type) - a row of choice cards, optionally with a follow-up select. */
    .type-prompt {
        text-align: left;
        margin-top: 0.5rem;
    }

    .type-prompt__label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 0.5rem;
        display: block;
    }

    /* A question, not a field label - reads as a sentence. */
    .type-prompt__question {
        font-size: 0.875rem;
        color: #4b5563;
        margin-bottom: 0.75rem;
    }

    .type-prompt__cards {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.5rem;
    }

    .type-prompt__cards--two {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    /* Cards that confirm on click carry an arrow affordance instead of a radio. */
    .type-prompt__card--action .type-prompt__card-title::after {
        content: ' \2192';
        opacity: 0.55;
    }

    /* Each choice carries its own accent, handed in as --accent / --accent-soft so
       one rule set serves every prompt. */
    .type-prompt__card {
        --accent: #4b5563;
        --accent-soft: #f9fafb;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        padding: 0.75rem;
        text-align: left;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        border-top: 3px solid var(--accent);
        background: #fff;
        cursor: pointer;
        transition: border-color .15s, background-color .15s, box-shadow .15s;
    }

    .type-prompt__card:hover {
        border-color: var(--accent);
        border-top-color: var(--accent);
        background: var(--accent-soft);
    }

    .type-prompt__card.is-selected {
        border-color: var(--accent);
        background: var(--accent-soft);
        box-shadow: 0 0 0 1px var(--accent) inset;
    }

    /* A mouse click leaves no ring; tabbing does. */
    .type-prompt__card:focus { outline: none; }

    .type-prompt__card:focus-visible {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }

    .type-prompt__card-title {
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--accent);
    }

    .type-prompt__card-blurb {
        font-size: 0.6875rem;
        line-height: 1.3;
        color: #6b7280;
    }

    .type-prompt__type {
        margin-top: 1rem;
    }

    .type-prompt__type.is-hidden {
        display: none;
    }

    .type-prompt__select {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        color: #374151;
        background: #fff;
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

    .btn-ghost {
        background-color: transparent;
        color: #374151;
    }

    .btn-ghost:hover {
        background-color: var(--muted);
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
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

    .input:read-only {
        background-color: var(--muted);
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
        min-height: 80px;
        resize: vertical;
    }

    .textarea:focus {
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
    }

    .dialog-content {
        background-color: white;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        width: 100%;
        max-width: 1280px;
        max-height: 90vh;
        overflow-y: auto;
        margin: 1rem;
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

    /* Label styles */
    .label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
    }

    /* Animation classes */
    .animate-fade-in {
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    /* Utility classes */
    .hidden {
        display: none !important;
    }

    .text-center {
        text-align: center;
    }

    .text-right {
        text-align: right;
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

    .text-sm {
        font-size: 0.875rem;
    }

    .text-xs {
        font-size: 0.75rem;
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

    .mb-1 {
        margin-bottom: 0.25rem;
    }

    .mb-2 {
        margin-bottom: 0.5rem;
    }

    .mb-3 {
        margin-bottom: 0.75rem;
    }

    .mb-4 {
        margin-bottom: 1rem;
    }

    .mb-6 {
        margin-bottom: 1.5rem;
    }

    .mt-1 {
        margin-top: 0.25rem;
    }

    .mt-2 {
        margin-top: 0.5rem;
    }

    .mt-4 {
        margin-top: 1rem;
    }

    .mr-2 {
        margin-right: 0.5rem;
    }

    .ml-2 {
        margin-left: 0.5rem;
    }

    .p-2 {
        padding: 0.5rem;
    }

    .p-4 {
        padding: 1rem;
    }

    .p-6 {
        padding: 1.5rem;
    }

    .px-3 {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }

    .py-2 {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }

    .py-4 {
        padding-top: 1rem;
        padding-bottom: 1rem;
    }

    .pb-3 {
        padding-bottom: 0.75rem;
    }

    .pt-2 {
        padding-top: 0.5rem;
    }

    .pt-4 {
        padding-top: 1rem;
    }

    .gap-2 {
        gap: 0.5rem;
    }

    .gap-3 {
        gap: 0.75rem;
    }

    .gap-4 {
        gap: 1rem;
    }

    .gap-6 {
        gap: 1.5rem;
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

    .items-start {
        align-items: flex-start;
    }

    .justify-center {
        justify-content: center;
    }

    .justify-between {
        justify-content: space-between;
    }

    .justify-end {
        justify-content: flex-end;
    }

    .space-y-2>*+* {
        margin-top: 0.5rem;
    }

    .space-y-3>*+* {
        margin-top: 0.75rem;
    }

    .space-y-4>*+* {
        margin-top: 1rem;
    }

    .space-y-6>*+* {
        margin-top: 1.5rem;
    }

    .space-x-2>*+* {
        margin-left: 0.5rem;
    }

    .space-x-6>*+* {
        margin-left: 1.5rem;
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

    .grid-cols-4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .w-full {
        width: 100%;
    }

    .h-4 {
        height: 1rem;
    }

    .h-5 {
        height: 1.25rem;
    }

    .h-8 {
        height: 2rem;
    }

    .h-10 {
        height: 2.5rem;
    }

    .h-16 {
        height: 4rem;
    }

    .h-24 {
        height: 6rem;
    }

    .w-4 {
        width: 1rem;
    }

    .w-5 {
        width: 1.25rem;
    }

    .w-8 {
        width: 2rem;
    }

    .w-10 {
        width: 2.5rem;
    }

    .w-12 {
        width: 3rem;
    }

    .w-16 {
        width: 4rem;
    }

    .min-h-screen {
        min-height: 100vh;
    }

    .max-w-xs {
        max-width: 20rem;
    }

    .max-h-48 {
        max-height: 12rem;
    }

    .overflow-y-auto {
        overflow-y: auto;
    }

    .relative {
        position: relative;
    }

    .absolute {
        position: absolute;
    }

    .top-0 {
        top: 0;
    }

    .left-0 {
        left: 0;
    }

    .right-0 {
        right: 0;
    }

    .bottom-0 {
        bottom: 0;
    }

    .border {
        border-width: 1px;
    }

    .border-b {
        border-bottom-width: 1px;
    }

    .border-t {
        border-top-width: 1px;
    }

    .border-gray-200 {
        border-color: #e5e7eb;
    }

    .border-gray-300 {
        border-color: #d1d5db;
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

    .bg-white {
        background-color: white;
    }

    .bg-gray-50 {
        background-color: #f9fafb;
    }

    .bg-gray-100 {
        background-color: #f3f4f6;
    }

    .bg-blue-50 {
        background-color: #eff6ff;
    }

    .bg-blue-100 {
        background-color: #dbeafe;
    }

    .bg-green-50 {
        background-color: #f0fdf4;
    }

    .bg-green-100 {
        background-color: #dcfce7;
    }

    .bg-purple-50 {
        background-color: #faf5ff;
    }

    .bg-purple-100 {
        background-color: #e9d5ff;
    }

    .bg-red-50 {
        background-color: #fef2f2;
    }

    .bg-red-100 {
        background-color: #fee2e2;
    }

    .bg-yellow-50 {
        background-color: #fefce8;
    }

    .bg-yellow-100 {
        background-color: #fef3c7;
    }

    .bg-indigo-50 {
        background-color: #eef2ff;
    }

    .bg-indigo-100 {
        background-color: #e0e7ff;
    }

    .bg-pink-50 {
        background-color: #fdf2f8;
    }

    .bg-pink-100 {
        background-color: #fce7f3;
    }

    .bg-teal-50 {
        background-color: #f0fdfa;
    }

    .bg-teal-100 {
        background-color: #ccfbf1;
    }

    .bg-orange-50 {
        background-color: #fff7ed;
    }

    .bg-orange-100 {
        background-color: #fed7aa;
    }

    .bg-cyan-50 {
        background-color: #ecfeff;
    }

    .bg-cyan-100 {
        background-color: #cffafe;
    }

    .bg-lime-50 {
        background-color: #f7fee7;
    }

    .bg-lime-100 {
        background-color: #ecfccb;
    }

    .bg-violet-50 {
        background-color: #f5f3ff;
    }

    .bg-violet-100 {
        background-color: #ede9fe;
    }

    .bg-emerald-50 {
        background-color: #ecfdf5;
    }

    .bg-emerald-100 {
        background-color: #d1fae5;
    }

    .bg-rose-50 {
        background-color: #fff1f2;
    }

    .bg-rose-100 {
        background-color: #ffe4e6;
    }

    .bg-sky-50 {
        background-color: #f0f9ff;
    }

    .bg-sky-100 {
        background-color: #e0f2fe;
    }

    .bg-amber-50 {
        background-color: #fffbeb;
    }

    .bg-amber-100 {
        background-color: #fef3c7;
    }

    .bg-slate-50 {
        background-color: #f8fafc;
    }

    .bg-slate-100 {
        background-color: #f1f5f9;
    }

    .bg-muted {
        background-color: var(--muted);
    }

    .text-gray-400 {
        color: #9ca3af;
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

    .text-gray-800 {
        color: #1f2937;
    }

    .text-gray-900 {
        color: #111827;
    }

    .text-blue-600 {
        color: #2563eb;
    }

    .text-blue-800 {
        color: #1e40af;
    }

    .text-green-600 {
        color: #16a34a;
    }

    .text-green-800 {
        color: #166534;
    }

    .text-purple-600 {
        color: #9333ea;
    }

    .text-purple-800 {
        color: #6b21a8;
    }

    .text-red-600 {
        color: #dc2626;
    }

    .text-red-800 {
        color: #991b1b;
    }

    .text-yellow-600 {
        color: #ca8a04;
    }

    .text-yellow-700 {
        color: #a16207;
    }

    .text-yellow-800 {
        color: #854d0e;
    }

    .text-indigo-600 {
        color: #4f46e5;
    }

    .text-indigo-800 {
        color: #3730a3;
    }

    .text-pink-600 {
        color: #db2777;
    }

    .text-pink-800 {
        color: #9d174d;
    }

    .text-teal-600 {
        color: #0d9488;
    }

    .text-teal-800 {
        color: #115e59;
    }

    .text-orange-600 {
        color: #ea580c;
    }

    .text-orange-800 {
        color: #9a3412;
    }

    .text-cyan-600 {
        color: #0891b2;
    }

    .text-cyan-800 {
        color: #155e75;
    }

    .text-lime-600 {
        color: #65a30d;
    }

    .text-lime-700 {
        color: #4d7c0f;
    }

    .text-lime-800 {
        color: #365314;
    }

    .text-violet-600 {
        color: #7c3aed;
    }

    .text-violet-800 {
        color: #5b21b6;
    }

    .text-emerald-600 {
        color: #059669;
    }

    .text-emerald-800 {
        color: #065f46;
    }

    .text-rose-600 {
        color: #e11d48;
    }

    .text-rose-800 {
        color: #9f1239;
    }

    .text-sky-600 {
        color: #0284c7;
    }

    .text-sky-800 {
        color: #075985;
    }

    .text-amber-600 {
        color: #d97706;
    }

    .text-amber-700 {
        color: #b45309;
    }

    .text-amber-800 {
        color: #92400e;
    }

    .text-slate-600 {
        color: #475569;
    }

    .text-slate-800 {
        color: #1e293b;
    }

    .text-muted-foreground {
        color: var(--muted-foreground);
    }

    .cursor-pointer {
        cursor: pointer;
    }

    .cursor-help {
        cursor: help;
    }

    .select-none {
        user-select: none;
    }

    /* Date picker styles */
    .date-picker {
        position: relative;
    }

    .date-picker-input {
        cursor: pointer;
    }

    .date-picker-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid var(--border);
        border-radius: 0.375rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        z-index: 10;
        padding: 1rem;
        margin-top: 0.25rem;
    }

    /* Responsive design */
    @media (min-width: 768px) {
        .md\:grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .md\:grid-cols-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .md\:grid-cols-4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }
</style>