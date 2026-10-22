@foreach (['success' => 'border-green-200 bg-green-50 text-green-800', 'error' => 'border-red-200 bg-red-50 text-red-800'] as $revFlash => $revTone)
  @if (session($revFlash))
    <div class="rounded-lg border {{ $revTone }} text-sm px-4 py-3 mb-4">{{ session($revFlash) }}</div>
  @endif
@endforeach
