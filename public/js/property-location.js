/**
 * Full Property Location — one rule, applied everywhere.
 *
 * A property's full location does NOT carry its plot number. The plot number is
 * captured in its own field on every card that builds a location, and repeating
 * it inside the location string gave readings like
 *
 *     1540, W/B PASS, KUMBOTSO, KANO STATE
 *
 * where the leading 1540 is simply the Plot No field again.
 *
 * Two things have to happen for that to stay true:
 *
 *  1. The composers stop putting the plot in. That is a per-card edit.
 *  2. Locations that ALREADY contain a plot — the ones backfilled from a file
 *     record when a file number is picked — get it taken out on the way in.
 *     That is what stripPlot() below is for.
 *
 * Loaded globally from layouts/app.blade.php, so every card can reach it as
 * window.PropertyLocation without adding a script tag of its own.
 */
(function (global) {
    'use strict';

    // "PLOT 12", "Plot No. 12", "PLOT NO 12A", "P/N 12" — a token that says
    // outright that it is a plot number. The label plus one word must be the
    // WHOLE token: a street genuinely called "Plot 45 Road" runs to three words
    // and is left alone.
    var LABELLED = /^(?:plot|p\/n)\b[\s.:]*(?:no\b[\s.:]*)?[\w\/-]+$/i;
    var LABEL_ONLY = /^(?:plot|p\/n)\b[\s.:]*(?:no\b[\s.:]*)?/i;

    // "1540", "12A", "1540/B", "12-14" — a bare token shaped like a plot number.
    var PLOT_SHAPED = /^\d+[a-z]?(?:\s*[\/-]\s*\d*[a-z]?)?$/i;

    function norm(value) {
        return String(value == null ? '' : value).trim().toLowerCase().replace(/\s+/g, ' ');
    }

    /**
     * Remove the plot number from a composed location string.
     *
     * @param {string} location  The stored/composed location.
     * @param {string} [plotNo]  The plot number for this property when it is
     *                           known — from the file record, or the card's own
     *                           Plot No field. Supplying it makes the match
     *                           exact rather than shape-based.
     * @returns {string} The location without its plot number.
     */
    function stripPlot(location, plotNo) {
        var raw = String(location == null ? '' : location).trim();
        if (!raw) return '';

        var tokens = raw.split(',').map(function (t) { return t.trim(); }).filter(function (t) { return t !== ''; });
        if (!tokens.length) return '';

        var known = norm(plotNo);
        // A known plot may itself be stored labelled ("PLOT 12"); compare on the
        // bare value so "12" and "PLOT 12" both match.
        var knownBare = known.replace(LABEL_ONLY, '').trim();

        var kept = [];
        var droppedKnown = false;

        tokens.forEach(function (token) {
            var t = norm(token);

            // 1. Anything that labels itself a plot goes, always.
            if (LABELLED.test(token)) return;

            // 2. A token equal to the plot number we were handed goes, once.
            //    Once only: a district that happened to share the digits should
            //    not vanish as well.
            if (!droppedKnown && knownBare && (t === knownBare || t === known)) {
                droppedKnown = true;
                return;
            }

            kept.push(token);
        });

        // 3. No plot number to match against, and the string still opens with a
        //    bare plot-shaped token. In every location this system composes, the
        //    plot leads and a house number is written labelled ("NO. 12"), so a
        //    leading bare number is the plot. Only done while enough of the
        //    address survives to still be an address, so a two-part string is
        //    never reduced to a fragment.
        if (!knownBare && kept.length > 2 && PLOT_SHAPED.test(kept[0])) {
            kept.shift();
        }

        return kept.join(', ');
    }

    /**
     * Join location parts, with the plot already left out by the caller.
     * Present so cards compose the same way rather than each inventing a join.
     */
    function compose(parts) {
        return (parts || []).filter(Boolean).map(function (p) {
            return String(p).trim();
        }).filter(Boolean).join(', ');
    }

    global.PropertyLocation = { stripPlot: stripPlot, compose: compose };
})(window);
