{{--
    Deed of Purchase — instrument-specific fields.

    What was paid for the interest. The parties, property and file number all
    live in the shared capture form; only these two are particular to this
    instrument.

    This template is injected into #instrument-fields, inside the "Additional
    Details" section, which the Deeds screens keep permanently hidden. The Land
    capture page opts back in via InstrumentCaptureConfig.showAdditionalDetails
    — without that flag these fields render into a container nobody can see.
--}}
<div class="grid grid-cols-2 gap-4">
    {{--
        Amount is the purchase consideration and is stored in the existing
        instrument_capture.consideration_amount column rather than a new one.

        Written out rather than using <x-instrument-input> because that component
        force-uppercases on every keystroke, which is meaningless on a money
        field and interferes with typing decimals. Same classes, so it sits
        flush with the fields above.
    --}}
    <div>
        <label id="considerationAmount-label" for="considerationAmount"
            class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-400 text-sm">&#8358;</span>
            </div>
            <input id="considerationAmount" name="considerationAmount" type="text"
                inputmode="decimal" autocomplete="off"
                class="w-full pl-10 px-4 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm"
                placeholder="Purchase consideration">
        </div>
    </div>

    <x-instrument-input id="receipt_no" label="Receipt No" icon="receipt"
        placeholder="Payment receipt number" />
</div>
