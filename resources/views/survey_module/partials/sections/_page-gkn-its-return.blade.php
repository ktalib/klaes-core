{{-- Page 2's return slip (CR 10112026 call): "There's rank, there's place, there's date. It has to show", and a tick
     box for each Field Record in the jacket. Rank sits with the surveyor above; Place is District + LGA dropdowns
     with Other. $retOff: shown read-only. --}}
@php $retOff = $retOff ?? false; @endphp
<fieldset class="its-sec">
    <legend>Field Records Return (page 2, GKN Section for Surveyor-General)</legend>
    <div class="form-grid its-grid-4">
        @foreach (['return_district' => ['itsRetDistrict', 'Place: District', 'Search district…'], 'return_lga' => ['itsRetLga', 'Place: LGA', 'Search LGA…']] as $f => [$fid, $flabel, $fph])
            @php $fv = old($f, $its->{$f}); @endphp
            <div class="form-group its-caps">
                <label>{{ $flabel }}</label>
                <select name="{{ $f }}" id="{{ $fid }}" data-no-search data-placeholder="{{ $fph }}" style="width:100%;" @disabled($retOff)>
                    <option value=""></option>
                    @if ($fv)<option value="{{ $fv }}" selected>{{ mb_strtoupper($fv) }}</option>@endif
                </select>
                <input type="text" name="{{ $f }}_other" id="{{ $fid }}Other" maxlength="255" value="{{ old($f . '_other') }}" placeholder="Specify the {{ $f === 'return_lga' ? 'LGA' : 'district' }} *"
                       style="margin-top:6px;{{ $fv === 'Other' ? '' : 'display:none;' }}" @disabled($fv !== 'Other' || $retOff) />
                @error($f . '_other')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <div class="form-group">
            <label>Date</label>
            <input type="date" name="return_date" max="{{ now()->toDateString() }}" value="{{ old('return_date', optional($its->return_date)->format('Y-m-d')) }}" @disabled($retOff) />
            @error('return_date')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
        </div>
        <div class="form-group">
            <label>R. of O/G. Item No.</label>
            <input type="text" value="{{ $its->file_number }}" readonly tabindex="-1" style="{{ $ro }}" placeholder="The file number" />
        </div>
        <div class="form-group full">
            <label>I submit the field jacket above job containing:</label>
            <div style="display:flex;flex-wrap:wrap;gap:8px 22px;padding-top:4px;">
                @foreach (\App\Models\Survey\SurveyIts::FIELD_RECORDS as $col => $recLabel)
                    <label style="display:inline-flex;align-items:center;gap:6px;font-weight:500;margin:0;cursor:pointer;white-space:nowrap;flex:0 0 auto;width:auto;">
                        <input type="checkbox" name="{{ $col }}" value="1" @checked(session()->hasOldInput() ? old($col) : $its->{$col}) @disabled($retOff) />
                        {{ $loop->iteration }}. {{ $recLabel }}
                    </label>
                @endforeach
            </div>
        </div>
    </div>
</fieldset>
