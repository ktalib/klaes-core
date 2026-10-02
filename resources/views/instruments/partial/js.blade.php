<script>
    // Initialise lucide icons
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });

    // ── Modal Elements (used on pages that include this partial with modals) ──
    const instrumentTypeModal = document.getElementById('instrument-type-modal');
    const closeModalBtn       = document.getElementById('close-modal-btn');
    const cancelBtn           = document.getElementById('cancel-btn');
    const continueBtn         = document.getElementById('continue-btn');
    const instrumentOptions   = document.querySelectorAll('.instrument-option');

    const poaFormModal    = document.getElementById('poa-form-modal');
    const closePoaFormBtn = document.getElementById('close-poa-form-btn');
    const cancelFormBtn   = document.getElementById('cancel-form-btn');
    const poaForm         = document.getElementById('poa-form');
    const generateParticularsBtn = document.getElementById('generate-particulars-btn');

    let selectedInstrumentType = null;

    // ── Modal event listeners (guard against null) ───────────────────────────
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            instrumentTypeModal.classList.add('hidden');
            resetModalSelection();
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            instrumentTypeModal.classList.add('hidden');
            resetModalSelection();
        });
    }

    if (instrumentTypeModal) {
        instrumentTypeModal.addEventListener('click', (e) => {
            if (e.target === instrumentTypeModal) {
                instrumentTypeModal.classList.add('hidden');
                resetModalSelection();
            }
        });
    }

    instrumentOptions.forEach(option => {
        option.addEventListener('click', () => {
            instrumentOptions.forEach(opt => {
                opt.classList.remove('bg-blue-50', 'border-blue-300');
            });
            option.classList.add('bg-blue-50', 'border-blue-300');
            selectedInstrumentType = option.querySelector('h3')?.textContent;

            if (continueBtn) {
                continueBtn.classList.remove('bg-gray-500', 'hover:bg-gray-600');
                continueBtn.classList.add('bg-black', 'hover:bg-black/90');
                continueBtn.disabled = false;
            }
        });
    });

    if (continueBtn) {
        continueBtn.addEventListener('click', () => {
            if (selectedInstrumentType) {
                instrumentTypeModal.classList.add('hidden');
                if (
                    selectedInstrumentType === 'Power of Attorney' ||
                    selectedInstrumentType === 'Irrevocable Power of Attorney'
                ) {
                    if (poaFormModal) {
                        poaFormModal.classList.remove('hidden');
                        const formTitle = document.querySelector('#poa-form-modal h2');
                        if (formTitle) formTitle.textContent = `Register ${selectedInstrumentType}`;
                    }
                } else {
                    alert(`You selected: ${selectedInstrumentType}. This will proceed to the instrument details form.`);
                }
                resetModalSelection();
            }
        });
    }

    // ── Generate Particulars ─────────────────────────────────────────────────
    if (generateParticularsBtn) {
        generateParticularsBtn.addEventListener('click', async function() {
            try {
                generateParticularsBtn.disabled = true;
                generateParticularsBtn.innerHTML = 'Generating...';

                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                const response = await fetch('{{ url("api/instruments/generate-particulars") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({})
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Request failed with status ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    document.getElementById('rootRegistrationNumber').value = data.rootRegistrationNumber;

                    const getOrCreate = (id, name) => {
                        let el = document.getElementById(id) || document.createElement('input');
                        el.type = 'hidden'; el.id = id; el.name = name;
                        return el;
                    };

                    const serialNoInput = getOrCreate('serial_no', 'serial_no');
                    serialNoInput.value = data.serial_no;
                    const pageNoInput = getOrCreate('page_no', 'page_no');
                    pageNoInput.value = data.page_no;
                    const volumeNoInput = getOrCreate('volume_no', 'volume_no');
                    volumeNoInput.value = data.volume_no;

                    const form = document.getElementById('poa-form');
                    if (form) {
                        if (!document.getElementById('serial_no')) form.appendChild(serialNoInput);
                        if (!document.getElementById('page_no'))   form.appendChild(pageNoInput);
                        if (!document.getElementById('volume_no')) form.appendChild(volumeNoInput);
                    }

                    generateParticularsBtn.innerHTML = 'Number Generated';
                    generateParticularsBtn.disabled = true;
                    generateParticularsBtn.classList.add('bg-gray-500', 'hover:bg-gray-500');
                    generateParticularsBtn.classList.remove('bg-black', 'hover:bg-black/90');
                } else {
                    alert(data.message || 'Failed to generate root particulars registration number');
                    generateParticularsBtn.disabled = false;
                    generateParticularsBtn.innerHTML = 'Generate Number';
                }
            } catch (error) {
                console.error('Error generating root particulars registration number:', error);
                alert('An error occurred: ' + error.message);
                generateParticularsBtn.disabled = false;
                generateParticularsBtn.innerHTML = 'Generate Number';
            }
        });
    }

    // ── POA Form modal ───────────────────────────────────────────────────────
    if (closePoaFormBtn) {
        closePoaFormBtn.addEventListener('click', () => poaFormModal.classList.add('hidden'));
    }
    if (cancelFormBtn) {
        cancelFormBtn.addEventListener('click', () => poaFormModal.classList.add('hidden'));
    }
    if (poaFormModal) {
        poaFormModal.addEventListener('click', (e) => {
            if (e.target === poaFormModal) poaFormModal.classList.add('hidden');
        });
    }

    if (poaForm) {
        poaForm.addEventListener('submit', function(e) {
            const rootRegistrationNumber = document.getElementById('rootRegistrationNumber')?.value;
            if (rootRegistrationNumber === '0/0/0') {
                e.preventDefault();
                alert('Please generate a registration number before submitting the form.');
                return false;
            }
            if (typeof updateFormFileData === 'function') updateFormFileData();
            return true;
        });
    }

    function resetModalSelection() {
        selectedInstrumentType = null;
        instrumentOptions.forEach(opt => opt.classList.remove('bg-blue-50', 'border-blue-300'));
        if (continueBtn) {
            continueBtn.classList.remove('bg-black', 'hover:bg-black/90');
            continueBtn.classList.add('bg-gray-500', 'hover:bg-gray-600');
            continueBtn.disabled = true;
        }
    }
</script>