@csrf
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
  <div>
    <label class="form-label">Application Number *</label>
    <input type="text" class="form-input" name="application_number" value="{{ old('application_number', $application->application_number) }}" required>
  </div>
  <div>
    <label class="form-label">File Number</label>
    <input type="text" class="form-input" name="file_number" value="{{ old('file_number', $application->file_number) }}">
  </div>
  <div class="md:col-span-2">
    <label class="form-label">Subject</label>
    <input type="text" class="form-input" name="subject" value="{{ old('subject', $application->subject) }}">
  </div>
  <div>
    <label class="form-label">Submitted By</label>
    <input type="text" class="form-input" name="submitted_by" value="{{ old('submitted_by', $application->submitted_by) }}">
  </div>
  <div>
    <label class="form-label">Submitted At</label>
    <input type="datetime-local" class="form-input" name="submitted_at" value="{{ old('submitted_at', optional($application->submitted_at)->format('Y-m-d\TH:i')) }}">
  </div>
  <div>
    <label class="form-label">Status *</label>
    <select name="status" class="form-input" required>
      @foreach($statusOptions as $value => $label)
        <option value="{{ $value }}" @selected(old('status', $application->status) === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <div class="md:col-span-2">
    <label class="form-label">Notes</label>
    <textarea name="notes" rows="3" class="form-input">{{ old('notes', $application->notes) }}</textarea>
  </div>
</div>
