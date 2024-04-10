/**
 * @private
 */
export default {
    namespaced: true,

    state() {
        return {
            loading: true,
            configs: {},
        };
    },

    mutations: {
        setLoading(state, loading) {
            state.loading = loading;
        },
        setDefaultConfigs(state, defaultConfig) {
            console.log(defaultConfig);
            state.configs.null = {
                ...defaultConfig,
                ...state.configs.null,
            };
        },
        setConfig(state, { key, config }) {
            console.log(key, config);
            state.configs[key] = {
                ...config,
            };
        },
        setConfigValue(state, { configKey, key, value }) {
            if (!state.configs[configKey]) {
                state.configs[configKey] = {};
            }

            state.configs[configKey][key] = value;
        },
    },
};
