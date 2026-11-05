{{--
    In-Unit label on the Send form (user, 2026-11-04). Not clickable: In-Unit is chosen on the
    card a row's Send button opens. Hidden on a normal Department send.
    $state: the form's state, e.g. 'send'.
--}}
<div x-show="{{ $state }}.in_unit" class="flex flex-wrap items-center gap-2">
    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg text-white" style="background:#7e22ce">
        <i data-lucide="git-branch" class="w-3.5 h-3.5"></i> In-Unit
    </span>
</div>
