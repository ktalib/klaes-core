document.addEventListener('click', function (event) {
    const button = event.target.closest('.dciv-master-delete');
    if (!button) return;
    event.preventDefault();
    button.closest('.dciv-menu')?.classList.add('hidden');
    MasterDelete.confirm({
        url: button.dataset.deleteUrl,
        reference: button.dataset.fileNumber,
        title: 'Master Delete DCIV File',
        lead: 'Permanently delete this file?',
        targets: [
            'File Number table',
            'DCIV table',
            'File Indexings',
            'Related File Numbers',
            'File Tracking'
        ]
    });
});
