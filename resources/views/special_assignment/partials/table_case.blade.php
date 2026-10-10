{{--
    One letter case for every SPAS table.

    Values arrive in every case: names and land uses in capitals, LGAs in title
    case from the LGA list, statuses as lower-case codes ("in_progress"), bill
    types as typed. The tables show them all in CAPITALS, the way the register
    is written, and a code reads as words ("IN PROGRESS").

    Display only: nothing stored is changed. File numbers keep their exact
    characters (capitals do not alter them). Cells holding a button or menu keep
    their own case, so action menus read as before.

    Usage: give the <table> the class "spas-table" and include this once.
--}}
<style>
    table.spas-table tbody td { text-transform: uppercase; }
    table.spas-table tbody td.spas-keep-case,
    table.spas-table tbody td.spas-keep-case * { text-transform: none; }
</style>
<script>
(function () {
    // A whole value that is a code: lower-case words joined by underscores.
    const CODE = /^[a-z]+(?:_[a-z]+)+$/;

    function normalise(table) {
        table.querySelectorAll('tbody td').forEach(td => {
            if (td.dataset.spasCase) return;
            td.dataset.spasCase = '1';

            if (td.querySelector('button, select, input, textarea, .action-dropdown, [role="menu"]')) {
                td.classList.add('spas-keep-case');
                return;
            }

            const walker = document.createTreeWalker(td, NodeFilter.SHOW_TEXT);
            for (let node = walker.nextNode(); node; node = walker.nextNode()) {
                const text = node.nodeValue.trim();
                if (CODE.test(text)) node.nodeValue = node.nodeValue.replace(text, text.replace(/_/g, ' '));
            }
        });
    }

    function watch() {
        document.querySelectorAll('table.spas-table').forEach(table => {
            if (table.dataset.spasWatched) return;
            table.dataset.spasWatched = '1';
            normalise(table);
            // DataTables redraws the body on every page, search and sort.
            new MutationObserver(() => normalise(table)).observe(table, { childList: true, subtree: true });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', watch);
    else watch();
    // Tables built after load (tabs, modals) are picked up on the next draw.
    setTimeout(watch, 1500);
})();
</script>
