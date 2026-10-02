document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function setFormSubmitting(form, isSubmitting) {
        const modalRoot = form.closest('[data-survey-report-modal]');
        if (!modalRoot || typeof window.Alpine === 'undefined' || typeof window.Alpine.$data !== 'function') {
            return;
        }

        const component = window.Alpine.$data(modalRoot);
        if (component && typeof component.submitting !== 'undefined') {
            component.submitting = isSubmitting;
        }
    }

    function closeModal() {
        // Use the Tailwind modal close function if available
        if (window.closeTailwindModal) {
            window.closeTailwindModal();
        } else {
            var modalContainer = document.getElementById('modal-container');
            if (modalContainer) {
                modalContainer.classList.add('hidden');
            }
        }
    }

    function handleFormSubmit(event) {
        const form = event.target;
        if (!form.classList.contains('js-survey-report-form')) return;

        if (event.defaultPrevented) {
            setFormSubmitting(form, false);
            return;
        }

        event.preventDefault();

        if (typeof form.__clearSurveyReportErrors === 'function') {
            form.__clearSurveyReportErrors();
        }

        setFormSubmitting(form, true);

        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: formData
        })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    const error = new Error(data.message || 'Unable to save record.');
                    error.response = data;
                    error.status = response.status;
                    throw error;
                }
                return data;
            })
            .then((data) => {
                if (window.Swal) {
                    Swal.fire('Success', data.message, 'success');
                }
                closeModal();
                window.location.reload();
            })
            .catch((error) => {
                setFormSubmitting(form, false);

                const serverErrors = error.response?.errors;
                if (serverErrors && typeof form.__applySurveyReportServerErrors === 'function') {
                    form.__applySurveyReportServerErrors(serverErrors);
                }

                const firstServerError = serverErrors
                    ? Object.values(serverErrors).flat().find(Boolean)
                    : null;
                const message = firstServerError || error.message || 'Request failed';

                if (window.Swal) {
                    Swal.fire('Error', message, 'error');
                } else {
                    alert(message);
                }
            });
    }

    function handleDeleteClick(event) {
        const button = event.target.closest('.js-delete-survey-report');
        if (!button) return;
        if (button.disabled || button.getAttribute('aria-disabled') === 'true') return;

        event.preventDefault();
        const url = button.dataset.deleteUrl;
        if (!url) return;

        const executeDelete = () => {
            fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            })
                .then(async (response) => {
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Unable to delete record.');
                    }
                    return data;
                })
                .then((data) => {
                    if (window.Swal) {
                        Swal.fire('Deleted', data.message, 'success');
                    }
                    window.location.reload();
                })
                .catch((error) => {
                    if (window.Swal) {
                        Swal.fire('Error', error.message || 'Request failed', 'error');
                    } else {
                        alert(error.message || 'Request failed');
                    }
                });
        };

        if (window.Swal) {
            Swal.fire({
                title: 'Delete record?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it'
            }).then((result) => {
                if (result.isConfirmed) executeDelete();
            });
        } else if (confirm('Delete this record?')) {
            executeDelete();
        }
    }

    function toggleDropdown(event) {
        const button = event.target.closest('.actions-dropdown-btn');
        if (!button) return;

        event.preventDefault();
        const dropdownId = button.getAttribute('data-dropdown-id');
        if (!dropdownId) return;

        document.querySelectorAll('.actions-dropdown-menu').forEach((menu) => {
            if (menu.getAttribute('data-dropdown-menu') !== dropdownId) {
                menu.classList.add('hidden');
            }
        });

        const menu = document.querySelector(`[data-dropdown-menu="${dropdownId}"]`);
        if (menu) {
            menu.classList.toggle('hidden');
        }
    }

    function closeDropdowns(event) {
        if (event.target.closest('.actions-dropdown-btn') || event.target.closest('.actions-dropdown-menu')) {
            return;
        }
        document.querySelectorAll('.actions-dropdown-menu').forEach((menu) => menu.classList.add('hidden'));
    }

    document.addEventListener('submit', handleFormSubmit);
    document.addEventListener('click', handleDeleteClick);
    document.addEventListener('click', toggleDropdown);
    document.addEventListener('click', closeDropdowns);

    // ── File Number Selector (Global File Number Modal) ──
    // Uses event delegation to ensure it works with AJAX-loaded modal content
    document.addEventListener('click', function (event) {
        const selectBtn = event.target.closest('#sr-select-fileno');
        if (selectBtn) {
            event.preventDefault();

            // Try to initialise if the object exists but wasn't initialised yet
            if (window.GlobalFileNoModal && typeof window.GlobalFileNoModal.init === 'function' && !window.GlobalFileNoModal._initialized) {
                try {
                    window.GlobalFileNoModal.init();
                    window.GlobalFileNoModal._initialized = true;
                } catch (e) {
                    console.warn('GlobalFileNoModal late-init failed:', e);
                }
            }

            if (!window.GlobalFileNoModal || typeof window.GlobalFileNoModal.open !== 'function') {
                console.error('GlobalFileNoModal not available. jQuery:', typeof jQuery, '| Modal DOM:', document.getElementById('global-fileno-modal') ? 'found' : 'missing');
                if (window.Swal) {
                    Swal.fire('Error', 'File number selector is not available. Please refresh the page and try again.', 'error');
                } else {
                    alert('File number selector is not available. Please refresh the page and try again.');
                }
                return;
            }

            window.GlobalFileNoModal.open({
                autoPopulateGenericFields: false,
                callback: function (data) {
                    const fileNumber = data.fileNumber || '';
                    const numberInput = document.getElementById('sr-file-number');
                    const titleInput = document.getElementById('sr-file-title');

                    if (numberInput) numberInput.value = fileNumber;

                    if (titleInput && data.record) {
                        const title = data.record.file_name || data.record.FileName || data.record.applicant_name || '';
                        if (title) titleInput.value = title;
                    }
                }
            });
        }
    });

    // ── File Number Selector for Lands Step 1 ──
    // Uses data-lands-select-fileno button, syncs to both Step 1 display and Step 2 hidden fields
    document.addEventListener('click', function (event) {
        const selectBtn = event.target.closest('[data-lands-select-fileno]');
        if (!selectBtn) return;
        event.preventDefault();

        if (window.GlobalFileNoModal && typeof window.GlobalFileNoModal.init === 'function' && !window.GlobalFileNoModal._initialized) {
            try {
                window.GlobalFileNoModal.init();
                window.GlobalFileNoModal._initialized = true;
            } catch (e) {
                console.warn('GlobalFileNoModal late-init failed:', e);
            }
        }

        if (!window.GlobalFileNoModal || typeof window.GlobalFileNoModal.open !== 'function') {
            if (window.Swal) {
                Swal.fire('Error', 'File number selector is not available. Please refresh the page and try again.', 'error');
            } else {
                alert('File number selector is not available. Please refresh the page and try again.');
            }
            return;
        }

        window.GlobalFileNoModal.open({
            autoPopulateGenericFields: false,
            callback: function (data) {
                const fileNumber = data.fileNumber || '';
                const title = data.record ? (data.record.file_name || data.record.FileName || data.record.applicant_name || '') : '';

                // Update Step 1 display fields
                const landsFileNo = document.querySelector('[data-lands-file-number]');
                const landsFileTitle = document.querySelector('[data-lands-file-title]');
                if (landsFileNo) {
                    landsFileNo.value = fileNumber;
                    landsFileNo.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (landsFileTitle && title) landsFileTitle.value = title;

                // Sync to Step 2 hidden name fields (the actual form fields)
                const numberInput = document.getElementById('sr-file-number');
                const titleInput = document.getElementById('sr-file-title');
                if (numberInput) numberInput.value = fileNumber;
                if (titleInput && title) titleInput.value = title;
            }
        });
    });
});
