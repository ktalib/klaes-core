<div class="space-y-4">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-gray-600">
            {{ $userRoles->count() }} user roles. Their departments are listed on the
            <a href="{{ route('configurable-entries.index', ['tab' => 'departments']) }}" class="font-semibold" style="color:#a21caf">Departments</a> tab.
        </p>
        <a href="{{ route('user-roles.index') }}" class="ce-pill">Manage user roles</a>
    </div>

    <div class="ce-table-wrapper">
        @if($userRoles->isEmpty())
            <div class="text-center py-12">
                <p class="text-gray-500">No user roles found</p>
            </div>
        @else
            <table class="ce-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>User Type</th>
                        <th>Level</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($userRoles as $role)
                        <tr>
                            <td class="font-medium">{{ $role->name }}</td>
                            <td>{{ $role->department?->name ?? '—' }}</td>
                            <td><span class="ce-pill blue">{{ $role->user_type }}</span></td>
                            <td><span class="ce-pill yellow">{{ ucfirst($role->level) }}</span></td>
                            <td>
                                @if($role->is_active)
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

