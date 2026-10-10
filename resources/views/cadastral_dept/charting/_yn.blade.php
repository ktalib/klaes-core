{{-- Yes / No as two buttons; $field is the posted name, $model the Alpine variable ('1' / '0'). --}}
<input type="hidden" name="{{ $field }}" :value="{{ $model }}">
<div class="inline-flex gap-2">
  <button type="button" @click="{{ $model }} = '1'"
          :class="{{ $model }} === '1' ? 'bg-green-100 text-green-800 border-green-500' : 'bg-white text-gray-700 border-gray-300'"
          class="px-5 py-2 rounded-lg border text-sm font-semibold">Yes</button>
  <button type="button" @click="{{ $model }} = '0'"
          :class="{{ $model }} === '0' ? 'bg-red-100 text-red-800 border-red-500' : 'bg-white text-gray-700 border-gray-300'"
          class="px-5 py-2 rounded-lg border text-sm font-semibold">No</button>
</div>
