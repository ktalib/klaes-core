{{-- Records Table --}}
<div class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-200">
        <tr>
          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">SN</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">FileNo</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">Local PC Path</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">Server Path</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">Status</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">A4</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">A3</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">Pages</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">Uploaded By</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">Upload Date</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-700 uppercase tracking-wide text-xs">Action</th>
        </tr>
      </thead>
      <tbody id="recordsTableBody" class="divide-y divide-gray-100 bg-white">
        @if(isset($initialRecords) && $initialRecords->count() > 0)
          @foreach($initialRecords as $index => $record)
          <tr class="hover:bg-gray-50 transition-colors duration-150">
            <td class="px-4 py-3 text-gray-600">{{ $initialRecords->firstItem() + $index }}</td>
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900">
                {{ $record->file_number ?? $record->temp_file_id ?? 'N/A' }}
              </div>
            </td>
            <td class="px-4 py-3 text-gray-600 text-sm">
              <span class="font-mono bg-gray-100 px-2 py-1 rounded text-xs">{{ $record->local_pc_path ?? $record->original_filename ?? 'N/A' }}</span>
            </td>
            <td class="px-4 py-3 text-gray-600 text-sm">
              <span class="font-mono bg-gray-100 px-2 py-1 rounded text-xs">{{ $record->document_path ?? 'N/A' }}</span>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                @if($record->status === 'pending') bg-yellow-100 text-yellow-800
                @elseif($record->status === 'converted') bg-green-100 text-green-800  
                @elseif($record->status === 'archived') bg-gray-100 text-gray-800
                @else bg-gray-100 text-gray-800 @endif">
                {{ ucfirst($record->status) }}
              </span>
            </td>
            <td class="px-4 py-3 text-center text-gray-600">
              <span class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded">
                {{ $record->a4_count ?? ($record->paper_size === 'A4' ? 1 : 0) }}
              </span>
            </td>
            <td class="px-4 py-3 text-center text-gray-600">
              <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded">
                {{ $record->a3_count ?? ($record->paper_size === 'A3' ? 1 : 0) }}
              </span>
            </td>
            <td class="px-4 py-3 text-center text-gray-600">
              <span class="inline-flex items-center px-2 py-1 bg-purple-100 text-purple-800 text-xs font-medium rounded">
                {{ $record->total_pages ?? (($record->a4_count ?? ($record->paper_size === 'A4' ? 1 : 0)) + ($record->a3_count ?? ($record->paper_size === 'A3' ? 1 : 0))) }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ $record->uploader ? $record->uploader->name : 'Unknown' }}</td>
            <td class="px-4 py-3 text-gray-600 text-sm">{{ $record->created_at->format('M j, Y g:i A') }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center space-x-2">
                @if($record->document_path)
                <button onclick="blindScanManager.viewDocument('{{ $record->document_path }}')" class="text-blue-600 hover:text-blue-800 text-sm" title="View Document">
                  <i class="fa-solid fa-eye"></i>
                </button>
                @endif
                <button onclick="blindScanManager.viewRecordDetails({{ $record->id }})" class="text-gray-600 hover:text-gray-800 text-sm" title="View Details">
                  <i class="fa-solid fa-info-circle"></i>
                </button>
                <button onclick="blindScanManager.editRecord({{ $record->id }})" class="text-green-600 hover:text-green-800 text-sm" title="Edit">
                  <i class="fa-solid fa-edit"></i>
                </button>
                <button onclick="blindScanManager.deleteRecord({{ $record->id }})" class="text-red-600 hover:text-red-800 text-sm" title="Delete">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          @endforeach
        @else
        <tr>
          <td colspan="11" class="px-4 py-8 text-center text-gray-500">No records found</td>
        </tr>
        @endif
      </tbody>
    </table>
  </div>
</div>