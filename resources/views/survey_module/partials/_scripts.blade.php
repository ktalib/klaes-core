{{--
    Shared Survey module behaviour.

    This file used to carry the whole prototype script block ported from
    docs/prototypes/Survey-Module.html — an in-memory/sessionStorage demo that
    populated the projects table, the plot register, the stepper and the tree
    list on DOMContentLoaded.

    All of those pages are server-rendered from the database now, so that code
    was not merely redundant: this partial is pushed to the `scripts` stack and
    therefore runs AFTER each page's own inline script, so any function it
    defined silently replaced the real one of the same name. It was overriding
    addTree() on the case register and selectNewProjectType() on the project
    form, and its DOM targets (#projectsTableBody, #farmTableBody, #treeList,
    #projectSelect, #formStepper) no longer exist anywhere.

    Only the genuinely shared helpers remain. Page-specific behaviour belongs in
    that page's own section partial, under a name unique to it.

    The original demo script is preserved verbatim in
    docs/prototypes/Survey-Module.html if it is ever needed for reference.
--}}
<script>
(function () {
    'use strict';

    // Occupancy Permit workflow: expand a stage to read its detail.
    window.toggleWfDetail = function (el) {
        var detail = el && el.querySelector ? el.querySelector('.wf-detail') : null;
        if (detail) detail.classList.toggle('open');
    };
})();
</script>
