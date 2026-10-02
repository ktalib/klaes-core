<div class="space-y-4">
    <!-- Existing Duration -->
    <div class="grid grid-cols-1 gap-4">
        <x-instrument-input id="duration" label="Duration" icon="clock" placeholder="Enter duration (e.g., 5 years)"
            value="{{ $record->duration ?? '' }}" />
    </div>
</div>