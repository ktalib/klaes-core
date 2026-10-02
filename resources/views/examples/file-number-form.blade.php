<!-- Example Usage in a Form -->
@extends('layouts.app')

@section('styles')
    <link href="{{ asset('css/file-number-modal.css') }}" rel="stylesheet">
@endsection

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Property Form</div>

                <div class="card-body">
                    <form id="propertyForm" method="POST" action="{{ route('property.store') }}">
                        @csrf

                        <div class="form-group">
                            <label for="fileNumber">File Number</label>
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control @error('file_number') is-invalid @enderror" 
                                       name="file_number" 
                                       id="fileNumber" 
                                       value="{{ old('file_number') }}" 
                                       readonly>
                                <div class="input-group-append">
                                    <button type="button" 
                                            class="btn btn-outline-secondary" 
                                            data-toggle="modal" 
                                            data-target="#globalFileNumberModal">
                                        Select File Number
                                    </button>
                                </div>

                                @error('file_number')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <!-- Other form fields... -->

                        <div class="form-group mb-0">
                            <button type="submit" class="btn btn-primary">
                                Submit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include the File Number Modal Component -->
@include('components.file-number-modal')
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/file-number-modal.js') }}"></script>
    <script>
        $(document).ready(function() {
            // Initialize the file number modal
            $('#globalFileNumberModal').fileNumberModal({
                targetInput: '#fileNumber',
                defaultTab: 'mls',
                onSelect: function(value, type) {
                    console.log('Selected:', value, 'Type:', type);
                }
            });
        });
    </script>
@endsection
