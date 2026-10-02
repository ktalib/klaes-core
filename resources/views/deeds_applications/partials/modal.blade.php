<div id="deeds-application-modal" class="modal-overlay hidden">
  <div class="modal-card">
    <div class="flex items-center justify-between mb-4">
      <h2 id="deeds-application-modal-title" class="text-lg font-semibold text-slate-900">New Application</h2>
      <button type="button" class="modal-close" data-close-modal>&times;</button>
    </div>
    <form id="deeds-application-form" method="POST" action="{{ route('deeds-applications.store') }}" data-store-url="{{ route('deeds-applications.store') }}">
      <input type="hidden" name="_method" value="POST">
      @include('deeds_applications.partials.form', ['application' => $application, 'statusOptions' => $statusOptions])
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" class="btn-secondary" data-close-modal>Cancel</button>
        <button type="submit" class="btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
