(function (window, document) {
    function initialise(container) {
        if (!container) {
            return;
        }

        container.dispatchEvent(new CustomEvent('pra-modal-initialised', {
            bubbles: true,
            detail: {
                container,
                timestamp: Date.now(),
            },
        }));
    }

    window.PraModal = {
        init: initialise,
    };
})(window, document);
