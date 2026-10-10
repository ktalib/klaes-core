{{-- Surveyor picker. $cdExcept: surveyor id to leave out (the current holder). Licence problems are shown, not hidden. --}}
@if ($surveyors->isEmpty())
  <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm text-yellow-900">
    No active surveyors in the directory. <a href="{{ route('cadastral-module.surveyors.index') }}" class="underline font-semibold">Add one in the Surveyors directory</a>, then come back.
  </div>
@else
  <select name="cadastral_surveyor_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    <option value="">Choose the surveyor</option>
    @foreach ($surveyors as $sv)
      @continue(isset($cdExcept) && (int) $cdExcept === (int) $sv->id)
      <option value="{{ $sv->id }}" @selected((int) old('cadastral_surveyor_id') === (int) $sv->id)>
        {{ $sv->display_name }}@if ($sv->surcon_number) · {{ $sv->surcon_number }}@endif
        @unless ($sv->canReceiveInstruction()) (licence {{ $sv->licence_status }}{{ $sv->licence_expires_on && $sv->licence_expires_on->isPast() ? ', expired' : '' }}: cannot receive the I to S) @endunless
      </option>
    @endforeach
  </select>
  <p class="text-xs text-gray-500 mt-1">Not listed? <a href="{{ route('cadastral-module.surveyors.index') }}" class="underline">Add the surveyor in the directory</a>.</p>
@endif
