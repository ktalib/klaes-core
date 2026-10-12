{{-- Log the file in to a unit, or out of the unit it is in. One Other option, whose name is then required. --}}
<div class="rounded-xl border border-gray-200 bg-white p-5">
  @if ($loggedIn)
    <div class="text-sm text-gray-600">Logged in to</div>
    <div class="text-lg font-bold text-gray-800">{{ $loggedIn['unit_name'] }}</div>
    <div class="text-xs text-gray-500 mb-4">since {{ $loggedIn['at']->format('d M Y H:i') }}</div>
    <form method="POST" action="{{ route('cadastral-dept.fmc.log.save', $fmc->qr_token) }}" class="space-y-3" data-confirm-title="Log the file out?" data-confirm="{{ $fmc->fmc_no }} is logged out of {{ $loggedIn['unit_name'] }}.">
      @csrf
      <input type="hidden" name="direction" value="out">
      <textarea name="reason" rows="2" placeholder="Note (optional)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('reason') }}</textarea>
      <button class="w-full px-4 py-3 rounded-lg bg-red-600 text-white text-sm font-bold inline-flex items-center justify-center gap-2">
        <i data-lucide="log-out" class="w-4 h-4"></i> Log out of {{ $loggedIn['unit_name'] }}
      </button>
    </form>
  @else
    <div class="text-sm text-gray-600 mb-3">Manual override - create log</div>
    <form method="POST" action="{{ route('cadastral-dept.fmc.log.save', $fmc->qr_token) }}" class="space-y-3"
          x-data="{ unit: @js(old('unit_key', '')), other: @js(old('unit_other', '')), reason: @js(old('reason', '')) }"
          data-confirm-title="Log the file in?" data-confirm="{{ $fmc->fmc_no }} is logged in to the chosen unit.">
      @csrf
      <input type="hidden" name="direction" value="in">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Unit <span class="text-red-600">*</span></label>
        <select name="unit_key" x-model="unit" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <option value="">Choose the unit…</option>
          <optgroup label="Cadastral">
            @foreach ($def->internalDesks() as $k => $d)<option value="{{ $k }}">{{ $d['label'] }}</option>@endforeach
          </optgroup>
          <optgroup label="Other departments">
            @foreach (array_diff_key($def->desks(), $def->internalDesks()) as $k => $d)<option value="{{ $k }}">{{ $d['label'] }}</option>@endforeach
          </optgroup>
          <option value="other">Other</option>
        </select>
      </div>
      <div x-show="unit === 'other'" x-cloak>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Specify the unit <span class="text-red-600">*</span></label>
        <input type="text" name="unit_other" x-model="other" :disabled="unit !== 'other'" maxlength="150"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Why is it going there? <span class="text-red-600">*</span></label>
        <textarea name="reason" rows="2" x-model="reason" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
      </div>
      <button :disabled="!unit || (unit === 'other' && other.trim() === '') || reason.trim() === ''"
              class="w-full px-4 py-3 rounded-lg bg-green-700 text-white text-sm font-bold inline-flex items-center justify-center gap-2 disabled:opacity-50">
        <i data-lucide="log-in" class="w-4 h-4"></i> Log in
      </button>
    </form>
  @endif
</div>
