{{-- The card's log: workflow movements and QR log-ins / log-outs, oldest first. --}}
<div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
      <tr>
        <th class="px-3 py-2">S/N</th><th class="px-3 py-2">Date &amp; time</th><th class="px-3 py-2">Unit</th>
        <th class="px-3 py-2">Movement</th><th class="px-3 py-2">Reason</th><th class="px-3 py-2">By</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @forelse ($timeline as $row)
        <tr class="{{ $row['source'] === 'qr' ? 'bg-blue-50' : '' }}">
          <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
          <td class="px-3 py-2 whitespace-nowrap">{{ $row['at']->format('d M Y H:i') }}</td>
          <td class="px-3 py-2 font-semibold">{{ $row['unit'] }}</td>
          <td class="px-3 py-2">{{ $row['event'] }}</td>
          <td class="px-3 py-2 text-gray-600">{{ $row['reason'] }}</td>
          <td class="px-3 py-2 text-gray-600">{{ $row['by'] }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">No movements yet.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<p class="text-xs text-gray-500 mt-2">White rows come from the workflow automatically. Blue rows are QR log-ins and log-outs.</p>
