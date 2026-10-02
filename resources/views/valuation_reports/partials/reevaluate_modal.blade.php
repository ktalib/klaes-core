<div id="reevaluate-modal" class="hidden fixed inset-0 z-[110] overflow-y-auto" aria-labelledby="reevaluate-title" role="dialog" aria-modal="true">

    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <!-- Overlay -->
        <div onclick="closeReevaluateModal()"
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>

        <!-- Modal Content -->
        <div class="relative flex flex-col w-full max-w-md bg-white rounded-3xl text-left shadow-2xl transform transition-all sm:my-8 border border-slate-100 overflow-hidden">

            <!-- Header -->
            <div class="bg-gradient-to-r from-purple-50 to-white border-b border-slate-100 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 bg-purple-600 rounded-lg text-white shadow-sm">
                        <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900 tracking-tight" id="reevaluate-title">Re-Evaluate Report</h3>
                        <p class="text-[11px] font-bold text-slate-500" id="reeval-file-number"></p>
                    </div>
                </div>
                <button type="button" onclick="closeReevaluateModal()"
                    class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition duration-200">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="px-6 py-6 space-y-4">
                <!-- Original value (reference) -->
                <div class="bg-slate-50 border border-slate-100 rounded-2xl px-4 py-3">
                    <p class="text-[10px] font-bold uppercase text-slate-400 tracking-widest">Original Value</p>
                    <p class="text-sm font-black text-slate-700 mt-0.5">₦ <span id="reeval-orig-figures">0.00</span></p>
                    <p class="text-[11px] font-bold text-slate-500 uppercase mt-0.5" id="reeval-orig-words"></p>
                </div>

                <!-- Re-evaluation card -->
                <div class="bg-purple-50/50 border border-purple-100 p-5 rounded-2xl space-y-4 shadow-sm">
                    <h4 class="text-xs font-black text-purple-700 uppercase tracking-widest">Re-Evaluation Value</h4>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase text-purple-600/70">Amount (₦)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-purple-700 text-sm">₦</span>
                            <input type="text" id="reeval-amount" oninput="reevalUpdateWords()" onblur="reevalFormatCurrency()"
                                placeholder="0.00"
                                class="w-full pl-8 pr-4 py-2 bg-white border border-purple-200 rounded-xl outline-none font-black text-xl text-purple-700 tracking-tight focus:ring-4 focus:ring-purple-500/10">
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase text-purple-600/70">Amount in Words</label>
                        <textarea id="reeval-words" rows="2" readonly
                            class="w-full px-3 py-1.5 bg-white border border-purple-100 rounded-lg outline-none font-bold text-purple-800 text-xs uppercase"></textarea>
                    </div>
                </div>

                <p class="text-[11px] text-slate-400 font-medium italic leading-snug">
                    These values will be printed in place of the original ones. Leave blank to keep printing the original value.
                </p>
            </div>

            <!-- Footer -->
            <div class="bg-slate-50 border-t border-slate-100 px-6 py-4 flex items-center justify-between gap-3">
                <button type="button" id="reeval-print-btn" onclick="reevalPrint()"
                    class="flex items-center gap-2 px-5 py-2 bg-white border border-slate-200 text-slate-700 font-bold rounded-xl hover:bg-slate-100 hover:text-slate-900 transition text-sm">
                    <i data-lucide="printer" class="h-4 w-4"></i> Print
                </button>
                <div class="flex items-center gap-3">
                <button type="button" onclick="closeReevaluateModal()"
                    class="px-5 py-2 text-slate-500 font-bold hover:text-slate-800 transition text-sm">Cancel</button>
                <button type="button" id="reeval-submit-btn" onclick="reevalSubmit()"
                    class="flex items-center gap-2 px-6 py-2 bg-purple-600 text-white font-black rounded-xl hover:bg-purple-700 disabled:bg-purple-300 transition shadow-lg shadow-purple-200 text-sm">
                    <span id="reeval-submit-label" class="flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i> Save Re-Evaluation</span>
                    <span id="reeval-submit-spinner" class="hidden items-center gap-2"><i data-lucide="loader-2" class="h-5 w-5 animate-spin"></i> Saving...</span>
                </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        let reevalId = null;
        let reevalSubmitting = false;

        window.openReevaluateModal = function (data) {
            data = data || {};
            reevalId = data.id || null;
            document.getElementById('reeval-file-number').textContent = data.file_number || '';
            document.getElementById('reeval-orig-figures').textContent = data.value_figures || '0.00';
            document.getElementById('reeval-orig-words').textContent = data.value_words || '';
            document.getElementById('reeval-amount').value = data.re_value_figures || '';
            document.getElementById('reeval-words').value = data.re_value_words || '';
            document.getElementById('reevaluate-modal').classList.remove('hidden');
            if (window.lucide) window.lucide.createIcons();
        };

        window.closeReevaluateModal = function () {
            document.getElementById('reevaluate-modal').classList.add('hidden');
        };

        window.reevalPrint = function () {
            if (!reevalId) return;
            window.open(`/valuation-reports/${reevalId}`, '_blank');
        };

        window.reevalFormatCurrency = function () {
            const el = document.getElementById('reeval-amount');
            let val = (el.value || '').toString().replace(/[^0-9.]/g, '');
            if (val) {
                el.value = new Intl.NumberFormat('en-NG', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(parseFloat(val));
            }
        };

        window.reevalUpdateWords = function () {
            let val = (document.getElementById('reeval-amount').value || '').toString().replace(/[^0-9.]/g, '');
            const wordsEl = document.getElementById('reeval-words');
            if (val && !isNaN(val)) {
                wordsEl.value = reevalNumberToWords(parseFloat(val));
            } else {
                wordsEl.value = '';
            }
        };

        function reevalNumberToWords(num) {
            if (num === 0) return 'Zero Naira Only';
            if (!num) return '';

            const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
            const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

            const convert_hundreds = (n) => {
                let str = '';
                if (n >= 100) {
                    str += ones[Math.floor(n / 100)] + ' Hundred ';
                    n %= 100;
                }
                if (n >= 10 && n <= 19) {
                    str += teens[n - 10];
                } else if (n >= 20) {
                    str += tens[Math.floor(n / 10)] + (n % 10 !== 0 ? '-' + ones[n % 10] : '');
                } else if (n > 0) {
                    str += ones[n];
                }
                return str.trim();
            };

            const convert_section = (n, unit) => {
                if (n === 0) return '';
                return convert_hundreds(n) + ' ' + unit + ' ';
            };

            let n = Math.floor(Math.abs(num));
            let kobo = Math.round((Math.abs(num) - n) * 100);

            let parts = [];
            if (n >= 1000000000) {
                parts.push(convert_section(Math.floor(n / 1000000000), 'Billion'));
                n %= 1000000000;
            }
            if (n >= 1000000) {
                parts.push(convert_section(Math.floor(n / 1000000), 'Million'));
                n %= 1000000;
            }
            if (n >= 1000) {
                parts.push(convert_section(Math.floor(n / 1000), 'Thousand'));
                n %= 1000;
            }
            parts.push(convert_hundreds(n));

            let res = parts.filter(p => p !== '').join(' ').replace(/\s+/g, ' ').trim();
            if (!res || res === '') res = 'Zero';
            res = res + ' Naira';
            if (kobo > 0) {
                res += ' and ' + convert_hundreds(kobo) + ' Kobo';
            }
            return res + ' Only';
        }

        window.reevalSubmit = async function () {
            if (reevalSubmitting) return;
            const figures = document.getElementById('reeval-amount').value;
            const words = document.getElementById('reeval-words').value;
            if (!figures || !words) {
                Swal.fire('Required', 'Please enter the re-evaluation amount.', 'warning');
                return;
            }

            reevalSubmitting = true;
            const btn = document.getElementById('reeval-submit-btn');
            const label = document.getElementById('reeval-submit-label');
            const spinner = document.getElementById('reeval-submit-spinner');
            btn.disabled = true;
            label.classList.add('hidden');
            spinner.classList.remove('hidden');
            spinner.classList.add('flex');

            try {
                const response = await fetch(`/valuation-reports/re-evaluate/${reevalId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ re_value_figures: figures, re_value_words: words })
                });
                const result = await response.json();
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Re-Evaluated!',
                        text: result.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => window.location.reload());
                } else {
                    throw new Error(result.message || 'Failed to save re-evaluation');
                }
            } catch (err) {
                Swal.fire('Error', err.message, 'error');
            } finally {
                reevalSubmitting = false;
                btn.disabled = false;
                label.classList.remove('hidden');
                spinner.classList.add('hidden');
                spinner.classList.remove('flex');
            }
        };
    })();
</script>
