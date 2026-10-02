@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('Generate RofO') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        @include($headerPartial ?? 'admin.header')


        <div class="p-6">
            @include('programmes.partials.rofo_form', [
                'rofo' => $rofo,
                'landAdmin' => $landAdmin,
                'existingRofo' => $existingRofo,
                'finalBill' => $finalBill,
                'surveyRecord' => $surveyRecord,
                'nextRofoNo' => $nextRofoNo,
                'PageTitle' => $PageTitle,
                'PageDescription' => $PageDescription,
                'isModal' => false,
                'batchMode' => $batchMode ?? false,
                'batchMother' => $batchMother ?? null,
                'batchUnits' => $batchUnits ?? collect(),
                'batchStats' => $batchStats ?? [
                    'total_units' => 0,
                    'memo_generated' => 0,
                    'rofo_generated' => 0,
                ],
                'batchPrimaryId' => $batchPrimaryId ?? null,
            ])
        </div>

        @include($footerPartial ?? 'admin.footer')
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.initializeRofoForm === 'function') {
                const scope = document.querySelector('[data-rofo-root]');
                window.initializeRofoForm(scope || document);
            }
        });
    </script>
@endsection
