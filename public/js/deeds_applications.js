document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('deeds-application-modal');
  if (!modal) return;

  const form = document.getElementById('deeds-application-form');
  const methodInput = form.querySelector('input[name="_method"]');
  const titleEl = document.getElementById('deeds-application-modal-title');
  const storeUrl = form.dataset.storeUrl;

  const setValue = (name, value) => {
    const input = form.querySelector(`[name="${name}"]`);
    if (input) {
      input.value = value ?? '';
    }
  };

  const resetForm = () => {
    form.reset();
    methodInput.value = 'POST';
    form.action = storeUrl;
    titleEl.textContent = 'New Application';
  };

  const openModal = () => {
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  };

  const closeModal = () => {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    resetForm();
  };

  document.querySelectorAll('.open-deeds-application-modal').forEach((button) => {
    button.addEventListener('click', () => {
      const mode = button.dataset.mode || 'create';
      resetForm();

      if (mode === 'edit') {
        const record = JSON.parse(button.dataset.record || '{}');
        setValue('application_number', record.application_number);
        setValue('file_number', record.file_number);
        setValue('subject', record.subject);
        setValue('status', record.status || 'draft');
        setValue('submitted_by', record.submitted_by);
        setValue('submitted_at', record.submitted_at);
        setValue('notes', record.notes);
        methodInput.value = 'PUT';
        form.action = button.dataset.updateUrl;
        titleEl.textContent = 'Edit Application';
      }

      openModal();
    });
  });

  modal.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', closeModal);
  });

  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
      closeModal();
    }
  });

  if (window.deedsApplicationFormShouldOpen) {
    resetForm();
    const data = window.deedsApplicationOldInput || {};
    Object.keys(data).forEach((key) => {
      if (['application_number', 'file_number', 'subject', 'status', 'submitted_by', 'submitted_at', 'notes'].includes(key)) {
        setValue(key, data[key]);
      }
    });
    openModal();
  }
});
