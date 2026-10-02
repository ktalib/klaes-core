{{-- Searchable District / Street Name dropdowns (Select2 + reference API).

     Include once per page, after components.global-fileno-modal (which loads
     Select2). Any <select data-reference-source="districts|streets"> is then
     enhanced automatically; see public/js/reference-select.js. --}}
<style>
    /* Match the Tailwind text inputs these selects sit alongside
       (w-full p-2 border border-gray-300 rounded-md). */
    .reference-select-wrap .select2-container--default .select2-selection--single {
        height: 42px;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        padding: 6px 8px;
        font-size: 0.875rem;
        background: #fff;
    }
    .reference-select-wrap .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px;
        color: #111827;
        padding-left: 2px;
        text-transform: uppercase;
    }
    .reference-select-wrap .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #9ca3af;
        text-transform: none;
    }
    .reference-select-wrap .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }
    /* Disabled (read-only backfilled) state mirrors the greyed-out inputs */
    .reference-select-wrap .select2-container--default.select2-container--disabled .select2-selection--single {
        background: #f3f4f6;
        cursor: not-allowed;
    }
    .reference-select-wrap .select2-container--default.select2-container--disabled .select2-selection--single .select2-selection__rendered {
        color: #6b7280;
    }
    .select2-dropdown { border: 1px solid #d1d5db; border-radius: 0.375rem; }
    .select2-results__option { font-size: 0.8rem; padding: 8px 12px; }
    .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
        background-color: #2563eb;
    }
    .select2-container { z-index: 10050; }
</style>
<script src="{{ asset('js/reference-select.js') }}"></script>
