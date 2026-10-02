{{--
    The planning clearance, on a parcel-update action menu.

    This is what replaced the KAMMA/Physical Planning Handshake — a four-field
    modal (land value, fee, status, remarks) that stood in for a site inspection
    with nowhere to live, and whose 'Approved' silently approved the whole
    application. The inspection now exists: the Master JSI, captured and approved by
    Physical Planning on their own register, and read back here by MasterJsiGate.

    One partial for all five single workflows (Subdivision, Separation, Merger,
    Extension, Change of Purpose) because the item and its preconditions are
    identical in each; only the record it hands over differs.

    Send JSI to Deeds is deliberately NOT here. The handover is Physical Planning
    acting on their own inspection, so it belongs on the Master JSI register beside
    Generate / Submit / Approve — not on a Deeds or Land officer's parcel-update
    menu, where it sat greyed out until someone in another department approved the
    sheet. One action, one place.

    Expects:
        $record       the parcel-update row
        $subjectType  its MasterJsiReport::SUBJECTS slug
        $jsiReport    the report for this row, or null — resolved once per page by
                      MasterJsiGate::reportMap(), never per row
--}}
@if ($jsiReport)
    {{-- Reading the inspection is part of working the parcel update, so the link
         stays; the status is on it because it is what the gate turns on. --}}
    <a href="{{ route('master-jsi.show', $jsiReport->id) }}"
        class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
        <i data-lucide="ruler" class="w-4 h-4 text-red-500"></i>
        Master JSI ({{ strtoupper($jsiReport->status) }})
    </a>
@else
    <a href="{{ route('master-jsi.create', [
            'subject_type' => $subjectType,
            'subject_id'   => $record->id,
            'category'     => 'SPU',
            'type'         => $subjectType,
            'return'       => request()->fullUrl(),
        ]) }}"
        class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
        <i data-lucide="ruler" class="w-4 h-4 text-red-500"></i>
        Capture Master JSI
    </a>
@endif
