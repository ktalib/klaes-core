/* ===========================================================================
   Why is File Commissioning not first?

   Paste into the browser console ON the Legal Search page, with the file already
   searched and the Timeline visible. Read-only: prints, changes nothing.

   The Timeline sorts weighted rows by weight DESC (Phase 1), then injects
   weightless "floaters" by date. A row whose recordPriorityWeight() is null is a
   floater and lands by DATE — which is exactly what File Commissioning appears to
   be doing (1975, 1975, 1982, 1983 …).

   The WEIGHT column on screen and recordPriorityWeight() are not guaranteed to be
   the same number. This prints both, side by side.
   =========================================================================== */
(function () {
  const rows = window._preferredRelatedTransactions
            || window._allRelatedTransactions
            || [];

  if (!rows.length) {
    console.warn('No timeline rows in scope. Run a search first, then re-run this.');
    return;
  }

  const fn = (name) => (typeof window[name] === 'function' ? window[name] : null);

  // These live in the page's module scope, so they may not be on window. Report
  // rather than fail silently — a missing function is itself the answer.
  const weightOf   = fn('recordPriorityWeight');
  const classifyOf = fn('classifyLifecycleEventType');
  const tsOf       = fn('getTransactionTimestamp');

  console.log('%cTimeline order probe', 'font-weight:bold;font-size:13px');
  console.log('rows in scope:', rows.length);
  console.log('recordPriorityWeight reachable   :', !!weightOf);
  console.log('classifyLifecycleEventType       :', !!classifyOf);
  console.log('getTransactionTimestamp          :', !!tsOf);

  if (!weightOf) {
    console.warn(
      'recordPriorityWeight is not exposed on window, so the weight cannot be read '
      + 'from here. Send me this message and I will expose it temporarily.'
    );
  }

  const table = rows.map((r, i) => {
    const type = r.transaction_type || r.instrument_type || '-';
    const w    = weightOf ? weightOf(r) : '(n/a)';
    const ts   = tsOf ? tsOf(r) : null;
    return {
      '#': i + 1,
      instrument: String(type).slice(0, 34),
      source: r.source_table || '-',
      // THE key column: null here means the row is treated as a weightless
      // floater and is positioned by date, not by rank.
      computedWeight: w === null ? 'NULL  <-- floater' : w,
      shownWeight: r.timeline_weight ?? r.weight ?? '-',
      event: classifyOf ? classifyOf(r) : '(n/a)',
      date: ts ? new Date(ts).toISOString().slice(0, 10) : '(undated)',
      reg_date: r.reg_date || '-',
      txn_date: r.transaction_date || '-',
    };
  });

  console.table(table);

  const comm = table.filter((t) => /commission/i.test(t.instrument));
  if (comm.length) {
    console.log('%cFile Commissioning row(s):', 'font-weight:bold');
    comm.forEach((c) => {
      console.log(
        `  computedWeight=${c.computedWeight}   shownWeight=${c.shownWeight}   event="${c.event}"   date=${c.date}`
      );
    });
    console.log(
      'If computedWeight is NULL the row is a floater and is placed by DATE — that is the bug.\n'
      + 'If it is 12, the weight sort is being overridden somewhere after Phase 1.'
    );
  } else {
    console.warn('No File Commissioning row found in the row set.');
  }
})();
