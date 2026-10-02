document.addEventListener('DOMContentLoaded', function() {
    // Create modal container if it doesn't exist already
    if (!document.getElementById('modal-container')) {
        const modalContainer = document.createElement('div');
        modalContainer.id = 'modal-container';
        modalContainer.className = 'fixed inset-0 z-50 overflow-y-auto hidden';
        modalContainer.innerHTML = `
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-overlay" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div id="modal-content" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="p-6 flex justify-center items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p>Loading...</p>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalContainer);
    }

    // Loading spinner HTML for resetting modal content while fetching
    var loadingSpinnerHTML = `
        <div class="p-6 flex justify-center items-center">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p>Loading...</p>
        </div>
    `;

    /**
     * Execute <script> tags inside dynamically injected HTML (innerHTML does not run them).
     * Clones each script and appends it so the browser evaluates it.
     */
    function executeScripts(container) {
        var scripts = container.querySelectorAll('script');
        scripts.forEach(function(oldScript) {
            var newScript = document.createElement('script');
            // Copy attributes (src, type, etc.)
            Array.from(oldScript.attributes).forEach(function(attr) {
                newScript.setAttribute(attr.name, attr.value);
            });
            // Copy inline script content
            newScript.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    /**
     * Open the Tailwind modal, fetch content from a URL, and inject it.
     * Used by event delegation below and can be called externally via window.openTailwindModal().
     */
    /**
     * Apply one of the named widths to the modal shell. Kept separate so the size list lives
     * in one place: openModal and any later caller cannot disagree about what 'xxl' means.
     */
    function setModalSize(modalContent, size) {
        var SIZES = {
            sm:  ['sm:max-w-sm'],
            md:  ['sm:max-w-lg'],
            lg:  ['sm:max-w-2xl'],
            xl:  ['sm:max-w-4xl'],
            xxl: ['sm:max-w-7xl', 'w-[95vw]']
        };

        Object.keys(SIZES).forEach(function (key) {
            modalContent.classList.remove.apply(modalContent.classList, SIZES[key]);
        });

        var chosen = SIZES[size] || SIZES.md;
        modalContent.classList.add.apply(modalContent.classList, chosen);
    }

    function openModal(url, size) {
        var modalContainer = document.getElementById('modal-container');
        var modalContent = document.getElementById('modal-content');

        if (!modalContainer || !modalContent) return;

        // Reset to loading state
        modalContent.innerHTML = loadingSpinnerHTML;

        // Show modal container
        modalContainer.classList.remove('hidden');

        // Set modal size.
        //
        // 'xxl' exists for forms that are genuinely wide rather than merely long — the user
        // create/edit modal carries a module x action permission grid seven columns across,
        // which at the old 'lg' (max-w-2xl, 672px) wrapped every module name onto three lines.
        // max-w-7xl is 1280px; the w-[95vw] keeps it from overflowing a laptop screen.
        setModalSize(modalContent, size);

        // Fetch modal content
        fetch(url)
            .then(function(response) { return response.text(); })
            .then(function(html) {
                modalContent.innerHTML = html;

                // Run the fragment's own <script> tags FIRST. innerHTML never executes them,
                // and the order matters: a fragment that declares its Alpine component inline
                //
                //     <div x-data="permissionGrid(...)">  ...  <script>function permissionGrid(){}</script>
                //
                // is dead if Alpine initialises before the script runs — Alpine evaluates the
                // x-data expression, finds no such function, throws, and the component silently
                // never mounts. That is exactly what happened to the user modal's permission
                // grid: headers rendered, every row and counter stayed blank.
                executeScripts(modalContent);

                // Then initialise Alpine on the injected content: x-data, x-show and @click
                // do nothing without this, because Alpine only auto-scans on page load.
                //
                // Only TOP-LEVEL x-data elements are initialised. Alpine 3's initTree walks
                // the whole subtree, so calling it on a parent already mounts every nested
                // component; calling it again on the child mounts that subtree a second time
                // and its effects and watchers run twice. The user modal nests the permission
                // grid inside the form's own x-data, so this is not hypothetical here.
                if (window.Alpine) {
                    modalContent.querySelectorAll('[x-data]').forEach(function(el) {
                        if (el.parentElement && el.parentElement.closest('[x-data]')) {
                            return; // a nested component; its ancestor initialises it
                        }
                        try { Alpine.initTree(el); } catch (err) { /* already initialized */ }
                    });
                }

                // Initialize Lucide icons for dynamic content
                if (window.lucide && lucide.createIcons) {
                    lucide.createIcons();
                }

                // Bind close buttons inside the loaded content
                modalContent.querySelectorAll('[data-dismiss="modal"]').forEach(function(btn) {
                    btn.addEventListener('click', closeModal);
                });
            })
            .catch(function(error) {
                console.error('Error loading modal content:', error);
                modalContent.innerHTML = '<div class="p-6">' +
                    '<div class="text-red-500">Error loading content. Please try again.</div>' +
                    '</div>' +
                    '<div class="px-6 py-3 bg-gray-50 text-right">' +
                    '<button type="button" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" data-dismiss="modal">Close</button>' +
                    '</div>';

                modalContent.querySelectorAll('[data-dismiss="modal"]').forEach(function(btn) {
                    btn.addEventListener('click', closeModal);
                });
            });
    }

    // Expose openModal globally so other scripts can call it
    window.openTailwindModal = openModal;

    // ─── Event-delegated click handler for .customModal buttons ───
    // Uses delegation so dynamically created buttons (e.g. DataTable re-renders) also work.
    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('.customModal');
        if (!trigger) return;

        e.preventDefault();

        var url   = trigger.getAttribute('data-url');
        var size  = trigger.getAttribute('data-size') || 'md';

        if (!url) return;

        openModal(url, size);
    });

    // Close modal function
    function closeModal() {
        var modalContainer = document.getElementById('modal-container');
        if (modalContainer) {
            modalContainer.classList.add('hidden');
        }
    }

    // Expose closeModal globally
    window.closeTailwindModal = closeModal;

    // Close modal on backdrop click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('bg-overlay')) {
            closeModal();
        }
    });

    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
});
