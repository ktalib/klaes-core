  <style>
    .file-preview {
      max-height: 400px;
      overflow-y: auto;
    }
    .batch-folder {
      transition: all 0.2s ease;
    }
    .batch-folder:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    /* Thumbnail hover effects */
    .file-card:hover .selection-indicator,
    .file-card:hover .remove-file-btn {
      opacity: 1 !important;
    }
    
    .file-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    /* Selection indicator hover */
    .selection-indicator:hover {
      transform: scale(1.1);
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    /* Remove button hover */
    .remove-file-btn:hover {
      transform: scale(1.1);
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }
    
    /* Selected state styling */
    .file-card.selected {
      background: rgba(34, 197, 94, 0.05);
      border-color: #22c55e;
    }
    
    /* Thumbnail image styling */
    .thumbnail-image {
      transition: transform 0.2s ease;
    }
    
    .file-card:hover .thumbnail-image {
      transform: scale(1.05);
    }
    
    /* Preview area cursor */
    .file-preview-area {
      cursor: pointer;
    }
  </style>