@extends('layouts.app')
@section('page-title') My Digital Files @endsection

@section('content')
<div class="flex-1 overflow-y-auto overflow-x-hidden">

    @include('admin.header')

    {{-- ── Module Banner ──────────────────────────────────────────────────── --}}
    <div class="bg-gradient-to-r from-[#450a0a] via-[#6b1010] to-[#450a0a] px-6 py-3 flex items-center gap-3 shadow-sm">
        <i data-lucide="folder-open" class="h-5 w-5 text-white shrink-0"></i>
        <div class="flex items-center gap-2">
            <span class="text-white font-bold text-sm uppercase tracking-widest">My Digital Files</span>
            <span class="text-red-200 text-sm">·</span>
            <span class="text-white text-sm font-medium">Temporary Access</span>
            <span class="text-red-200 text-sm">·</span>
            <span class="text-red-200 text-sm">View-Only</span>
        </div>
        <div class="ml-auto flex items-center gap-3">
            <a href="{{ route('digital-request.index') }}"
                class="inline-flex items-center gap-1.5 text-xs font-medium text-red-100 hover:text-white transition-colors">
                <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                Back to Requests
            </a>
        </div>
    </div>

    <div class="p-6">
        <div class="container mx-auto py-4 space-y-6">

            {{-- ── Notice ──────────────────────────────────────────────────── --}}
            <div class="flex items-start gap-3 p-3.5 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800">
                <i data-lucide="shield-alert" class="h-4 w-4 text-blue-500 shrink-0 mt-0.5"></i>
                <div class="text-xs">
                    <strong>View-only access.</strong>
                    Temporary copies from the digital archive. Right-click and downloads are disabled. Access expires automatically after 5 working days.
                </div>
            </div>

            {{-- ── Active Accesses ─────────────────────────────────────────── --}}
            <div>
                <h2 class="text-sm font-semibold text-gray-800 flex items-center gap-2 mb-4">
                    <i data-lucide="folder-open" class="h-4 w-4 text-[#450a0a]"></i>
                    Active Digital Files
                    <span class="inline-flex items-center justify-center min-w-[1.3rem] h-5 px-1.5 rounded-full
                                 {{ $active->count() > 0 ? 'bg-[#450a0a] text-white' : 'bg-gray-200 text-gray-500' }}
                                 text-[10px] font-black">
                        {{ $active->count() }}
                    </span>
                </h2>

                @if($active->isEmpty())
                    <div class="bg-white border border-gray-200 rounded-xl p-10 text-center text-gray-400 shadow-sm">
                        <i data-lucide="folder" class="h-10 w-10 mx-auto mb-3 text-gray-300"></i>
                        <p class="text-sm">No active digital file access.</p>
                        <p class="text-xs mt-1 text-gray-400">Request digital access from the
                            <a href="{{ route('digital-request.index') }}" class="text-[#450a0a] hover:underline">Digital File Requests</a> page.
                        </p>
                    </div>
                @else
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-5">
                        @foreach($active as $access)
                        @php
                            $days    = $access->daysRemaining();
                            $badgeCls = $access->expiryBadgeClass();
                            $files   = (array) ($access->files_copied ?? []);
                        @endphp

                        {{-- ── Folder card ─────────────────────────────────── --}}
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col"
                             data-access-id="{{ $access->id }}">

                            {{-- Folder tab strip --}}
                            <div class="flex items-end px-4 pt-3 gap-0">
                                <div class="relative flex items-center gap-2 bg-amber-50 border border-b-0 border-amber-200 rounded-t-xl px-4 py-2 min-w-0">
                                    <svg class="h-4 w-4 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 6a2 2 0 012-2h4l2 2h6a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                                    </svg>
                                    <span class="text-xs font-bold text-amber-800 font-mono truncate max-w-[120px]" title="{{ $access->file_no }}">
                                        {{ $access->file_no }}
                                    </span>
                                </div>
                                <div class="flex-1 border-b border-gray-200 pb-0 h-[1px] self-end"></div>
                                {{-- Expiry badge --}}
                                <span class="shrink-0 inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 mb-0.5 rounded-full border {{ $badgeCls }}">
                                    <i data-lucide="clock" class="h-3 w-3"></i>
                                    {{ $days > 0 ? $days . 'd left' : 'Expires today' }}
                                </span>
                            </div>

                            {{-- Folder body --}}
                            <div class="border border-gray-200 border-t-0 rounded-b-2xl rounded-tr-2xl flex-1 flex flex-col overflow-hidden">

                                {{-- File info header --}}
                                <div class="bg-amber-50/60 px-4 py-2.5 border-b border-amber-100 flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        @if($access->file_title)
                                            <p class="text-xs font-semibold text-gray-700 truncate">{{ $access->file_title }}</p>
                                        @endif
                                        <p class="text-[10px] text-gray-400 mt-0.5">
                                            Granted: {{ $access->granted_at?->format('d M Y') ?? '—' }}
                                            &nbsp;·&nbsp;
                                            Expires: {{ $access->expires_at->format('d M Y') }}
                                        </p>
                                    </div>
                                    <span class="shrink-0 text-[10px] text-gray-400 bg-white border border-gray-200 px-2 py-0.5 rounded-full whitespace-nowrap">
                                        {{ count($files) }} file{{ count($files) !== 1 ? 's' : '' }}
                                    </span>
                                </div>

                                {{-- File grid (folder contents) --}}
                                <div class="p-3 flex-1">
                                    @if(empty($files))
                                        <p class="text-xs text-gray-400 italic p-2">No files in this access grant.</p>
                                    @else
                                        <div class="grid grid-cols-3 gap-2">
                                            @foreach($files as $filename)
                                            @php
                                                $ext   = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                                $isPdf = $ext === 'pdf';
                                                $viewUrl = route('digital-request.view-file', ['access' => $access->id, 'filename' => $filename]);
                                                $shortName = strlen($filename) > 16 ? substr($filename, 0, 13) . '…' : $filename;
                                            @endphp
                                            {{-- File icon tile --}}
                                            <button type="button"
                                                class="dfr-file-tile group flex flex-col items-center gap-1.5 p-2 rounded-xl border border-transparent
                                                       hover:bg-blue-50 hover:border-blue-200 transition-all text-center cursor-pointer"
                                                data-url="{{ $viewUrl }}"
                                                data-filename="{{ $filename }}"
                                                data-ext="{{ $ext }}"
                                                data-is-pdf="{{ $isPdf ? 'true' : 'false' }}"
                                                title="{{ $filename }}">

                                                {{-- File icon --}}
                                                @if($isPdf)
                                                <div class="relative w-10 h-12 flex items-end justify-center">
                                                    <svg viewBox="0 0 40 48" class="w-10 h-12 drop-shadow-sm" fill="none">
                                                        <path d="M4 4C4 1.8 5.8 0 8 0H28L40 12V44C40 46.2 38.2 48 36 48H8C5.8 48 4 46.2 4 44V4Z" fill="#FEE2E2"/>
                                                        <path d="M28 0L40 12H32C29.8 12 28 10.2 28 8V0Z" fill="#FCA5A5"/>
                                                        <rect x="8" y="22" width="24" height="3" rx="1.5" fill="#EF4444"/>
                                                        <rect x="8" y="29" width="18" height="2.5" rx="1.25" fill="#FCA5A5"/>
                                                        <rect x="8" y="35" width="20" height="2.5" rx="1.25" fill="#FCA5A5"/>
                                                    </svg>
                                                    <span class="absolute bottom-1 left-1/2 -translate-x-1/2 text-[7px] font-black text-red-600 uppercase tracking-wider">PDF</span>
                                                </div>
                                                @else
                                                <div class="relative w-10 h-12 flex items-center justify-center">
                                                    <svg viewBox="0 0 40 48" class="w-10 h-12 drop-shadow-sm" fill="none">
                                                        <path d="M4 4C4 1.8 5.8 0 8 0H28L40 12V44C40 46.2 38.2 48 36 48H8C5.8 48 4 46.2 4 44V4Z" fill="#DBEAFE"/>
                                                        <path d="M28 0L40 12H32C29.8 12 28 10.2 28 8V0Z" fill="#93C5FD"/>
                                                        {{-- Mountain / image icon --}}
                                                        <circle cx="14" cy="22" r="3" fill="#60A5FA"/>
                                                        <path d="M8 36L16 24L22 30L28 22L36 36H8Z" fill="#93C5FD"/>
                                                    </svg>
                                                </div>
                                                @endif

                                                {{-- Filename --}}
                                                <span class="text-[10px] text-gray-600 leading-tight group-hover:text-blue-700 w-full truncate px-0.5">
                                                    {{ $shortName }}
                                                </span>
                                            </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                {{-- Footer --}}
                                @if(!empty($files))
                                <div class="px-3 py-2 bg-gray-50 border-t border-gray-100 flex items-center justify-end">
                                    <button type="button"
                                        class="btn-open-all inline-flex items-center gap-1.5 text-[10px] font-semibold text-[#450a0a]
                                               hover:text-[#5c0c0c] bg-white hover:bg-red-50 border border-red-200 px-2.5 py-1 rounded-lg transition-colors"
                                        data-files="{{ json_encode(array_map(fn($f) => route('digital-request.view-file', ['access' => $access->id, 'filename' => $f]), $files)) }}"
                                        data-names="{{ json_encode($files) }}">
                                        <i data-lucide="layers" class="h-3 w-3"></i>
                                        Open All
                                    </button>
                                </div>
                                @endif

                            </div>{{-- end folder body --}}
                        </div>{{-- end folder card --}}
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ── History ─────────────────────────────────────────────────── --}}
            @if($history->isNotEmpty())
            <div>
                <h2 class="text-sm font-semibold text-gray-400 flex items-center gap-2 mb-3">
                    <i data-lucide="history" class="h-4 w-4 text-gray-300"></i>
                    Access History
                </h2>
                <div class="space-y-1.5">
                    @foreach($history as $access)
                    @php
                        $statusLabel = match($access->access_status) {
                            'expired' => ['label' => 'Expired',  'cls' => 'text-gray-500 bg-gray-100 border-gray-200'],
                            'revoked' => ['label' => 'Revoked',  'cls' => 'text-red-600 bg-red-50 border-red-200'],
                            default   => ['label' => ucfirst($access->access_status), 'cls' => 'text-gray-500 bg-gray-100 border-gray-200'],
                        };
                        $files = (array) ($access->files_copied ?? []);
                    @endphp
                    <div class="bg-white border border-gray-100 rounded-xl px-4 py-3 flex items-center gap-4 opacity-50 hover:opacity-70 transition-opacity">
                        <svg class="h-4 w-4 text-gray-300 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h4l2 2h6a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                        </svg>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold text-gray-500 font-mono">{{ $access->file_no }}</p>
                            @if($access->file_title)
                                <p class="text-[10px] text-gray-400 truncate">{{ $access->file_title }}</p>
                            @endif
                        </div>
                        <div class="text-[10px] text-gray-400 whitespace-nowrap hidden sm:block">
                            {{ $access->granted_at?->format('d M Y') ?? '—' }} → {{ $access->expires_at->format('d M Y') }}
                        </div>
                        <div class="text-[10px] text-gray-400 whitespace-nowrap">{{ count($files) }} file(s)</div>
                        <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full border {{ $statusLabel['cls'] }}">
                            {{ $statusLabel['label'] }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>

</div>

{{-- ════════════════════════════════════════════════════════════════════════ --}}
{{-- ── File Preview Modal ─────────────────────────────────────────────────── --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}
<div id="dfr-preview-modal"
     class="fixed inset-0 z-[9999] flex items-center justify-center p-3 bg-black/80 backdrop-blur-sm hidden"
     role="dialog" aria-modal="true" aria-label="File Preview">

    <div class="relative bg-white rounded-2xl shadow-2xl flex flex-col w-full max-w-6xl overflow-hidden"
         style="height:92vh;max-height:92vh;">

        {{-- Header --}}
        <div class="flex items-center gap-3 px-4 py-2.5 border-b border-gray-200 bg-gray-50 rounded-t-2xl shrink-0">
            <div id="modal-file-icon" class="shrink-0"></div>
            <div class="min-w-0 flex-1">
                <p id="modal-filename" class="text-sm font-semibold text-gray-800 truncate">—</p>
                <p id="modal-filemeta" class="text-[10px] text-gray-400 mt-0.5">—</p>
            </div>
            <div class="flex items-center gap-0.5 shrink-0">
                <button type="button" id="modal-prev"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                    title="Previous (←)">
                    <i data-lucide="chevron-left" class="h-4 w-4"></i>
                </button>
                <span id="modal-counter" class="text-xs text-gray-400 font-mono px-1 min-w-[2.8rem] text-center">1 / 1</span>
                <button type="button" id="modal-next"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                    title="Next (→)">
                    <i data-lucide="chevron-right" class="h-4 w-4"></i>
                </button>
            </div>
            <span class="hidden sm:inline-flex items-center gap-1 text-[10px] font-semibold text-blue-700 bg-blue-50 border border-blue-200 px-2.5 py-1 rounded-full shrink-0">
                <i data-lucide="shield" class="h-3 w-3"></i>View-Only
            </span>
            <button type="button" id="modal-close"
                class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-200 transition-all ml-1"
                title="Close (Esc)">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        {{-- Body: side panel + viewer --}}
        <div class="flex flex-1 min-h-0 overflow-hidden">

            {{-- Side panel thumbnails --}}
            <div id="modal-sidepanel"
                 class="w-[152px] shrink-0 overflow-y-auto bg-gray-50 border-r border-gray-200 flex flex-col gap-1 p-2">
            </div>

            {{-- Viewer --}}
            <div id="modal-viewer"
                 class="flex-1 min-w-0 flex items-center justify-center overflow-hidden relative"
                 style="background:#111827;">

                <div id="modal-loading"
                     class="absolute inset-0 flex flex-col items-center justify-center gap-3 z-10"
                     style="background:#111827;">
                    <div class="w-9 h-9 border-4 border-white/10 border-t-[#450a0a] rounded-full animate-spin"></div>
                    <span class="text-xs mt-1" style="color:rgba(255,255,255,0.4);">Loading…</span>
                </div>

                <img id="modal-img" src="" alt=""
                     class="hidden select-none"
                     style="max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;display:none;"
                     draggable="false">

                <iframe id="modal-pdf" src="" title="PDF preview"
                        style="position:absolute;inset:0;width:100%;height:100%;border:0;display:none;">
                </iframe>

            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
(function () {
    'use strict';

    document.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    lucide.createIcons();

    var _files   = [];
    var _current = 0;

    var modal     = document.getElementById('dfr-preview-modal');
    var modalImg  = document.getElementById('modal-img');
    var modalPdf  = document.getElementById('modal-pdf');
    var modalLoad = document.getElementById('modal-loading');
    var modalName = document.getElementById('modal-filename');
    var modalMeta = document.getElementById('modal-filemeta');
    var modalIcon = document.getElementById('modal-file-icon');
    var modalCtr  = document.getElementById('modal-counter');
    var modalPrev = document.getElementById('modal-prev');
    var modalNext = document.getElementById('modal-next');
    var modalClose= document.getElementById('modal-close');
    var sidePanel = document.getElementById('modal-sidepanel');

    function pdfSvg(cls) {
        return '<svg viewBox="0 0 40 48" class="' + cls + '" fill="none">'
            + '<path d="M4 4C4 1.8 5.8 0 8 0H28L40 12V44C40 46.2 38.2 48 36 48H8C5.8 48 4 46.2 4 44V4Z" fill="#FEE2E2"/>'
            + '<path d="M28 0L40 12H32C29.8 12 28 10.2 28 8V0Z" fill="#FCA5A5"/>'
            + '<rect x="8" y="22" width="24" height="3" rx="1.5" fill="#EF4444"/>'
            + '<rect x="8" y="29" width="18" height="2.5" rx="1.25" fill="#FCA5A5"/>'
            + '<rect x="8" y="35" width="20" height="2.5" rx="1.25" fill="#FCA5A5"/>'
            + '</svg>';
    }
    function imgSvg(cls) {
        return '<svg viewBox="0 0 40 48" class="' + cls + '" fill="none">'
            + '<path d="M4 4C4 1.8 5.8 0 8 0H28L40 12V44C40 46.2 38.2 48 36 48H8C5.8 48 4 46.2 4 44V4Z" fill="#DBEAFE"/>'
            + '<path d="M28 0L40 12H32C29.8 12 28 10.2 28 8V0Z" fill="#93C5FD"/>'
            + '<circle cx="14" cy="22" r="3" fill="#60A5FA"/>'
            + '<path d="M8 36L16 24L22 30L28 22L36 36H8Z" fill="#93C5FD"/>'
            + '</svg>';
    }
    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function showImg(show) {
        modalImg.style.display = show ? 'block' : 'none';
        if (!show) { modalImg.src = ''; }
    }
    function showPdf(show) {
        modalPdf.style.display = show ? 'block' : 'none';
        if (!show) { modalPdf.src = ''; }
    }
    function showLoad(show, html) {
        modalLoad.style.display = show ? 'flex' : 'none';
        if (html !== undefined) modalLoad.innerHTML = html;
    }

    function openModal(files, startIndex) {
        _files = files; _current = startIndex;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        renderSidePanel();
        renderFile(_current);
        lucide.createIcons();
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        showImg(false); showPdf(false);
        modalImg.onload = null; modalImg.onerror = null;
        modalPdf.onload = null;
    }

    function renderFile(idx) {
        if (!_files.length) return;
        var f = _files[idx];
        _current = idx;

        showImg(false); showPdf(false);
        showLoad(true,
            '<div class="w-9 h-9 border-4 border-white/10 border-t-[#450a0a] rounded-full animate-spin"></div>'
            + '<span class="text-xs mt-3" style="color:rgba(255,255,255,0.4);">Loading…</span>'
        );

        modalName.textContent = f.name;
        modalMeta.textContent = (f.isPdf ? 'PDF' : 'Image') + ' · ' + (idx + 1) + ' of ' + _files.length;
        modalCtr.textContent  = (idx + 1) + ' / ' + _files.length;
        modalIcon.innerHTML   = f.isPdf ? pdfSvg('h-7 w-5') : imgSvg('h-7 w-5');
        modalPrev.disabled    = (idx === 0);
        modalNext.disabled    = (idx === _files.length - 1);

        // Update side panel active state
        sidePanel.querySelectorAll('.dfr-thumb').forEach(function (t, i) {
            var active = (i === idx);
            t.style.background   = active ? '#fff' : '';
            t.style.borderColor  = active ? '#450a0a' : 'transparent';
            t.style.boxShadow    = active ? '0 1px 3px rgba(0,0,0,0.1)' : '';
        });
        var at = sidePanel.querySelectorAll('.dfr-thumb')[idx];
        if (at) at.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        if (f.isPdf) {
            modalPdf.onload = function () { showLoad(false); showPdf(true); };
            modalPdf.src = f.url;
        } else {
            var capturedUrl = f.url;
            var pre = new Image();
            pre.onload = function () {
                if (!_files[_current] || _files[_current].url !== capturedUrl) return;
                modalImg.src = capturedUrl;
                showImg(true);
                showLoad(false);
            };
            pre.onerror = function () {
                if (!_files[_current] || _files[_current].url !== capturedUrl) return;
                fetch(capturedUrl, { method: 'HEAD', credentials: 'same-origin' })
                    .then(function (r) {
                        showLoad(true,
                            '<div style="text-align:center;padding:1rem;">'
                            + '<p style="color:#f87171;font-size:.8rem;font-weight:600;">Could not load image</p>'
                            + '<p style="color:rgba(255,255,255,.4);font-size:.7rem;margin-top:.25rem;">HTTP ' + r.status
                            + ' · ' + escHtml(r.headers.get('content-type') || 'unknown') + '</p></div>'
                        );
                    })
                    .catch(function () {
                        showLoad(true,
                            '<div style="text-align:center;padding:1rem;">'
                            + '<p style="color:#f87171;font-size:.8rem;">Network error</p></div>'
                        );
                    });
            };
            pre.src = capturedUrl;
        }
    }

    function renderSidePanel() {
        sidePanel.innerHTML = '';
        _files.forEach(function (f, i) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.title = f.name;
            btn.className = 'dfr-thumb w-full flex items-center gap-2 px-2 py-2 rounded-xl border transition-all text-left';
            btn.style.border = i === _current ? '1.5px solid #450a0a' : '1.5px solid transparent';
            btn.style.background = i === _current ? '#fff' : '';
            btn.style.boxShadow  = i === _current ? '0 1px 3px rgba(0,0,0,0.1)' : '';
            btn.innerHTML =
                (f.isPdf ? pdfSvg('h-8 w-6 shrink-0') : imgSvg('h-8 w-6 shrink-0'))
                + '<div style="min-width:0;flex:1;">'
                +   '<p style="font-size:10px;font-weight:600;color:#374151;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'
                +     escHtml(f.name)
                +   '</p>'
                +   '<p style="font-size:9px;color:#9ca3af;margin-top:2px;">' + (f.isPdf ? 'PDF' : 'Image') + '</p>'
                + '</div>';
            btn.addEventListener('mouseenter', function () {
                if (i !== _current) { btn.style.background = '#f9fafb'; btn.style.borderColor = '#e5e7eb'; }
            });
            btn.addEventListener('mouseleave', function () {
                if (i !== _current) { btn.style.background = ''; btn.style.borderColor = 'transparent'; }
            });
            btn.addEventListener('click', function () { renderFile(i); });
            sidePanel.appendChild(btn);
        });
    }

    // Wire page tiles
    document.querySelectorAll('[data-access-id]').forEach(function (card) {
        var tiles = Array.from(card.querySelectorAll('.dfr-file-tile'));
        var fileList = tiles.map(function (t) {
            return { url: t.dataset.url, name: t.dataset.filename, ext: t.dataset.ext, isPdf: t.dataset.isPdf === 'true' };
        });
        tiles.forEach(function (t, idx) {
            t.addEventListener('click', function () { openModal(fileList, idx); });
        });
    });

    document.querySelectorAll('.btn-open-all').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('[data-access-id]');
            if (!card) return;
            var tiles = Array.from(card.querySelectorAll('.dfr-file-tile'));
            if (!tiles.length) return;
            var fileList = tiles.map(function (t) {
                return { url: t.dataset.url, name: t.dataset.filename, ext: t.dataset.ext, isPdf: t.dataset.isPdf === 'true' };
            });
            openModal(fileList, 0);
        });
    });

    modalPrev.addEventListener('click', function () { if (_current > 0) renderFile(_current - 1); });
    modalNext.addEventListener('click', function () { if (_current < _files.length - 1) renderFile(_current + 1); });
    modalClose.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) {
        if (modal.classList.contains('hidden')) return;
        if (e.key === 'Escape')     closeModal();
        if (e.key === 'ArrowRight') { if (_current < _files.length - 1) renderFile(_current + 1); }
        if (e.key === 'ArrowLeft')  { if (_current > 0)                  renderFile(_current - 1); }
    });

})();
</script>
@endsection