<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: {
                        DEFAULT: '#3b82f6',
                        foreground: '#ffffff'
                    },
                    muted: {
                        DEFAULT: '#f3f4f6',
                        foreground: '#6b7280'
                    },
                    border: '#e5e7eb',
                    ring: '#3b82f6',
                    success: '#10b981',
                    warning: '#f59e0b',
                    destructive: '#ef4444',
                    secondary: {
                        DEFAULT: '#f1f5f9',
                        foreground: '#0f172a'
                    }
                },
                width: {
                    '180': '180px',
                    '300': '300px'
                },
                inset: {
                    '2.5': '0.625rem'
                }
            }
        }
    }
</script>
<style>
  
    .modal {
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    .modal.show {
        opacity: 1;
        visibility: visible;
    }
    .modal-content {
        transform: scale(0.9);
        transition: transform 0.3s ease;
    }
    .modal.show .modal-content {
        transform: scale(1);
    }
    
    /* Custom select arrow */
    .custom-select {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
    }
</style>