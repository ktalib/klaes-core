document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('land-recommendation-form');
    const pickBtn = document.getElementById('select-fileno-btn');
    const fileNoInput = document.getElementById('file_number');
    const applicantInput = document.getElementById('applicant_name');
    const locationInput = document.getElementById('location');
    const lgaInput = document.getElementById('lga');
    const plotInput = document.getElementById('plot_number');
    const layoutPlanInput = document.getElementById('layout_plan_no');
    const trackingInput = document.getElementById('tracking_id');
    const submitBtn = form?.querySelector('button[type="submit"]');

    // Location auto-builder
    const streetInput    = document.getElementById('street_name');
    const districtInput  = document.getElementById('district');
    const stateInput     = document.getElementById('state');

    function buildLocation() {
        // street, district, lga are now hidden inputs — read .value directly
        const street   = (streetInput?.value || '').trim();
        const district = (districtInput?.value || '').trim();
        const lga      = (lgaInput?.value || '').trim();
        // state is fixed — read from the hidden input (stateInput points to the disabled display input)
        const stateHidden = document.querySelector('input[type="hidden"][name="state"]');
        const state    = ((stateHidden?.value || stateInput?.value || '')).trim();

        // House No / Plot No are shown in their own fields elsewhere on the form
        // and on print templates, so they're deliberately excluded here.
        // District and LGA legitimately share the same name for some Kano
        // locations (e.g. "Dawakin Kudu" is both the LGA and its district),
        // so skip a part when it repeats the immediately preceding one to
        // avoid a duplicated-looking location like "... Dawakin Kudu Dawakin Kudu ...".
        const rawParts = [street, district, lga, state];
        const parts = [];
        let lastLower = null;
        rawParts.forEach(function (val) {
            val = (val || '').trim();
            if (!val) return;
            if (val.toLowerCase() === lastLower) return;
            parts.push(val);
            lastLower = val.toLowerCase();
        });
        if (locationInput) locationInput.value = parts.join(' ');
    }

    // Expose globally so Select2 change handlers in the blade script can call it
    window._buildLocation = buildLocation;

    // lga/street/district are hidden inputs updated by Select2 — no input listener needed
    [stateInput].forEach(el => {
        if (el) el.addEventListener('input', buildLocation);
    });

    // Declare dropdowns early so all callbacks can reference them
    const landUseSelect = document.getElementById('land_use_id');
    const purposeSelect = document.getElementById('purpose_id');
    const landUseText = document.getElementById('land_use_text');
    const purposeText = document.getElementById('purpose_of_clause_text');

    // Recommendation Type Toggle
    const typeRadios = document.querySelectorAll('input[name="type"]');
    const conversionSection = document.getElementById('conversion-fields-section');

    typeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'Conversion') {
                conversionSection?.classList.remove('hidden');
            } else {
                conversionSection?.classList.add('hidden');
            }
        });
    });

    // Derive land use keyword from file number prefix (e.g. RES-2026-1 → "RESIDENTIAL")
    function landUseFromFileNo(fileNo) {
        const fn = (fileNo || '').toUpperCase().trim();
        if (/^RES[-\/]/.test(fn) || fn.startsWith('RES')) return 'RESIDENTIAL';
        if (/^AG[-\/]/.test(fn) || fn.startsWith('AGR'))  return 'AGRICULTURAL';
        if (/^COM[-\/]/.test(fn) || fn.startsWith('COM'))  return 'COMMERCIAL';
        if (/^IND[-\/]/.test(fn) || fn.startsWith('IND'))  return 'INDUSTRIAL';
        if (/^MIX[-\/]/.test(fn) || fn.startsWith('MIX'))  return 'MIXED';
        return null;
    }

    // Match land use from file record into the select dropdown
    function applyLandUse(record) {
        if (!landUseSelect) return;

        // Try by ID first (most reliable)
        const recordId = record.land_use_id || record.LandUseID || record.landuse_id;
        if (recordId) {
            const optById = Array.from(landUseSelect.options).find(o => String(o.value) === String(recordId));
            if (optById) {
                landUseSelect.value = optById.value;
                landUseSelect.dispatchEvent(new Event('change'));
                return;
            }
        }

        // Try by name from record — exact, then includes both directions
        const recordLU = (record.land_use || record.LandUse || record.ma_land_use || '').trim().toLowerCase();

        // Also try deriving from the file number prefix (most reliable fallback)
        const currentFileNo = fileNoInput ? fileNoInput.value : '';
        const prefixLU = (landUseFromFileNo(currentFileNo) || '').toLowerCase();

        const candidates = [recordLU, prefixLU].filter(Boolean);

        for (const lu of candidates) {
            let matched = Array.from(landUseSelect.options).find(o =>
                o.text.trim().toLowerCase() === lu
            );
            if (!matched) {
                matched = Array.from(landUseSelect.options).find(o =>
                    o.text.trim().toLowerCase().includes(lu) || lu.includes(o.text.trim().toLowerCase())
                );
            }
            if (matched) {
                landUseSelect.value = matched.value;
                landUseSelect.dispatchEvent(new Event('change'));
                return;
            }
        }
    }

    // Initialize Global File Number Modal
    if (window.GlobalFileNoModal) {
        window.GlobalFileNoModal.init();

        if (pickBtn) {
            pickBtn.addEventListener('click', () => {
                window.GlobalFileNoModal.open({
                    targetFields: ['#file_number'],
                    callback: (data) => {
                        console.log('File selected:', data);
                        if (data.record) {
                            const record = data.record;

                            // Auto-populate fields
                            if (applicantInput) applicantInput.value = record.file_name || record.FileName || '';
                            if (locationInput) locationInput.value = record.location || '';
                            if (plotInput) plotInput.value = record.plot_no || '';
                            // street_name, district, lga are now Select2 with hidden inputs
                            const streetVal = record.street_name || '';
                            if (streetVal) {
                                document.getElementById('street_name').value = streetVal;
                                const opt = new Option(streetVal, streetVal, true, true);
                                $('#street_name_select').append(opt).trigger('change');
                            }
                            const districtVal = record.district || '';
                            if (districtVal) {
                                document.getElementById('district').value = districtVal;
                                const opt = new Option(districtVal, districtVal, true, true);
                                $('#district_select').append(opt).trigger('change');
                            }
                            const lgaVal = record.lga || '';
                            if (lgaVal) {
                                document.getElementById('lga').value = lgaVal;
                                const opt = new Option(lgaVal, lgaVal, true, true);
                                $('#lga_select').append(opt).trigger('change');
                            }
                            // layout_plan_no is now a Select2 — set via jQuery
                            const tpVal = record.layout_plan_no || '';
                            if (tpVal && $('#layout_plan_no').length) {
                                const opt = new Option(tpVal, tpVal, true, true);
                                $('#layout_plan_no').append(opt).trigger('change');
                            }
                            if (trackingInput) trackingInput.value = record.tracking_id || '';

                            // Default the Prevailing Year to the year in the file number, fallback to commissioning date
                            let fileYear = null;
                            const fileNoVal = (fileNoInput ? fileNoInput.value : '') || record.mlsfNo || record.kangisFileNo || record.NewKANGISFileNo || record.st_file_no || '';
                            if (fileNoVal) {
                                const yearMatch = fileNoVal.match(/(?:^|[^0-9])(19\d{2}|20\d{2})(?:[^0-9]|$)/);
                                if (yearMatch) {
                                    fileYear = parseInt(yearMatch[1]);
                                }
                            }
                            if (!fileYear && record.commissioning_date) {
                                fileYear = new Date(record.commissioning_date).getFullYear();
                            }
                            if (fileYear && cofoYearInput) {
                                cofoYearInput.value = fileYear;
                            }

                            // Auto-populate Land Use (and trigger purpose load)
                            applyLandUse(record);

                            // Trigger change event for text inputs
                            [applicantInput, locationInput, lgaInput, plotInput, layoutPlanInput, trackingInput].forEach(input => {
                                if (input) input.dispatchEvent(new Event('change'));
                            });

                            // Auto-detect term based on Land Use name
                            const termInput     = document.getElementById('term_input');
                            const baseTermInput = document.getElementById('base_term');
                            if (termInput && (record.land_use || record.LandUse)) {
                                const lu = (record.land_use || record.LandUse).toUpperCase().trim();
                                let base = null;
                                if (lu.includes('RESIDENTIAL') || lu.includes('AGRICULTURAL') || lu.includes('AGRICULTURE')) {
                                    base = '99';
                                } else if (lu.includes('COMMERCIAL') || lu.includes('INDUSTRIAL')) {
                                    base = '40';
                                }
                                if (base !== null) {
                                    if (baseTermInput) baseTermInput.value = base;
                                    if (window._calcResidualTerm) window._calcResidualTerm();
                                    else termInput.value = base;
                                }
                            }

                            // Clean up file_number if it has (Block)
                            if (fileNoInput && fileNoInput.value.includes('(Block)')) {
                                fileNoInput.value = fileNoInput.value.replace(/\s*\(Block\)/, '').trim();
                            }

                            // Auto-detect recommendation type from file number
                            const fileNo = (fileNoInput ? fileNoInput.value : '') || (data.fileNumber || '');
                            autoDetectRecommendationType(fileNo);

                            // Warn immediately if a recommendation already exists for this file
                            checkDuplicateFileNo(fileNo).then(dup => { if (dup) warnDuplicate(dup); });
                        }
                    }
                });
            });
        }
    }

    // ── Duplicate file-number check ──────────────────────────────────────────
    // Warns when a recommendation already exists for the selected file number.
    // On the edit page the current record is excluded via data-record-id.
    const dupCheckUrl = form?.dataset.dupcheckUrl || '';
    const excludeId   = form?.dataset.recordId || '';
    const dupConfirmedInput = document.getElementById('duplicate_confirmed');
    let lastDuplicate = null; // cache the most recent check result

    function checkDuplicateFileNo(fileNo) {
        lastDuplicate = null;
        // Any fresh check invalidates a previous "Save Anyway" — picking a
        // different file number must not inherit the earlier confirmation.
        if (typeof window._resetDupConfirmation === 'function') window._resetDupConfirmation();
        if (!dupCheckUrl || !fileNo) return Promise.resolve(null);

        const params = new URLSearchParams({ file_number: fileNo });
        if (excludeId) params.append('exclude_id', excludeId);

        return fetch(dupCheckUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
        })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                lastDuplicate = (data && data.exists) ? data : null;
                return lastDuplicate;
            })
            .catch(() => null);
    }

    function warnDuplicate(dup) {
        if (!dup || typeof Swal === 'undefined') return;
        // A re-issuance is a deliberate second record for the same file number.
        if (window._reissuanceMode) return;
        Swal.fire({
            icon: 'warning',
            title: 'Possible Duplicate',
            html:
                'A recommendation already exists for <strong>' + (dup.file_number || '') + '</strong>.' +
                '<div style="text-align:left;margin-top:10px;font-size:0.85rem;color:#475569">' +
                    '<div><strong>Applicant:</strong> ' + (dup.applicant_name || '—') + '</div>' +
                    '<div><strong>Status:</strong> ' + (dup.status || '—') + '</div>' +
                    '<div><strong>Created:</strong> ' + (dup.created_at || '—') + '</div>' +
                '</div>',
            showCancelButton: true,
            confirmButtonText: 'Open Existing',
            cancelButtonText: 'Continue Anyway',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
        }).then(result => {
            if (result.isConfirmed && dup.edit_url) {
                window.location.href = dup.edit_url;
            }
        });
    }

    function autoDetectRecommendationType(fileNo) {
        const isConversion = /CON/i.test(fileNo);
        const directRadio     = document.querySelector('input[name="type"][value="Direct"]');
        const conversionRadio = document.querySelector('input[name="type"][value="Conversion"]');
        const directLabel     = directRadio?.closest('label');
        const conversionLabel = conversionRadio?.closest('label');

        if (isConversion) {
            if (conversionRadio) { conversionRadio.checked = true; conversionRadio.disabled = false; }
            if (directRadio)     { directRadio.checked = false; directRadio.disabled = true; }
            if (directLabel)     { directLabel.classList.add('opacity-40', 'cursor-not-allowed'); directLabel.classList.remove('cursor-pointer'); }
            if (conversionLabel) { conversionLabel.classList.remove('opacity-40', 'cursor-not-allowed'); conversionLabel.classList.add('cursor-pointer'); }
            conversionSection?.classList.remove('hidden');
        } else {
            if (directRadio)     { directRadio.checked = true; directRadio.disabled = false; }
            if (conversionRadio) { conversionRadio.checked = false; conversionRadio.disabled = true; }
            if (conversionLabel) { conversionLabel.classList.add('opacity-40', 'cursor-not-allowed'); conversionLabel.classList.remove('cursor-pointer'); }
            if (directLabel)     { directLabel.classList.remove('opacity-40', 'cursor-not-allowed'); directLabel.classList.add('cursor-pointer'); }
            conversionSection?.classList.add('hidden');
        }

        const active = isConversion ? conversionRadio : directRadio;
        if (active) active.dispatchEvent(new Event('change'));
    }

    // Form submission validation
    if (form) {
        let dupConfirmed = false; // set once the user chooses to save despite a duplicate

        window._resetDupConfirmation = function () {
            dupConfirmed = false;
            if (dupConfirmedInput) dupConfirmedInput.value = '0';
        };

        function promptDuplicate(dup) {
            Swal.fire({
                icon: 'warning',
                title: 'Possible Duplicate',
                html: 'A recommendation already exists for <strong>' + (dup.file_number || fileNoInput.value) + '</strong>.<br>Save this one anyway?',
                showCancelButton: true,
                confirmButtonText: 'Save Anyway',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
            }).then(result => {
                if (result.isConfirmed) {
                    dupConfirmed = true;
                    // The server rejects a duplicate unless this flag comes with the post.
                    if (dupConfirmedInput) dupConfirmedInput.value = '1';
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            });
        }

        form.addEventListener('submit', function (e) {
            // A Plot Subdivision batch has no single file number, land use or
            // applicant — each child row carries its own, and the batch endpoint
            // runs the duplicate guard per child. Every check below is a
            // single-record concern, so stand the whole guard down.
            // The class is set by the batch module in land_recommendations/form.blade.php.
            if (form.classList.contains('batch-mode')) return;

            if (!fileNoInput.value) {
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'File Number Required', text: 'Please select a file number before saving.' });
                return;
            }
            if (landUseSelect && !landUseSelect.value) {
                e.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Land Use Required', text: 'Please select a land use category.' });
                return;
            }

            // Always re-check on submit rather than trusting `lastDuplicate`: the
            // file number may have been set without going through the picker, or an
            // earlier check may have failed silently. The server enforces this too.
            // A re-issuance deliberately repeats an existing file number, so it is
            // exempt (the server skips its guard for the same reason).
            if (!dupConfirmed && !window._reissuanceMode) {
                e.preventDefault();
                checkDuplicateFileNo(fileNoInput.value).then(dup => {
                    if (dup) {
                        promptDuplicate(dup);
                        return;
                    }
                    dupConfirmed = true; // nothing to confirm — let the next submit through
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                });
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="animate-spin mr-2"></i> Processing...';
            }
        });
    }

    // Dynamic Dropdowns — Land Use change loads purposes
    if (landUseSelect) {
        landUseSelect.addEventListener('change', function () {
            const landUseId = this.value;
            const landUseName = this.options[this.selectedIndex].text;

            if (landUseText) {
                landUseText.value = landUseId ? landUseName : '';
            }

            // Auto-detect term
            const termInput    = document.getElementById('term_input');
            const baseTermInput = document.getElementById('base_term');
            if (termInput && landUseName) {
                const lu = landUseName.toUpperCase().trim();
                let base = null;
                if (lu.includes('RESIDENTIAL') || lu.includes('AGRICULTURAL') || lu.includes('AGRICULTURE')) {
                    base = '99';
                } else if (lu.includes('COMMERCIAL') || lu.includes('INDUSTRIAL')) {
                    base = '40';
                }
                if (base !== null) {
                    if (baseTermInput) baseTermInput.value = base;
                    if (window._calcResidualTerm) window._calcResidualTerm();
                    else termInput.value = base;
                }
            }

            // Reset purpose select
            if (purposeSelect) {
                purposeSelect.innerHTML = '<option value="">Loading...</option>';
                purposeSelect.disabled = true;
            }

            if (landUseId) {
                fetch(`/api/reference/purposes?landuseid=${landUseId}`)
                    .then(r => r.json())
                    .then(result => {
                        if (result.success) {
                            const savedPurpose = purposeSelect.dataset.selected || '';
                            let options = '<option value="">Select Purpose</option>';
                            result.data.forEach(p => {
                                const sel = String(p.id) === String(savedPurpose) ? ' selected' : '';
                                options += `<option value="${p.id}"${sel}>${p.name}</option>`;
                            });
                            // Add 'Other' option
                            const isOther = String(savedPurpose) === 'other' || (savedPurpose === '' && purposeSelect.dataset.custom === 'true');
                            options += `<option value="other"${isOther ? ' selected' : ''}>Other</option>`;
                            
                            purposeSelect.innerHTML = options;
                            purposeSelect.disabled = false;
                            
                            // Sync the hidden text field
                            if (savedPurpose && savedPurpose !== 'other') {
                                const selOpt = purposeSelect.options[purposeSelect.selectedIndex];
                                if (purposeText && selOpt) purposeText.value = selOpt.text;
                            }
                            
                            // Trigger change so UI can update (e.g. show custom purpose field)
                            purposeSelect.dispatchEvent(new Event('change'));
                        } else {
                            purposeSelect.innerHTML = '<option value="">Error loading purposes</option>';
                        }
                    })
                    .catch(() => {
                        purposeSelect.innerHTML = '<option value="">Error loading purposes</option>';
                    });
            } else {
                if (purposeSelect) {
                    purposeSelect.innerHTML = '<option value="">Select Purpose</option>';
                    purposeSelect.disabled = true;
                }
            }
        });
    }

    if (purposeSelect) {
        purposeSelect.addEventListener('change', function() {
            if (purposeText) {
                purposeText.value = this.value ? this.options[this.selectedIndex].text : '';
            }
        });
    }

    // Trigger change if land use already selected on page load (edit mode)
    if (landUseSelect && landUseSelect.value) {
        landUseSelect.dispatchEvent(new Event('change'));
    }

    // Edit mode: file number already populated — apply type lock immediately
    if (fileNoInput && fileNoInput.value) {
        autoDetectRecommendationType(fileNoInput.value);
    }

    // Residual Term: shown inside the Term (Years) field when CoFO year is entered
    const cofoYearInput = document.getElementById('cofo_year');
    const selectedYearSelect = document.getElementById('selected_year');
    const appTypeHidden = document.getElementById('application-type-hidden');
    const termInput = document.getElementById('term_input');
    const termHint  = document.getElementById('term_hint');

    function setTermHint(text) {
        if (termHint) termHint.textContent = text || '';
    }

    // Recomputes the residual term from the CoFO year.
    function calcResidualTerm() {
        const termEl     = termInput || document.getElementById('term_input');
        const baseTermEl = document.getElementById('base_term');
        if (!cofoYearInput || !termEl) return;
        const prevailingYear = parseInt(cofoYearInput.value) || 0;
        const selectedYear   = parseInt(selectedYearSelect ? selectedYearSelect.value : new Date().getFullYear()) || new Date().getFullYear();
        const baseTerm = parseInt(baseTermEl ? baseTermEl.value : termEl.value) || 0;
        const appType  = (appTypeHidden ? appTypeHidden.value : '').trim();

        // Automate the term calculation for subdivisions, mergers, plot extensions
        const automateTypes = ['Plot Subdivision', 'Plot Merger', 'Plot Extension'];
        const shouldAutomate = automateTypes.includes(appType);

        if (shouldAutomate) {
            if (prevailingYear >= 1900 && selectedYear >= prevailingYear && baseTerm > 0) {
                const residual = baseTerm - (selectedYear - prevailingYear);
                termEl.value = residual > 0 ? residual : 0;
                setTermHint(`Automated residual: ${baseTerm} - (${selectedYear} - ${prevailingYear}) = ${termEl.value} years.`);
                termEl.readOnly = true;
                termEl.classList.add('bg-slate-50', 'text-slate-500', 'cursor-not-allowed');
            } else {
                termEl.value = baseTerm || '';
                setTermHint('Ensure Prevailing Year and Year are filled correctly to calculate the residual.');
                termEl.readOnly = false;
                termEl.classList.remove('bg-slate-50', 'text-slate-500', 'cursor-not-allowed');
            }
        } else {
            termEl.readOnly = false;
            termEl.classList.remove('bg-slate-50', 'text-slate-500', 'cursor-not-allowed');
            
            if (prevailingYear >= 1900 && selectedYear >= prevailingYear && baseTerm > 0) {
                const residual = baseTerm - (selectedYear - prevailingYear);
                termEl.value = residual > 0 ? residual : 0;
                setTermHint(`Residual: ${baseTerm} - (${selectedYear} - ${prevailingYear}) = ${termEl.value} years (Overrideable).`);
            } else {
                termEl.value = baseTerm || '';
                setTermHint('Enter Prevailing Year and select Year to auto-calculate the residual term.');
            }
        }
        termEl.dataset.manual = '0';
    }
    window._calcResidualTerm = calcResidualTerm;

    if (termInput) {
        termInput.addEventListener('input', function() {
            this.dataset.manual = '1';
            setTermHint('Manually entered term. Change the Prevailing Year or selected Year to recalculate.');
        });
    }

    if (cofoYearInput) {
        cofoYearInput.addEventListener('input', calcResidualTerm);
        cofoYearInput.addEventListener('change', calcResidualTerm);
    }
    if (selectedYearSelect) {
        selectedYearSelect.addEventListener('change', calcResidualTerm);
    }

    if (fileNoInput) {
        const updatePrevailingYearFromFileNo = () => {
            const fileNoStr = fileNoInput.value;
            if (fileNoStr) {
                const match = fileNoStr.match(/(?:^|[^0-9])(19\d{2}|20\d{2})(?:[^0-9]|$)/);
                if (match) {
                    const fileYear = parseInt(match[1]);
                    if (fileYear && cofoYearInput) {
                        cofoYearInput.value = fileYear;
                        calcResidualTerm();
                    }
                }
            }
        };
        fileNoInput.addEventListener('input', updatePrevailingYearFromFileNo);
        fileNoInput.addEventListener('change', updatePrevailingYearFromFileNo);
    }

    // On load, keep a term that was already saved on the record; otherwise derive it.
    if (!termInput || termInput.dataset.saved !== '1' || !termInput.value) {
        calcResidualTerm();
    } else if (cofoYearInput && cofoYearInput.value) {
        setTermHint(`Saved term for Prevailing Year ${cofoYearInput.value}. Change the year to recalculate.`);
    }

    if (window.lucide) {
        window.lucide.createIcons();
    }
});
