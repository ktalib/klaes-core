<div class="space-y-4">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-gray-600">
            {{ $departments->count() }} departments. Their units are listed on the
            <a href="{{ route('configurable-entries.index', ['tab' => 'units']) }}" class="font-semibold" style="color:#7e22ce">Units</a> tab.
        </p>
        <a href="{{ route('departments.index') }}" class="ce-pill">Manage departments &amp; units</a>
    </div>

    <div class="ce-table-wrapper">
        @if($departments->isEmpty())
            <div class="text-center py-12">
                <p class="text-gray-500">No departments found</p>
            </div>
        @else
            <table class="ce-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Units</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($departments as $dept)
                        @php($count = $unitCounts[$dept->id] ?? 0)
                        <tr>
                            <td class="font-medium">{{ $dept->name }}</td>
                            <td><span class="ce-pill">{{ $dept->code }}</span></td>
                            <td class="ce-muted">{{ Str::limit($dept->description, 80) }}</td>
                            <td>
                                @if($count)
                                    <a href="{{ route('configurable-entries.index', ['tab' => 'units']) }}" class="ce-pill" style="background:#faf5ff;color:#7e22ce">{{ $count }} {{ Str::plural('unit', $count) }}</a>
                                @else
                                    <span class="ce-muted">&mdash;</span>
                                @endif
                            </td>
                            <td>
                                @if($dept->is_active)
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

