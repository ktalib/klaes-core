<style>
    /* Modern Card System */
    .main-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 1.5rem;
    }
    
    .progress-card {
        background: white;
        border-radius: 0.9rem;
        padding: 1.1rem;
        box-shadow: 0 3px 16px rgba(15, 23, 42, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 1.5rem;
    }

    .progress-card.progress-card--inline {
        margin-bottom: 1rem;
    }
    
    .progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .progress-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3748;
    }
    
    .progress-counter {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .progress-bar-container {
        background: #e2e8f0;
        height: 8px;
        border-radius: 1rem;
        overflow: hidden;
    }
    
    .progress-bar-fill {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        height: 100%;
        border-radius: 1rem;
        transition: width 0.5s ease;
        position: relative;
    }
    
    .progress-bar-fill::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        animation: shimmer 2s infinite;
    }
    
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    
    /* Main Content Layout */
    .workspace-grid {
        display: grid;
        grid-template-areas:
            "viewer viewer"
            "folders classification";
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
        align-items: start;
    }

    .workspace-viewer { grid-area: viewer; }
    .workspace-folder { grid-area: folders; }
    .workspace-classification { grid-area: classification; }

    @media (min-width: 1680px) {
        .workspace-grid {
            grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr);
        }
    }

    @media (max-width: 1280px) {
        .workspace-grid {
            grid-template-areas:
                "viewer"
                "folders"
                "classification";
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
    }
    
    /* Document Viewer Card */
    .viewer-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    
    .viewer-header {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .viewer-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .viewer-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #2d3748;
    }
    
    .nav-controls {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .viewer-tools {
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .tool-btn {
        width: 34px;
        height: 34px;
        border-radius: 0.5rem;
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #374151;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        padding: 0;
    }

    .tool-btn i {
        width: 1.05rem;
        height: 1.05rem;
    }

    .tool-btn:hover {
        color: #4f46e5;
        border-color: #c7d2fe;
        background: #f5f7ff;
    }

    .tool-btn.active {
        border-color: #4f46e5;
        background: #eef2ff;
        color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
    }

    .tool-divider {
        width: 1px;
        height: 22px;
        background: #e2e8f0;
        display: inline-block;
        margin: 0 0.1rem;
    }
    
    .nav-btn {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .nav-btn:hover:not(:disabled) {
        border-color: #667eea;
        background: #f7fafc;
        transform: translateY(-1px);
    }
    
    .nav-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .doc-counter {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .document-viewer {
        padding: 1.5rem;
        min-height: 520px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .viewer-canvas {
        position: relative;
        width: 100%;
        height: 100%;
        min-height: 480px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: 0.75rem;
        background: linear-gradient(145deg, #f9fafb 0%, #f1f5f9 100%);
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.06);
    }

    .viewer-media-wrapper {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        cursor: default;
        position: relative;
    }

    .viewer-media-wrapper.pan-mode {
        cursor: grab;
    }

    .viewer-media-wrapper.pan-active {
        cursor: grabbing;
    }

    .viewer-media-wrapper.crop-mode {
        cursor: crosshair;
    }

    /* Crop Overlay Styles */
    .crop-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        pointer-events: auto;
        z-index: 10;
    }

    .crop-selection {
        position: absolute;
        border: 2px solid #3b82f6;
        background: rgba(59, 130, 246, 0.1);
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.3);
        cursor: move;
        min-width: 20px;
        min-height: 20px;
    }

    .crop-handle {
        position: absolute;
        width: 8px;
        height: 8px;
        background: #3b82f6;
        border: 1px solid white;
        border-radius: 50%;
        z-index: 12;
    }

    .crop-handle-nw {
        top: -4px;
        left: -4px;
        cursor: nw-resize;
    }

    .crop-handle-ne {
        top: -4px;
        right: -4px;
        cursor: ne-resize;
    }

    .crop-handle-sw {
        bottom: -4px;
        left: -4px;
        cursor: sw-resize;
    }

    .crop-handle-se {
        bottom: -4px;
        right: -4px;
        cursor: se-resize;
    }

    .crop-actions {
        position: absolute;
        top: -40px;
        right: 0;
        display: flex;
        gap: 4px;
        z-index: 13;
    }

    .crop-action-btn {
        width: 32px;
        height: 32px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background-color 0.15s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .crop-action-btn:hover {
        background: #2563eb;
    }

    .crop-action-btn.crop-cancel {
        background: #ef4444;
    }

    .crop-action-btn.crop-cancel:hover {
        background: #dc2626;
    }

    .crop-action-btn i {
        width: 16px;
        height: 16px;
    }

    /* Tool button active state for crop */
    .tool-btn[aria-pressed="true"] {
        background-color: #3b82f6;
        color: white;
    }

    .viewer-media-wrapper.dropzone-hover::after {
        content: '';
        position: absolute;
        inset: 0;
        border: 2px dashed rgba(99, 102, 241, 0.8);
        border-radius: 0.75rem;
        pointer-events: none;
    }

    .viewer-media-wrapper.dropzone-uploading::after {
        content: 'Replacing…';
        position: absolute;
        inset: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, 0.65);
        color: #f8fafc;
        font-size: 0.9rem;
        font-weight: 600;
        border-radius: 0.75rem;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    .viewer-media {
        display: flex;
        align-items: center;
        justify-content: center;
        transform-origin: center center;
        transition: transform 0.18s ease;
        will-change: transform;
        min-width: 160px;
    }

    .viewer-media-content {
        max-width: 100%;
        max-height: 520px;
        border-radius: 0.75rem;
        border: 1px solid rgba(148, 163, 184, 0.35);
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.12);
        background: white;
    }

    .viewer-media iframe.viewer-media-content {
        width: clamp(60%, 860px, 100%);
        height: 520px;
        border: none;
    }

    .viewer-media img.viewer-media-content,
    .viewer-media canvas.viewer-media-content {
        object-fit: contain;
    }
    
    .viewer-placeholder {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #718096;
        pointer-events: none;
        background: linear-gradient(145deg, rgba(255,255,255,0.75) 0%, rgba(248,250,252,0.85) 100%);
    }
    
    .viewer-placeholder.hidden {
        display: none;
    }
    
    .viewer-placeholder i {
        font-size: 3.5rem;
        margin-bottom: 0.75rem;
        opacity: 0.45;
    }
    
    /* Sidebar */
    .sidebar {
        gap: 1.5rem;
    }
    
    /* Folder Navigation & Workspace */
    .folder-card {
        background: white;
        border-radius: 1rem;
        box-shadow: 0 6px 24px rgba(15, 23, 42, 0.06);
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        margin-bottom: 0;
        overflow: hidden;
    }

    .folder-header {
        padding: 0.85rem 1.25rem;
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .folder-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }

    .folder-title i {
        width: 1.2rem;
        height: 1.2rem;
        color: #4f46e5;
    }

    .folder-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        align-items: center;
        justify-content: flex-end;
        color: #475569;
        font-size: 0.78rem;
    }

    .folder-meta-label {
        font-weight: 600;
        color: #334155;
    }

    .folder-chip {
        background: rgba(79, 70, 229, 0.08);
        color: #4338ca;
        border-radius: 999px;
        padding: 0.2rem 0.6rem;
        font-size: 0.7rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .folder-chip:hover {
        background: rgba(79, 70, 229, 0.15);
    }

    .folder-body {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
        padding: 1rem 1.25rem;
    }

    .folder-group-scroller {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
    }

    .folder-group-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(79, 70, 229, 0.02) 100%);
        border: 1px solid rgba(99, 102, 241, 0.12);
        border-radius: 0.75rem;
        padding: 0.45rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #312e81;
    }

    .folder-group-title {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-weight: 700;
        font-size: 0.78rem;
        color: #3730a3;
    }

    .folder-group-title i {
        width: 0.9rem;
        height: 0.9rem;
    }

    .folder-group-actions {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
    }

    .folder-group-count {
        font-size: 0.72rem;
        color: #4c51bf;
        background: rgba(79, 70, 229, 0.12);
        border-radius: 999px;
        padding: 0.2rem 0.5rem;
    }

    .folder-group-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        border: 1px solid rgba(79, 70, 229, 0.2);
        background: rgba(255, 255, 255, 0.8);
        color: #3730a3;
        padding: 0.25rem 0.6rem;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .folder-group-toggle:hover {
        border-color: rgba(79, 70, 229, 0.35);
        background: rgba(255, 255, 255, 0.95);
        color: #312e81;
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.18);
    }

    .folder-group-toggle:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
    }

    .folder-group-toggle-text {
        line-height: 1;
    }

    .folder-group-toggle i,
    .folder-group-toggle svg {
        width: 0.9rem;
        height: 0.9rem;
        transition: transform 0.2s ease;
    }

    .folder-group-toggle[data-state="expanded"] i,
    .folder-group-toggle[data-state="expanded"] svg {
        transform: rotate(180deg);
    }

    .folder-tree {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 0.75rem;
        padding: 0.6rem;
        border: 1px solid #e2e8f0;
        max-height: none;
        overflow: visible;
    }

    .folder-tree[data-collapsed="true"] {
        display: none;
    }

    .folder-node {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.55rem;
        border-radius: 0.6rem;
        padding: 0.35rem 0.5rem;
        background: white;
        border: 1px solid transparent;
        color: #334155;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .folder-node:hover {
        border-color: rgba(79, 70, 229, 0.25);
        box-shadow: 0 6px 14px rgba(79, 70, 229, 0.08);
    }

    .folder-node.active {
        border-color: #4338ca;
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.16) 0%, rgba(99, 102, 241, 0.1) 100%);
        color: #1f2937;
        box-shadow: 0 10px 24px rgba(79, 70, 229, 0.15);
    }

    .folder-node-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: 0.5rem;
        background: rgba(79, 70, 229, 0.08);
        color: #4f46e5;
    }

    .folder-node-icon i {
        width: 0.9rem;
        height: 0.9rem;
    }

    .folder-node-label {
        flex: 1;
        text-align: left;
        font-size: 0.78rem;
    }

    .folder-node-count {
        background: rgba(15, 23, 42, 0.08);
        border-radius: 999px;
        padding: 0.18rem 0.45rem;
        font-size: 0.62rem;
        font-weight: 700;
        color: #0f172a;
    }

    .folder-workspace {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
        min-height: 320px;
    }

    .folder-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
        justify-content: space-between;
    }

    .folder-sort-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
    }

    .folder-sort-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.35rem 0.6rem;
        border-radius: 0.6rem;
        border: 1px solid #dbeafe;
        background: #eef2ff;
        color: #3730a3;
        font-size: 0.72rem;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .folder-sort-btn i {
        width: 0.9rem;
        height: 0.9rem;
    }

    .folder-sort-btn:hover {
        border-color: #4f46e5;
        color: #312e81;
    }

    .folder-sort-btn.active {
        background: linear-gradient(135deg, #4338ca 0%, #6366f1 100%);
        color: white;
        border-color: transparent;
        box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
    }

    .folder-status {
        font-size: 0.7rem;
        font-weight: 600;
        color: #475569;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .folder-status[data-state="pending"] {
        color: #b45309;
    }

    .folder-status[data-state="success"] {
        color: #047857;
    }

    .folder-status[data-state="error"] {
        color: #b91c1c;
    }

    .folder-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.75rem;
    }

    @media (max-width: 1500px) {
        .folder-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 1100px) {
        .folder-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .folder-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    .document-thumbnail {
        position: relative;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        padding: 0.55rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        min-height: 150px;
        transition: all 0.2s ease;
        cursor: grab;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
    }

    .document-thumbnail:active {
        cursor: grabbing;
    }

    .document-thumbnail:hover {
        border-color: #6366f1;
        box-shadow: 0 10px 24px rgba(99, 102, 241, 0.16);
        transform: translateY(-3px);
    }

    .document-thumbnail.active {
        border-color: #4338ca;
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(79, 70, 229, 0.04) 100%);
        box-shadow: 0 12px 26px rgba(79, 70, 229, 0.2);
    }

    .document-thumbnail.dragging {
        opacity: 0.75;
        border-style: dashed;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
    }

    .document-thumbnail.dropzone-hover {
        outline: 2px dashed #6366f1;
        outline-offset: 4px;
    }

    .document-thumbnail.dropzone-uploading {
        opacity: 0.75;
        position: relative;
    }

    .document-thumbnail.dropzone-uploading::after {
        content: 'Replacing…';
        position: absolute;
        inset: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, 0.65);
        color: #f1f5f9;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 0.75rem;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    .document-thumbnail.folder-hidden {
        display: none;
    }

    .folder-item-surface {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        flex: 1;
    }

    .folder-item-preview {
        position: relative;
        border-radius: 0.6rem;
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        min-height: 90px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .folder-extension-badge {
        position: absolute;
        top: 0.5rem;
        left: 0.5rem;
        background: rgba(15, 23, 42, 0.85);
        color: #f8fafc;
        padding: 0.18rem 0.5rem;
        border-radius: 999px;
        font-size: 0.58rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.18);
    }

    .folder-item-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .folder-icon {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.3rem;
        color: #4338ca;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.045em;
        font-size: 0.68rem;
    }

    .folder-icon i {
        width: 1.8rem;
        height: 1.8rem;
    }

    .image-fallback {
        display: none;
        font-size: 0.7rem;
        color: #475569;
    }

    .folder-drag-handle {
        position: absolute;
        top: 0.4rem;
        right: 0.4rem;
        border: none;
        background: rgba(15, 23, 42, 0.1);
        color: #0f172a;
        border-radius: 0.45rem;
        padding: 0.25rem;
        cursor: grab;
        transition: all 0.2s ease;
    }

    .folder-drag-handle:hover {
        background: rgba(79, 70, 229, 0.18);
        color: #312e81;
    }

    .folder-item-meta {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .folder-item-name {
        font-size: 0.76rem;
        font-weight: 700;
        color: #1f2937;
        line-height: 1.35;
    }

    .folder-item-reference {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        margin-top: 0.15rem;
    }

    .folder-item-reference-label {
        font-size: 0.62rem;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .folder-item-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }

    .folder-tag {
        background: rgba(15, 23, 42, 0.08);
        color: #0f172a;
        padding: 0.18rem 0.45rem;
        border-radius: 999px;
        font-size: 0.62rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    .folder-item-reference .folder-tag {
        font-size: 0.64rem;
        padding: 0.22rem 0.55rem;
    }

    .folder-tag-code {
        background: rgba(79, 70, 229, 0.12);
        color: #312e81;
        border: 1px solid rgba(79, 70, 229, 0.26);
    }

    .folder-tag-accent {
        background: rgba(59, 130, 246, 0.15);
        color: #1d4ed8;
    }

    .folder-tag-classification {
        background: rgba(16, 185, 129, 0.18);
        color: #047857;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-weight: 700;
    }

    .folder-tag-classification::before {
        content: '\2713';
        font-size: 0.65rem;
    }

    .page-status {
        position: absolute;
        right: 0.55rem;
        bottom: 0.55rem;
        width: 0.9rem;
        height: 0.9rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, 0.08);
        color: #64748b;
    }

    .page-status.completed {
        background: #10b981;
        color: white;
    }

    .folder-empty {
        border: 2px dashed #cbd5f5;
        border-radius: 0.9rem;
        padding: 2rem;
        text-align: center;
        color: #475569;
        background: rgba(238, 242, 255, 0.4);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.65rem;
    }

    .folder-empty i {
        width: 2.5rem;
        height: 2.5rem;
        color: #6366f1;
    }

    /* Multi-select & booklet adjustments */
    .page-select-checkbox {
        position: absolute;
        top: 0.5rem;
        left: 0.5rem;
        width: 16px;
        height: 16px;
        accent-color: #2563eb;
        z-index: 15;
        cursor: pointer;
    }

    .document-thumbnail.selected {
        border-color: #2563eb !important;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.18) 0%, rgba(59, 130, 246, 0.06) 100%) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25) !important;
    }

    .document-thumbnail.booklet-page {
        border-color: #d97706 !important;
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.18) 0%, rgba(251, 191, 36, 0.08) 100%) !important;
    }

    .document-thumbnail.booklet-page::before {
        content: 'B';
        position: absolute;
        top: 0.45rem;
        left: 0.45rem;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #d97706;
        color: white;
        font-size: 0.6rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 12;
    }

    @media (max-width: 1280px) {
        .folder-group-scroller {
            gap: 0.5rem;
        }

        .folder-tree {
            padding: 0.5rem;
        }

        .classification-card {
            max-height: none;
        }
    }

    @media (max-width: 768px) {
        .folder-body {
            padding: 1rem;
        }

        .folder-tree {
            flex-direction: column;
        }

        .folder-node {
            width: 100%;
        }
    }
    
    /* Classification Form Card */
    .classification-card {
        display: flex;
        flex-direction: column;
        max-height: 640px;
    }

    .form-container {
        flex: 1 1 auto;
        overflow-y: auto;
        min-height: 0;
    }

    .page-form {
        transition: all 0.3s ease;
    }

    .page-form.active {
        opacity: 1;
        transform: translateY(0);
    }

    .page-form.hidden {
        display: none;
    }
    
    /* Override existing form-group styles with enhanced version */
    .form-group {
        margin-bottom: 1.5rem;
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
    }
    
    .form-group:hover {
        border-color: #cbd5e0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    
    .form-label {
        display: flex;
        align-items: center;
        font-size: 0.875rem;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 0.75rem;
    }
    
    .required {
        color: #e53e3e;
        margin-left: 0.25rem;
    }
    
    .form-input, .form-select {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
    }
    
    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-help {
        margin-top: 0.5rem;
        font-size: 0.75rem;
        color: #718096;
        display: flex;
        align-items: center;
        line-height: 1.4;
    }
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-input.error, .form-select.error {
        border-color: #e53e3e;
        box-shadow: 0 0 0 3px rgba(229, 62, 62, 0.1);
    }
    
    .form-help {
        font-size: 0.75rem;
        color: #718096;
        margin-top: 0.25rem;
    }
    
    .form-footer {
        padding: 1.5rem;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }
    
    .btn-save {
        width: 100%;
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        padding: 1rem 1.5rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .btn-save:hover:not(:disabled) {
        background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
        transform: translateY(-1px);
        box-shadow: 0 8px 25px rgba(72, 187, 120, 0.3);
    }
    
    .btn-save:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 2rem;
    }
    
    .btn-back {
        background: white;
        border: 2px solid #e2e8f0;
        color: #4a5568;
        border-radius: 0.75rem;
        padding: 0.875rem 1.5rem;
        font-weight: 600;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }
    
    .btn-back:hover {
        border-color: #cbd5e0;
        background: #f7fafc;
        transform: translateY(-1px);
    }
    
    .status-text {
        color: #718096;
        font-size: 0.875rem;
    }
    
    /* Help Section */
    .help-card {
        background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%);
        border: 1px solid #90cdf4;
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .help-header {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .help-icon {
        color: #3182ce;
        font-size: 1.25rem;
        margin-top: 0.125rem;
    }
    
    .help-content h4 {
        font-size: 1rem;
        font-weight: 600;
        color: #2c5282;
        margin: 0 0 0.75rem 0;
    }
    
    .help-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .help-list li {
        color: #2c5282;
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
        padding-left: 1rem;
        position: relative;
    }
    
    .help-list li::before {
        content: '•';
        color: #3182ce;
        font-weight: bold;
        position: absolute;
        left: 0;
    }
    
    /* No Documents State */
    .no-documents {
        background: white;
        border-radius: 1rem;
        padding: 3rem;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
    }
    
    .no-documents-icon {
        font-size: 4rem;
        color: #cbd5e0;
        margin-bottom: 1.5rem;
    }
    
    .no-documents h3 {
        font-size: 1.5rem;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 1rem;
    }
    
    .no-documents p {
        color: #718096;
        margin-bottom: 2rem;
        font-size: 1.1rem;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 0.75rem;
        padding: 1rem 2rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
        transform: translateY(-1px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }
    
    /* Breadcrumb */
    .breadcrumb {
        background: white;
        border-radius: 1rem;
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }
    
    .breadcrumb-list {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    
    .breadcrumb-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .breadcrumb-link {
        color: #4a5568;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s ease;
    }
    
    .breadcrumb-link:hover {
        color: #667eea;
    }
    
    .breadcrumb-separator {
        color: #cbd5e0;
    }
    
    .breadcrumb-current {
        color: #718096;
        font-weight: 500;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .main-container {
            padding: 1rem;
        }
        
        .workflow-header {
            padding: 1.5rem;
        }
        
        .workflow-title {
            font-size: 1.5rem;
        }
        
        .thumbnails-grid {
            grid-template-columns: 1fr;
        }
        
        .nav-controls {
            gap: 0.5rem;
        }
        
        .action-buttons {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }
    }
    
    /* Loading States */
    .loading {
        opacity: 0.6;
        pointer-events: none;
    }
    
    .spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid transparent;
        border-top: 2px solid currentColor;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Page Status Indicators */
    .page-status {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        width: 1rem;
        height: 1rem;
        border-radius: 50%;
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .page-status.completed {
        background: #48bb78;
        color: white;
    }
    
    .page-status.in-progress {
        background: #ed8936;
        color: white;
    }

    /* Multi-Select and Booklet Management Styles */
    .multi-select-controls {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 1rem;
    }

    .booklet-controls {
        background: #fdf4ff;
        border: 1px solid #d946ef;
        border-radius: 0.5rem;
        padding: 1rem;
    }

    .btn-outline {
        background: white;
        border: 1px solid;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-outline:hover {
        background-color: rgba(0, 0, 0, 0.05);
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }

    .btn-xs {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }

    .page-code-preview-container {
        margin-top: 0.5rem;
    }

    .page-code-preview {
        margin-bottom: 0.5rem;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .bg-blue-500 {
        background-color: #3b82f6;
    }

    .text-white {
        color: white;
    }

    /* Form enhancements for new features */
    .page-type-others-container,
    .page-subtype-others-container {
        margin-top: 0.5rem;
    }

    /* Enhanced thumbnails for multi-select */
    .document-thumbnail.selected {
        border-color: #8b5cf6 !important;
        background-color: #f3f4f6;
        transform: scale(1.02);
    }

    .document-thumbnail.booklet-page {
        border-color: #d946ef !important;
        background-color: #fdf4ff;
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .multi-select-controls,
        .booklet-controls {
            padding: 0.75rem;
        }
        
        .btn-outline {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }
        
        .page-code-preview .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    }

    /* Advanced Controls Section */
    .advanced-controls-section {
        margin-bottom: 1.5rem;
    }

    .control-card {
        background: white;
        border-radius: 0.75rem;
        padding: 0.85rem 0.9rem;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .control-card--compact {
        padding: 0.7rem 0.75rem;
    }

    .control-card:hover {
        box-shadow: 0 6px 24px rgba(0, 0, 0, 0.12);
        transform: translateY(-1px);
    }

    .control-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.55rem;
        gap: 0.45rem;
    }

    .control-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: #2d3748;
        margin: 0;
    }

    .control-description {
        color: #64748b;
        font-size: 0.74rem;
        line-height: 1.35;
        margin: 0;
    }

    .btn-control {
        background: white;
        border: 1.5px solid #e2e8f0;
        color: #4a5568;
        padding: 0.32rem 0.75rem;
        border-radius: 0.4rem;
        font-size: 0.75rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        min-width: 80px;
        justify-content: center;
    }

    .btn-control:hover {
        border-color: #cbd5e0;
        background: #f7fafc;
    }

    .btn-control[data-active="true"] {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
        color: white;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-control[data-active="true"]:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
    }

    .btn-xs {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        border: 1px solid #e2e8f0;
        background: white;
        color: #4a5568;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-xs:hover {
        background: #f7fafc;
        border-color: #cbd5e0;
    }

    .multi-select-active,
    .booklet-active {
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Selected page thumbnail styling */
    .document-thumbnail.selected {
        border: 3px solid #6366f1;
        background: #eff6ff;
        transform: scale(1.05);
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
    }

    .document-thumbnail.selected::after {
        content: '✓';
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: #6366f1;
        color: white;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: bold;
        z-index: 10;
    }

    /* Booklet page styling */
    .document-thumbnail.booklet-page {
        border: 3px solid #9333ea;
        background: #fdf4ff;
    }

    .document-thumbnail.booklet-page::after {
        content: '📖';
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: #9333ea;
        color: white;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        z-index: 10;
    }

    /* Multi-select checkboxes for pages */
    .page-select-checkbox {
        position: absolute;
        top: 0.5rem;
        left: 0.5rem;
        width: 1.25rem;
        height: 1.25rem;
        cursor: pointer;
        z-index: 10;
    }

    .multi-select-only {
        display: none;
    }

    .multi-select-mode .multi-select-only {
        display: block;
        margin-bottom: 1rem;
    }
</style>