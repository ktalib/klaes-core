<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Parcel Update Template Previews</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, Helvetica, sans-serif;
      background: #e8edf2;
      min-height: 100vh;
    }

    /* ── TOP NAV ── */
    .topbar {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 1000;
      background: #1a3552;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 0;
      padding: 0;
      box-shadow: 0 2px 8px rgba(0,0,0,0.35);
      height: 52px;
    }
    .topbar-brand {
      padding: 0 20px;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.5px;
      white-space: nowrap;
      border-right: 1px solid rgba(255,255,255,0.12);
      height: 100%;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .topbar-brand svg { width: 18px; height: 18px; opacity: 0.85; }
    .nav-tabs {
      display: flex;
      height: 100%;
      overflow-x: auto;
      flex: 1;
    }
    .nav-tabs::-webkit-scrollbar { display: none; }
    .nav-tab {
      display: flex;
      align-items: center;
      padding: 0 16px;
      font-size: 12px;
      font-weight: 600;
      color: rgba(255,255,255,0.6);
      text-decoration: none;
      white-space: nowrap;
      border-right: 1px solid rgba(255,255,255,0.08);
      transition: background 0.15s, color 0.15s;
      height: 100%;
    }
    .nav-tab:hover { background: rgba(255,255,255,0.07); color: #fff; }
    .nav-tab.active { background: rgba(255,255,255,0.13); color: #fff; border-bottom: 3px solid #4aa3e8; }
    .topbar-actions {
      padding: 0 16px;
      height: 100%;
      display: flex;
      align-items: center;
      gap: 8px;
      border-left: 1px solid rgba(255,255,255,0.12);
    }
    .btn-print-all {
      background: #4aa3e8;
      color: #fff;
      border: none;
      border-radius: 5px;
      padding: 6px 14px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .btn-print-all:hover { background: #3a8fd4; }

    /* ── MAIN CONTENT ── */
    .content {
      padding-top: 52px;
    }

    /* ── SECTION WRAPPER ── */
    .template-section {
      padding: 30px 20px 10px;
    }
    .section-label {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      color: #6b7a8d;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .section-label::after {
      content: '';
      flex: 1;
      height: 1px;
      background: #c8d3de;
    }
    .section-actions {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
      margin-bottom: 10px;
    }
    .btn-open {
      font-size: 11px;
      font-weight: 600;
      color: #1a5276;
      background: #ddeeff;
      border: 1px solid #a8ccea;
      border-radius: 4px;
      padding: 4px 12px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .btn-open:hover { background: #c5e0f7; }
    .btn-print {
      font-size: 11px;
      font-weight: 600;
      color: #fff;
      background: #1a5276;
      border: none;
      border-radius: 4px;
      padding: 4px 12px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .btn-print:hover { background: #154360; }

    /* ── DOCUMENT FRAME ── */
    .doc-frame-wrapper {
      display: flex;
      justify-content: center;
      padding-bottom: 30px;
    }
    .doc-frame {
      background: #fff;
      width: 210mm;
      min-height: 297mm;
      box-shadow: 0 4px 24px rgba(0,0,0,0.18);
      border-radius: 2px;
      overflow: hidden;
    }
    .doc-frame iframe {
      width: 100%;
      height: 297mm;
      border: none;
      display: block;
    }

    /* ── SEPARATOR ── */
    .section-divider {
      height: 1px;
      background: #c8d3de;
      margin: 0 20px;
    }

    /* ── PRINT STYLES ── */
    @media print {
      .topbar, .section-label, .section-actions, .section-divider { display: none !important; }
      .content { padding-top: 0; }
      .template-section { padding: 0; page-break-after: always; }
      .doc-frame { box-shadow: none; width: 100%; min-height: auto; }
      .doc-frame iframe { height: auto; }
    }
  </style>
</head>
<body>

{{-- TOP NAVIGATION --}}
<nav class="topbar">
  <div class="topbar-brand">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    Parcel Templates
  </div>
  <div class="nav-tabs">
    <a href="#all" class="nav-tab active" onclick="showAll(event)">All Templates</a>
    @foreach($templates as $key => $t)
    <a href="#{{ $key }}" class="nav-tab" onclick="scrollTo('{{ $key }}', event)">{{ $t['label'] }}</a>
    @endforeach
  </div>
  <div class="topbar-actions">
    <button class="btn-print-all" onclick="window.open('{{ url('/preview-parcel-templates?type=print_all') }}','_blank','width=900,height=800')">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      Print All
    </button>
  </div>
</nav>

{{-- TEMPLATE SECTIONS --}}
<div class="content">
  @foreach($templates as $key => $t)
  <div class="template-section" id="{{ $key }}">
    <div class="section-label">{{ $t['label'] }}</div>
    <div class="section-actions">
      <a href="{{ url('/preview-parcel-templates?type='.$key) }}" target="_blank" class="btn-open">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        Open Full Page
      </a>
      <button class="btn-print" onclick="printFrame('frame-{{ $key }}')">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print This
      </button>
    </div>
    <div class="doc-frame-wrapper">
      <div class="doc-frame">
        <iframe
          id="frame-{{ $key }}"
          src="{{ url('/preview-parcel-templates?type='.$key) }}"
          loading="lazy"
          title="{{ $t['label'] }}"
        ></iframe>
      </div>
    </div>
  </div>
  @if(!$loop->last)
  <div class="section-divider"></div>
  @endif
  @endforeach
</div>

<script>
  function scrollTo(id, e) {
    e.preventDefault();
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
    e.currentTarget.classList.add('active');
  }

  function showAll(e) {
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
    e.currentTarget.classList.add('active');
  }

  function printFrame(frameId) {
    const frame = document.getElementById(frameId);
    frame.contentWindow.focus();
    frame.contentWindow.print();
  }

  // Highlight active tab on scroll
  const sections = document.querySelectorAll('.template-section');
  const tabs = document.querySelectorAll('.nav-tab:not(:first-child)');
  window.addEventListener('scroll', () => {
    let current = '';
    sections.forEach(s => {
      if (window.scrollY >= s.offsetTop - 80) current = s.id;
    });
    tabs.forEach(t => {
      t.classList.remove('active');
      if (t.getAttribute('href') === '#' + current) t.classList.add('active');
    });
    if (!current) document.querySelector('.nav-tab').classList.add('active');
  }, { passive: true });
</script>
</body>
</html>
