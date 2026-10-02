<div class="space-y-4">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-gray-600">
            {{ $units->count() }} units across {{ $units->pluck('parent_name')->filter()->unique()->count() }} of {{ $departmentCount }} departments.
            To add one, use <em>Create Department</em> and pick its department under <em>Unit of</em>.
        </p>
        <a href="{{ route('departments.index') }}" class="ce-pill">Manage departments &amp; units</a>
    </div>

    <div class="ce-table-wrapper">
        @if($units->isEmpty())
            <div class="text-center py-12">
                <p class="text-gray-500">No units yet</p>
            </div>
        @else
            <table class="ce-table">
                <thead>
                    <tr>
                        <th>Unit</th>
                        <th>Department</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($units as $unit)
                        <tr>
                            <td class="font-medium">{{ $unit->name }}</td>
                            <td>
                                @if($unit->parent_name)
                                    <span class="ce-pill" style="background:#fdf4ff;color:#a21caf">{{ $unit->parent_name }}</span>
                                @else
                                    <span class="ce-pill red">No department</span>
                                @endif
                            </td>
                            <td><span class="ce-pill">{{ $unit->code }}</span></td>
                            <td class="ce-muted">{{ Str::limit($unit->description, 80) }}</td>
                            <td>
                                @if($unit->is_active)
                                    <span class="ce-pill green">Active</span>
                                @else
                                    <span class="ce-pill red">Inactive</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

