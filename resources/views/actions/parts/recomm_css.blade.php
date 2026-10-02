<style>
    .tab-content {
        display: none;
        opacity: 0;
        transform: translateY(10px);
        transition: all 0.3s ease;
    }

    .tab-content.active {
        display: block;
        opacity: 1;
        transform: translateY(0);
        animation: fadeInUp 0.4s ease-out;
    }

    /* Content area color accents */
    .tab-content.active .bg-white.border.border-gray-200 {
        border-left: 4px solid transparent;
        transition: border-color 0.3s ease;
    }

    /* Color-coded content borders */
    #documents-tab.active .bg-white.border.border-gray-200 {
        border-left-color: #3b82f6;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
    }

    #planning-form-tab.active .bg-white.border.border-gray-200 {
        border-left-color: #7c3aed;
        box-shadow: 0 2px 8px rgba(124, 58, 237, 0.1);
    }

    #initial-tab.active .bg-white.border.border-gray-200 {
        border-left-color: #10b981;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
    }

    #final-tab.active .bg-white.border.border-gray-200 {
        border-left-color: #f59e0b;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);
    }

    #buyers-list-tab.active .bg-white.border.border-gray-200 {
        border-left-color: #6366f1;
        box-shadow: 0 2px 8px rgba(99, 102, 241, 0.12);
    }

    /* Fade in animation */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Tab indicator dots */
    .tab-button::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 50%;
        transform: translateX(-50%);
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: transparent;
        transition: all 0.3s ease;
    }

    .tab-button.active::after {
        background-color: currentColor;
        box-shadow: 0 0 8px currentColor;
    }

    /* Tab container styling */
    .grid.grid-cols-3.gap-2.mb-4 {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        padding: 8px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* Active tab content header styling */
    .tab-content.active .p-4.border-b h3 {
        position: relative;
        padding-left: 12px;
    }

    .tab-content.active .p-4.border-b h3::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 20px;
        border-radius: 2px;
        transition: background-color 0.3s ease;
    }

    #documents-tab.active .p-4.border-b h3::before {
        background-color: #3b82f6;
    }

    #planning-form-tab.active .p-4.border-b h3::before {
        background-color: #7c3aed;
    }

    #initial-tab.active .p-4.border-b h3::before {
        background-color: #10b981;
    }

    #final-tab.active .p-4.border-b h3::before {
        background-color: #f59e0b;
    }

    #buyers-list-tab.active .p-4.border-b h3::before {
        background-color: #6366f1;
    }

    /* Smooth transitions for all interactive elements */
    .tab-button, .tab-content, .bg-white.border.border-gray-200 {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Mobile responsiveness for tabs */
    @media (max-width: 768px) {
        .grid.grid-cols-3.gap-2.mb-4 {
            grid-template-columns: 1fr;
            gap: 4px;
        }
        
        .tab-button {
            font-size: 0.875rem;
            padding: 0.75rem 1rem;
        }
        
        .tab-button::after {
            display: none;
        }
    }

    .tab-button {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        padding: 0.5rem 1rem;
        border-radius: 0.25rem;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        background-color: #f8fafc;
        color: #64748b;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .tab-button.active {
        font-weight: 600;
        color: white;
        border: 2px solid transparent;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Color differentiation for each tab */
    .tab-button[data-tab="documents"].active {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        border-color: #1e40af;
    }

    .tab-button[data-tab="planning-form"].active {
        background: linear-gradient(135deg, #7c3aed, #5b21b6);
        border-color: #4c1d95;
    }

    .tab-button[data-tab="initial"].active {
        background: linear-gradient(135deg, #10b981, #047857);
        border-color: #065f46;
    }

    .tab-button[data-tab="final"].active {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        border-color: #b45309;
    }

    .tab-button[data-tab="buyers-list"].active {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        border-color: #4338ca;
    }

    /* Hover effects with color preview */
    .tab-button[data-tab="documents"]:hover:not(.active) {
        background-color: #dbeafe;
        color: #1d4ed8;
        border-color: #3b82f6;
    }

    .tab-button[data-tab="planning-form"]:hover:not(.active) {
        background-color: #e4d4f4;
        color: #5b21b6;
        border-color: #7c3aed;
    }

    .tab-button[data-tab="initial"]:hover:not(.active) {
        background-color: #d1fae5;
        color: #047857;
        border-color: #10b981;
    }

    .tab-button[data-tab="final"]:hover:not(.active) {
        background-color: #fef3c7;
        color: #d97706;
        border-color: #f59e0b;
    }

    .tab-button[data-tab="buyers-list"]:hover:not(.active) {
        background-color: #e0e7ff;
        color: #4338ca;
        border-color: #6366f1;
    }

    .tab-button:hover:not(.active) {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
    }

    @media print {
        body * {
            visibility: hidden;
        }

        #final-tab,
        #final-tab * {
            visibility: visible;
        }

        #final-tab {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }

        .no-print,
        button,
        .tab-button,
        footer,
        nav {
            display: none !important;
        }
    }
</style>