document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function closeModal() {
        // Tailwind modal (primary)
        const modalContainer = document.getElementById('modal-container');
        if (modalContainer) {
            modalContainer.classList.add('hidden');
        }
        // Bootstrap fallback
        if (window.$ && $('#customModal').length) {
            $('#customModal').modal('hide');
        }
    }

    function handleFormSubmit(event) {
        const form = event.target;
        if (!form.classList.contains('js-for-information-form')) return;

        event.preventDefault();

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
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to save record.');
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
                if (window.Swal) {
                    Swal.fire('Error', error.message || 'Request failed', 'error');
                } else {
                    alert(error.message || 'Request failed');
                }
            });
    }

    function handleDeleteClick(event) {
        const button = event.target.closest('.js-delete-for-information');
        if (!button) return;

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
    // Uses event delegation so it works even though the modal form is loaded via AJAX innerHTML
    document.addEventListener('click', function (event) {
        // Select button — open GlobalFileNoModal
        if (event.target.closest('#fi-select-fileno')) {
            event.preventDefault();
            if (!window.GlobalFileNoModal || typeof window.GlobalFileNoModal.open !== 'function') {
                if (window.Swal) {
                    Swal.fire('Error', 'File number selector is not available. Please refresh the page.', 'error');
                } else {
                    alert('File number selector is not available. Please refresh the page.');
                }
                return;
            }

            window.GlobalFileNoModal.open({
                autoPopulateGenericFields: false,
                callback: function (data) {
                    var fileNumber = data.fileNumber || '';
                    var hiddenInput = document.getElementById('fi-file-number');
                    var displayInput = document.getElementById('fi-file-number-display');
                    if (hiddenInput) hiddenInput.value = fileNumber;
                    if (displayInput) displayInput.value = fileNumber;

                    // Auto-fill Title from the record (only if title is currently empty)
                    var titleInput = document.getElementById('fi-title');
                    if (titleInput && !titleInput.value.trim()) {
                        var title = '';
                        if (data.record) {
                            title = data.record.file_name || data.record.FileName || data.record.applicant_name || '';
                        }
                        if (title) {
                            titleInput.value = title;
                            titleInput.setAttribute('readonly', true);
                            titleInput.classList.add('ring-2', 'ring-green-300', 'border-green-400');
                            setTimeout(function () {
                                titleInput.classList.remove('ring-2', 'ring-green-300', 'border-green-400');
                            }, 1500);
                        }
                    }
                }
            });
            return;
        }

        // Clear button — reset file number
        if (event.target.closest('#fi-clear-fileno')) {
            event.preventDefault();
            var hiddenInput = document.getElementById('fi-file-number');
            var displayInput = document.getElementById('fi-file-number-display');
            var titleInput = document.getElementById('fi-title');
            if (hiddenInput) hiddenInput.value = '';
            if (displayInput) displayInput.value = '';
            if (titleInput) {
                titleInput.value = '';
                titleInput.removeAttribute('readonly');
            }
            return;
        }
    });
});
