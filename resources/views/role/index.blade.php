@extends('layouts.app')
@section('page-title')
    {{ __('Role') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item" aria-current="page"> {{ __('Role') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Role List') }}</h5>
                        </div>
                        <div class="col-auto d-flex gap-2">
                            <button type="button" id="bulk-delete-btn" class="btn btn-danger d-none">
                                <i class="ti ti-trash"></i> {{ __('Delete Selected') }} (<span id="selected-count">0</span>)
                            </button>
                            @if (Gate::check('create role'))
                                <a class="btn btn-secondary"
                                href="{{ route('role.create') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i> {{ __('Create Role') }}</a>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="dt-responsive table-responsive">
                        <table class="table table-hover advance-datatable">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="select-all">
                                        </div>
                                    </th>
                                    <th>{{ __('Role') }}</th>
                                    <th>{{ __('Assigned Users') }}</th>
                                    <th>{{ __('Assigned Permissions') }}</th>
                                    <th class="text-right">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($roleData as $role)
                                    <tr data-id="{{ $role->id }}">
                                        <td>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input role-checkbox" value="{{ $role->id }}">
                                            </div>
                                        </td>
                                        <td>{{ ucfirst($role->name) }} </td>
                                        <td>{{ \Auth::user()->roleWiseUserCount($role->name) }}</td>
                                        <td>{{ $role->permissions()->count() }}</td>
                                        <td>
                                            <div class="cart-action">
                                                {!! Form::open(['method' => 'DELETE', 'route' => ['role.destroy', $role->id]]) !!}
                                                @can('edit role')
                                                    <a class="avtar avtar-xs btn-link-secondary text-secondary" data-bs-toggle="tooltip" data-bs-original-title="{{ __('Edit') }}"
                                                        href="{{ route('role.edit', $role->id) }}"> <i data-feather="edit"></i></a>
                                                @endif
                                                @if ($role->name != 'tenant' && $role->name != 'maintainer')
                                                    @can('delete role')
                                                        <a class=" avtar avtar-xs btn-link-danger text-danger confirm_dialog" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Detete') }}" href="#"> <i
                                                                data-feather="trash-2"></i></a>
                                                    @endif
                                                @endif
                                                {!! Form::close() !!}
                                            </div>

                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
<script>
    $(document).ready(function() {
        var selectedRoles = [];

        function updateBulkDeleteButton() {
            if (selectedRoles.length > 0) {
                $('#bulk-delete-btn').removeClass('d-none');
                $('#selected-count').text(selectedRoles.length);
            } else {
                $('#bulk-delete-btn').addClass('d-none');
            }
        }

        $('#select-all').on('change', function() {
            var checked = $(this).prop('checked');
            $('.role-checkbox').prop('checked', checked);
            
            selectedRoles = [];
            if (checked) {
                $('.role-checkbox').each(function() {
                    selectedRoles.push($(this).val());
                });
            }
            updateBulkDeleteButton();
        });

        $(document).on('change', '.role-checkbox', function() {
            var id = $(this).val();
            if ($(this).prop('checked')) {
                selectedRoles.push(id);
            } else {
                selectedRoles = selectedRoles.filter(item => item !== id);
                $('#select-all').prop('checked', false);
            }
            
            if (selectedRoles.length === $('.role-checkbox').length) {
                $('#select-all').prop('checked', true);
            }
            updateBulkDeleteButton();
        });

        $('#bulk-delete-btn').on('click', function() {
            if (selectedRoles.length === 0) return;

            Swal.fire({
                title: '{{ __("Are you sure?") }}',
                text: '{{ __("You are about to delete selected roles. This action cannot be undone!") }}',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '{{ __("Yes, delete them!") }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ route("role.bulk-delete") }}',
                        type: 'POST',
                        data: {
                            ids: selectedRoles,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire(
                                    '{{ __("Deleted!") }}',
                                    response.message,
                                    'success'
                                ).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire(
                                    '{{ __("Error!") }}',
                                    response.message,
                                    'error'
                                );
                            }
                        },
                        error: function() {
                            Swal.fire(
                                '{{ __("Error!") }}',
                                '{{ __("An error occurred while deleting roles.") }}',
                                'error'
                            );
                        }
                    });
                }
            });
        });
    });
</script>
@endpush
