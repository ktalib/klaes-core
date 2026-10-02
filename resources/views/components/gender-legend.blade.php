{{--
    Colour key for the gender row tint on the indexed-files tables.

    Male | Female | Corporate | Joint are the only values file_indexings.gender
    holds (App\Services\GenderNormalizer) — Government bodies fold into
    Corporate. "Not recorded" is listed because most legacy rows predate the
    gender columns and are deliberately left untinted.

    Swatch colours come from .gender-swatch-* in public/css/app-layout.css, the
    same file that tints the rows, so the key can never drift from the table.
--}}
<div class="flex flex-wrap items-center gap-x-5 gap-y-2 px-4 py-2.5 border-b border-gray-100 bg-gray-50/60 text-[11px] font-semibold text-gray-600">
    <span class="inline-flex items-center gap-1.5 uppercase tracking-wider text-gray-400">
        <i data-lucide="palette" class="w-3.5 h-3.5"></i>
        Row colour by gender
    </span>
    <span class="inline-flex items-center gap-1.5"><span class="gender-swatch gender-swatch-male"></span>Male</span>
    <span class="inline-flex items-center gap-1.5"><span class="gender-swatch gender-swatch-female"></span>Female</span>
    <span class="inline-flex items-center gap-1.5"><span class="gender-swatch gender-swatch-corporate"></span>Corporate</span>
    <span class="inline-flex items-center gap-1.5"><span class="gender-swatch gender-swatch-joint"></span>Joint</span>
    <span class="inline-flex items-center gap-1.5 text-gray-400"><span class="gender-swatch gender-swatch-none"></span>Not recorded</span>
</div>
