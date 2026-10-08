document.addEventListener('click', function (event) {
    const button = event.target.closest('.dciv-master-delete');
    if (!button) return;
    event.preventDefault();
    button.closest('.dciv-menu')?.classList.add('hidden');
    MasterDelete.confirm({
        url: button.dataset.deleteUrl,
        reference: button.dataset.fileNumber,
        title: 'Master Delete DCIV File',
        lead: 'Permanently delete this selected file number. Other files in the batch are kept.',
        targets: [
            'The selected indexing and file-number registry records',
            'Matching customer, entity, PRA, CofO and staged indexing records',
            'DCIV generation metadata, investigation links and derived label records'
        ],
        keeps: 'Related files and tracking history are kept. The grouping is released; the serial counter is unchanged. Files carrying scans, page typings, bills or SPAS applications cannot be deleted.'
    });
});
