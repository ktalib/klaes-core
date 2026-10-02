{{-- The EDMS master folder these scans go into. The registry folder holds one
     folder per file type, and the file number folder sits inside it, so this
     decides the upload path:
       EDMS/SCAN_UPLOAD/{Registry}/{File Type}/{FILE NUMBER}/
     Optional: leave it blank and the file stays directly under the registry,
     exactly as before, until someone classifies it.

     Picked in steps — Category, then Type, then Old/New where the type has it —
     rather than one long grouped list. Regular is a complete answer on its own,
     so choosing it hides the other two dropdowns; every other category reveals
     the Type one. The third appears only for Regrant and Resettlement, the only
     types that split.

     The resolved key lands in the hidden #scan-upload-file-type input, which is
     what the rest of the page reads and posts, so nothing downstream had to
     change when this became a cascade. --}}
@php
  $edmsTypeTree = \App\Services\Edms\EdmsFileType::tree();
  $edmsCategories = \App\Services\Edms\EdmsFileType::categories();
@endphp
<div id="file-type-selection-container">
  <p class="block text-sm font-semibold text-gray-700 mb-2">File Type</p>

  {{-- The value every other script reads. Never edited by hand — the selects
       below write it through commitFileTypeSelection(). --}}
  <input type="hidden" id="scan-upload-file-type" value="">

  {{-- The catalogue, straight from the edms_file_types lookup table, so the
       dropdowns cannot drift from the folders that actually exist on disk. --}}
  <script type="application/json" id="scan-upload-file-type-tree">@json($edmsTypeTree)</script>

  <div class="space-y-2">
    {{-- One grid for all three, so they fill two per row: Category and Type
         side by side, and the variant — when the type has one — below them.
         Each cell hides on its own, which is why they are siblings here rather
         than the Type and variant sharing a wrapper. --}}
    <div class="grid gap-2 sm:grid-cols-2">
      <div>
        <label for="scan-upload-file-type-category" class="block text-xs font-medium text-gray-600 mb-1">Category</label>
        <select id="scan-upload-file-type-category" class="input w-full text-sm">
          <option value="">Not specified — keep under the registry</option>
          @foreach($edmsCategories as $category)
            <option value="{{ $category['key'] }}">{{ $category['label'] }}</option>
          @endforeach
        </select>
      </div>

      {{-- Revealed only for a category that has types under it. --}}
      <div id="scan-upload-file-type-detail" class="hidden">
        <label for="scan-upload-file-type-type" class="block text-xs font-medium text-gray-600 mb-1">Type</label>
        <select id="scan-upload-file-type-type" class="input w-full text-sm">
          <option value="">Select type…</option>
        </select>
      </div>

      {{-- Only Regrant and Resettlement reach this, and both split Old / New.
           The heading is still built from the type's own variant labels rather
           than hardcoded, so a type added to the lookup table that splits some
           other way labels itself correctly. --}}
      <div id="scan-upload-file-type-variant-wrap" class="hidden">
        <label for="scan-upload-file-type-variant" id="scan-upload-file-type-variant-label"
               class="block text-xs font-medium text-gray-600 mb-1">Variant</label>
        <select id="scan-upload-file-type-variant" class="input w-full text-sm">
          <option value="">Select…</option>
        </select>
      </div>
    </div>

    {{-- Shows the folder the current selection resolves to, so an operator can
         see where the scans are about to land before loading them. --}}
    <p id="scan-upload-file-type-hint" class="text-xs text-gray-500">
      Leave as “Not specified” to keep the file directly under its registry.
    </p>
  </div>
</div>
