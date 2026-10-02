<div class="overflow-x-auto">
    <table class="manual-attendance-table">
        @php $embedded = $embedded ?? false; @endphp
        <thead>
            <tr>
            <th>{{ $embedded ? 'S/N' : 'Select' }}</th>
                <th>Employee</th>
                <th>Work Station</th>
                <th>Status</th>
                <th>Check-in Time</th>
                <th>Check-out Time</th>
                <th>Hours</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody id="manual-attendance-table-body">
            <tr>
                <td colspan="8" class="manual-attendance-empty">Select filters to load staff.</td>
            </tr>
        </tbody>
    </table>
</div>
