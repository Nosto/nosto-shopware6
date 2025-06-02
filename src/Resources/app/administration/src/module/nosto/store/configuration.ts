/* eslint-disable sw-core-rules/require-package-annotation */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

// Define the state interface for TypeScript support
export interface NostoIntegrationConfigState {
    loading: boolean;
    configs: Record<string, any>;
}

export default {
    id: 'nostoIntegrationConfig',


    state: (): NostoIntegrationConfigState => ({
        loading: true,
        configs: {},
    }),

    actions: {
        setLoading(loading: boolean) {
            console.log(123123123);
            this.loading = loading;
        },

        setDefaultConfigs(defaultConfig: any) {
            this.configs.null = {
                ...defaultConfig,
                ...this.configs.null,
            };
        },

        setConfig({ key, config }: { key: string; config: any }) {
            this.configs[key] = {
                ...config,
            };
        },

        setConfigValue({ configKey, key, value }: { configKey: string; key: string; value: any }) {
            if (!this.configs[configKey]) {
                this.configs[configKey] = {};
            }

            this.configs[configKey][key] = value;
        },
    },
};
