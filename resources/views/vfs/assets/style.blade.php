<style>
/*
 * Virtual Folder System — the styling Tailwind 2.2.19's CDN build cannot express.
 *
 * The app loads Tailwind from CDN, so there is no JIT: arbitrary values
 * (max-h-[85vh], w-[400px], bg-black/40) are inert, and the slate-*, amber-*
 * and emerald-* palettes do not exist. Anything below is here because a default
 * utility could not do it, not as a matter of preference.
 */

.vfs-shell {
    display: flex;
    min-height: 0;
}

/* Left section nav */
.vfs-nav {
    width: 232px;
    flex-shrink: 0;
}

/* Preview panel; collapses to a drawer under 1024px (see the query below) */
.vfs-preview {
    width: 320px;
    flex-shrink: 0;
}

/* Thumbnail tiles: contain-fit so a landscape survey plan keeps its aspect
   ratio instead of being cropped into a square. */
.vfs-thumb {
    height: 104px;
    background: #eef2f7;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.vfs-thumb img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

/* Long document names wrap to two lines and stay fully available in details. */
.vfs-tile-label {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Status timeline rail, matching the existing file-history treatment. */
.vfs-timeline {
    position: relative;
    padding-left: 40px;
}

.vfs-timeline-line {
    position: absolute;
    left: 15px;
    top: 8px;
    bottom: 8px;
    width: 2px;
    background: #e5e7eb;
}

.vfs-timeline-node {
    position: absolute;
    left: -33px;
    top: 12px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #ffffff;
    border-width: 3px;
    border-style: solid;
}

/* Cancellation watermark. A VIEWING overlay only — never burned into the
   physical original, which is exactly what the framework requires. */
.vfs-watermark {
    position: relative;
}

.vfs-watermark::after {
    content: attr(data-watermark);
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-24deg);
    font-size: 64px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: rgba(31, 41, 55, 0.14);
    pointer-events: none;
    white-space: nowrap;
}

@media (max-width: 1280px) {
    .vfs-preview { width: 280px; }
}

/* Linked-file cards.

   Tailwind 2.2.19 from the CDN has no JIT, so a hover ring cannot be written as
   a utility here — it lives in CSS. The lift is deliberately small: these are
   records, not products, and a card that jumps reads as a button rather than a
   file. */
.vfs-link-card {
    transition: border-color 120ms ease, box-shadow 120ms ease, transform 120ms ease;
}

.vfs-link-card:hover {
    border-color: #60a5fa;                          /* blue-400 */
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.16);  /* blue-600 at low alpha */
    transform: translateY(-1px);
}

.vfs-link-card:focus-visible {
    outline: 2px solid #2563eb;                     /* blue-600 */
    outline-offset: 1px;
}

/* Long holder names and property lines must not widen the grid column, which on a
   narrow viewport would push the card past the page edge. */
.vfs-link-card .truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Tablet and phone: the preview becomes a drawer and the nav collapses,
   with no horizontal page overflow at any width. */
@media (max-width: 1024px) {
    .vfs-shell { flex-direction: column; }
    .vfs-nav,
    .vfs-preview { width: 100%; }
}
</style>
