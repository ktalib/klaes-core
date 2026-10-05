@extends('survey_module.layouts.klaes')

@php
    $PageTitle = \App\Http\Controllers\Survey\LpknController::SECTIONS[$section];
    $PageDescription = 'Application Types / LPKN / ' . $PageTitle;
@endphp

@section('page-title', $PageTitle . ' — KLAES')

@section('survey-content')
    @include('survey_module.partials._flash')

    <form method="GET" class="table-toolbar">
        <label for="lpkn-layout">Layout Plan</label>
        <select name="layout" id="lpkn-layout" required>
            <option value="">Select a layout plan</option>
            @foreach ($layouts as $item)
                <option value="{{ $item->id }}" @selected($layout && $layout->id === $item->id)>
                    {{ $item->lpkn_number }} — {{ $item->layout_name }}
                </option>
            @endforeach
        </select>
        <button class="btn btn-primary" type="submit">Open</button>
        <a class="btn btn-secondary" href="{{ route('survey-module.records.lpkn') }}">Layout Plan Register</a>
    </form>

    @if (!$layout)
        <p>Select a layout plan to view or enter {{ strtolower($PageTitle) }}.</p>
    @else
        <div class="table-toolbar" style="flex-wrap:wrap;gap:8px;">
            @foreach (\App\Http\Controllers\Survey\LpknController::SECTIONS as $key => $title)
                <a class="btn {{ $key === $section ? 'btn-primary' : 'btn-secondary' }} btn-sm"
                   href="{{ route('survey-module.records.lpkn.' . $key, ['layout' => $layout->id]) }}">{{ $title }}</a>
            @endforeach
        </div>

        @if ($section === 'instruction' || $section === 'report')
            <form method="POST" class="farmer-entry-form open"
                  action="{{ route('survey-module.records.lpkn.' . $section . '.save', $layout) }}">
                @csrf
                @method('PUT')
                @php
                    $fields = $section === 'instruction'
                        ? ['its_number' => ['Instruction Number', 'text', 50], 'its_issued_at' => ['Date Issued', 'date', null],
                           'its_recipient' => ['Recipient / Surveyor', 'text', 255], 'its_issued_by' => ['Issuing Officer', 'text', 255]]
                        : ['report_surveyor' => ['Surveyor', 'text', 255], 'report_date' => ['Report Date', 'date', null]];
                    $bodyField = $section === 'instruction' ? 'its_instructions' : 'surveyor_report';
                @endphp
                <div class="form-row">
                    @foreach ($fields as $field => [$label, $type, $max])
                        <div>
                            <label for="{{ $field }}">{{ $label }} <span class="required">*</span></label>
                            <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" required
                                   @if ($max) maxlength="{{ $max }}" @endif
                                   value="{{ old($field, $type === 'date' ? optional($layout->$field)->format('Y-m-d') : $layout->$field) }}" />
                        </div>
                    @endforeach
                </div>
                <label for="{{ $bodyField }}">{{ $PageTitle }} <span class="required">*</span></label>
                <textarea id="{{ $bodyField }}" name="{{ $bodyField }}" rows="10" maxlength="20000" required
                          style="width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:6px;">{{ old($bodyField, $layout->$bodyField) }}</textarea>
                <button type="submit" class="btn btn-success">Save {{ $PageTitle }}</button>
            </form>
        @elseif ($section === 'coordinates')
            <form method="POST" class="farmer-entry-form open" action="{{ route('survey-module.records.lpkn.coordinates.save', $layout) }}">
                @csrf
                <div class="form-row">
                    <div><label for="beacon_id">Beacon ID *</label><input id="beacon_id" name="beacon_id" value="{{ old('beacon_id') }}" maxlength="50" required /></div>
                    <div><label for="sort_order">Traverse Order *</label><input id="sort_order" name="sort_order" type="number" min="0" max="1000000" value="{{ old('sort_order', ($coordinates->max('sort_order') ?? 0) + 1) }}" required /></div>
                    @foreach (['northing' => 'Northing (m)', 'easting' => 'Easting (m)', 'elevation' => 'Elevation (m)'] as $field => $label)
                        <div><label for="{{ $field }}">{{ $label }}{{ $field !== 'elevation' ? ' *' : '' }}</label>
                            <input id="{{ $field }}" name="{{ $field }}" type="number" step="0.001" value="{{ old($field) }}" @required($field !== 'elevation') /></div>
                    @endforeach
                </div>
                <div class="form-row"><div><label for="remarks">Remarks</label><input id="remarks" name="remarks" maxlength="500" value="{{ old('remarks') }}" /></div></div>
                <button type="submit" class="btn btn-success">Add Coordinate</button>
            </form>
            <div class="table-wrapper"><div class="table-scroll"><table>
                <thead><tr><th>Order</th><th>Beacon</th><th>Northing (m)</th><th>Easting (m)</th><th>Elevation (m)</th><th>Remarks</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($coordinates as $coordinate)
                        <tr><td>{{ $coordinate->sort_order }}</td><td>{{ $coordinate->beacon_id }}</td><td>{{ $coordinate->northing }}</td><td>{{ $coordinate->easting }}</td><td>{{ $coordinate->elevation }}</td><td>{{ $coordinate->remarks }}</td>
                            <td><form method="POST" action="{{ route('survey-module.records.lpkn.coordinates.delete', [$layout, $coordinate]) }}" onsubmit="return confirm('Remove this coordinate?');">
                                @csrf @method('DELETE')<button class="btn btn-secondary btn-sm" type="submit">Remove</button>
                            </form></td></tr>
                    @empty
                        <tr><td colspan="7">No coordinates entered for this layout.</td></tr>
                    @endforelse
                </tbody>
            </table></div></div>
        @else
            <p>Computed from consecutive beacons in traverse order. Distances are in metres; grid bearings are degrees clockwise from north. The final beacon is not automatically joined to the first.</p>
            <div class="table-wrapper"><div class="table-scroll"><table>
                <thead><tr><th>From Beacon</th><th>To Beacon</th><th>Distance (m)</th><th>Grid Bearing (°)</th></tr></thead>
                <tbody>
                    @forelse ($legs as $leg)
                        <tr><td>{{ $leg['from'] }}</td><td>{{ $leg['to'] }}</td>
                            <td>{{ $leg['distance'] !== null ? number_format($leg['distance'], 3) : 'Missing coordinates' }}</td>
                            <td>{{ $leg['bearing'] !== null ? number_format($leg['bearing'], 6) : 'Undefined' }}</td></tr>
                    @empty
                        <tr><td colspan="4">Enter at least two beacons in List of Coordinates to compute observations.</td></tr>
                    @endforelse
                </tbody>
            </table></div></div>
        @endif
    @endif
@endsection
