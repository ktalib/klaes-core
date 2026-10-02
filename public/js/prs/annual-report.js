/**
 * PRS Annual Progress Report — chart layer.
 *
 * Written against Chart.js 3.9.1, which layouts/app.blade.php already loads
 * globally. Do NOT load a second Chart.js here — the global `Chart` symbol
 * would clash. (The stack doc recommends consolidating the app on 4.4.0; that
 * is a coordinated upgrade, not a drop-in for this page.)
 *
 * Chart rules applied throughout, per docs/prs-2025/10-data-quality-audit.md:
 *   - Months only on the category axis. The annual total is a hero number beside
 *     the chart, never a bar and never a stacked series. The source charts did
 *     both and read double the true value.
 *   - Categories come from the label column, never the row index. Three source
 *     charts were labelled 1..13 because the range was set to the wrong column.
 *   - Colour follows the entity, not its rank; series order is fixed server-side
 *     and validated for CVD adjacency.
 *   - No 3-D, no pies of time series.
 */
(function () {
    'use strict';

    if (typeof Chart === 'undefined') {
        console.warn('[PRS] Chart.js not loaded — charts skipped.');
        return;
    }

    var INK = {
        primary: '#1e293b',
        secondary: '#475569',
        muted: '#94a3b8',
        grid: '#f1f5f9',
        axis: '#cbd5e1',
        surface: '#ffffff',
    };

    var FONT = 'system-ui, -apple-system, "Segoe UI", sans-serif';

    Chart.defaults.font.family = FONT;
    Chart.defaults.font.size = 11;
    Chart.defaults.color = INK.muted;

    function fmt(n) {
        return (n === null || n === undefined) ? '—' : Number(n).toLocaleString();
    }

    /** Legend: always present for >= 2 series, never for one (the title names it). */
    function legend(seriesCount) {
        return {
            display: seriesCount > 1,
            position: 'top',
            align: 'end',
            labels: {
                boxWidth: 8,
                boxHeight: 8,
                usePointStyle: true,
                pointStyle: 'circle',
                padding: 16,
                color: INK.secondary,
                font: { size: 11, weight: '700' },
            },
        };
    }

    function tooltip(stacked) {
        return {
            backgroundColor: '#ffffff',
            titleColor: INK.primary,
            bodyColor: INK.secondary,
            borderColor: '#e2e8f0',
            borderWidth: 1,
            padding: 12,
            cornerRadius: 10,
            displayColors: true,
            boxWidth: 8,
            boxHeight: 8,
            usePointStyle: true,
            pointStyle: 'circle',
            titleFont: { size: 12, weight: '800' },
            bodyFont: { size: 11, weight: '500' },
            shadowColor: 'rgba(0, 0, 0, 0.05)',
            shadowBlur: 10,
            callbacks: {
                label: function (ctx) {
                    return ' ' + ctx.dataset.label + ': ' + fmt(value(ctx));
                },
                footer: stacked ? function (items) {
                    var sum = items.reduce(function (a, i) { return a + value(i); }, 0);
                    return 'Total: ' + fmt(sum);
                } : undefined,
            },
        };
    }

    function isHorizontal(chart) {
        return chart && chart.options && chart.options.indexAxis === 'y';
    }

    /** Tooltip item -> numeric value, whichever axis carries it. */
    function value(ctx) {
        return isHorizontal(ctx.chart) ? ctx.parsed.x : ctx.parsed.y;
    }

    // NOTE: grid.drawBorder / grid.borderColor are the Chart.js 3.x spelling.
    // The `scale.border` object is 4.x-only and is silently ignored on 3.9.1.
    function valueAxis(stacked) {
        return {
            stacked: !!stacked,
            beginAtZero: true,
            grid: {
                color: INK.grid,
                drawTicks: false,
                drawBorder: false,
            },
            ticks: { color: INK.muted, padding: 8, callback: function (v) { return fmt(v); } },
        };
    }

    function categoryAxis(stacked) {
        return {
            stacked: !!stacked,
            grid: {
                display: false,
                drawBorder: true,
                borderColor: INK.axis,
            },
            ticks: { color: INK.secondary, padding: 6, autoSkip: false, maxRotation: 0, minRotation: 0 },
        };
    }

    /** Thin marks, 4px rounded data-ends, 2px surface gap between stacked fills. */
    function barStyling(series, stacked) {
        return series.map(function (s) {
            return {
                label: s.label,
                data: s.data,
                backgroundColor: s.color,
                borderColor: INK.surface,
                borderWidth: stacked ? 2 : 0,
                borderRadius: 4,
                borderSkipped: false,
                barPercentage: 0.72,
                categoryPercentage: 0.78,
                maxBarThickness: 34,
            };
        });
    }

    function build(canvas) {
        var cfg;
        try {
            cfg = JSON.parse(canvas.dataset.chart);
        } catch (e) {
            console.error('[PRS] bad chart payload on #' + canvas.id, e);
            return;
        }

        var type = cfg.type;
        var horizontal = (type === 'bar-h' || type === 'stacked-bar-h');
        var stacked = (type === 'stacked-column' || type === 'stacked-bar-h');
        var datasets = barStyling(cfg.series, stacked);

        var options = {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: horizontal ? 'y' : 'x',
            interaction: { mode: 'index', intersect: false },
            layout: { padding: { top: 4, right: 12, bottom: 0, left: 0 } },
            plugins: {
                legend: legend(cfg.series.length),
                tooltip: tooltip(stacked),
            },
            scales: horizontal
                ? { x: valueAxis(stacked), y: categoryAxis(stacked) }
                : { x: categoryAxis(stacked), y: valueAxis(stacked) },
        };

        // Long month names collide on narrow viewports — abbreviate rather than rotate.
        if (!horizontal && cfg.labels.length === 12) {
            options.scales.x.ticks.callback = function (val, i) {
                var label = cfg.labels[i] || '';
                return window.innerWidth < 1100 ? label.slice(0, 3) : label;
            };
        }

        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: { labels: cfg.labels, datasets: datasets },
            options: options,
        });
    }

    function initCharts() {
        document.querySelectorAll('canvas[data-chart]').forEach(build);
    }

    function initTableToggles() {
        document.querySelectorAll('.js-toggle-table').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = document.getElementById(btn.dataset.target);
                if (!target) return;

                var open = target.classList.toggle('hidden');
                btn.setAttribute('aria-expanded', String(!open));

                var chev = btn.querySelector('.js-chevron');
                if (chev) chev.style.transform = open ? 'rotate(180deg)' : '';
            });
        });
    }

    function initPrint() {
        var btn = document.getElementById('btn_print');
        if (btn) btn.addEventListener('click', function () { window.print(); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCharts();
        initTableToggles();
        initPrint();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
})();
