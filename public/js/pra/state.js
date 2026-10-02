(function (window) {
    const cache = new Map();

    function createDefaultState() {
        return {
            lastFileNumber: null,
            record: null,
            appliedToken: null,
            source: null,
            isLoading: false,
            error: null,
            lastStatus: null,
            pendingApplyAttempts: 0,
        };
    }

    const state = Object.assign(createDefaultState(), window.praAutoFillState || {});

    function reset() {
        const fresh = createDefaultState();
        Object.keys(state).forEach((key) => {
            delete state[key];
        });
        Object.assign(state, fresh);
    }

    window.PraStore = {
        cache,
        state,
        resetState: reset,
        set(key, value) {
            state[key] = value;
            return state[key];
        },
        get(key) {
            return state[key];
        }
    };

    window.praAutoFillState = state;
})(window);
