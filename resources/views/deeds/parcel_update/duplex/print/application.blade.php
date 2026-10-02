@extends('deeds.parcel_update.duplex.print._layout')

@section('doc-title', 'Application for APU - Advance Parcel Update (Duplex)')

@section('doc-body')
    <table class="meta">
        <tr><td>Applicant</td><td>{{ $duplex->applicant_name ?: '—' }}</td></tr>
        <tr><td>File Title</td><td>{{ $duplex->file_title ?: '—' }}</td></tr>
        <tr><td>Source File Number(s)</td>
            <td class="mono">{{ implode(', ', (array) ($duplex->source_file_nos ?? [])) ?: '—' }}</td></tr>
        <tr><td>Plot / House No.</td><td>{{ $duplex->plot_no ?: '—' }} / {{ $duplex->house_no ?: '—' }}</td></tr>
        <tr><td>Location</td>
            <td>{{ collect([$duplex->street_name, $duplex->district, $duplex->lga, $duplex->state])->filter()->implode(', ') ?: '—' }}</td></tr>
        <tr><td>Phone</td><td>{{ $duplex->phone ?: '—' }}</td></tr>
    </table>

    <p class="body-paragraph">
        The applicant named above applies for
        {{ $duplex->isSingleStage() ? 'the parcel update' : 'the parcel updates' }} set out below to be
        carried out on the file number{{ count((array) $duplex->source_file_nos) > 1 ? 's' : '' }} stated,
        @if (!$duplex->isSingleStage())
            <strong>in the order shown</strong>, as a single instruction.
        @else
            as a single instruction.
        @endif
    </p>

    <table class="stage-table">
        <thead>
            <tr>
                <th style="width:12%">Order</th>
                <th style="width:34%">Update</th>
                <th style="width:18%">Quantity</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($duplex->stageRows as $stage)
            <tr>
                <td class="mono">{{ $stage->rank }}</td>
                <td>{{ $stage->label() }}</td>
                <td>{{ $stage->outputCount() }}</td>
                <td>
                    @if ($stage->type === 'change_of_purpose')
                        New land use: <strong>{{ $stage->newLandUseLabel() ?? '—' }}</strong>
                    @elseif ($stage->type === 'merger')
                        Sources merged into one parcel
                    @elseif ($stage->type === 'extension')
                        Boundary adjustment
                    @else
                        {{ $stage->outputCount() }} resulting plot{{ $stage->outputCount() > 1 ? 's' : '' }}
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p class="note">
        No file number is created or retired by this application. File numbers are assigned only when
        the duplex is commissioned by the Lands department.
    </p>
@endsection
