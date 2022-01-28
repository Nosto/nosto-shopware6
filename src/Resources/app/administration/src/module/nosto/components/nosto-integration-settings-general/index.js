import template from './nosto-integration-settings-general.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('nosto-integration-settings-general', {
    template,

    inject: [
        'repositoryFactory',
    ],

    props: {
        actualConfigData: {
            type: Object,
            required: true,
        },
        allConfigs: {
            type: Object,
            required: true,
        },
        selectedSalesChannelId: {
            type: String,
            required: false,
            default: null,
        }
    },

    data() {
        return {
            isLoading: false,
            propertyGroups: []
        };
    },

    computed: {
        propertyRepository() {
            return this.repositoryFactory.create('property_group');
        },
    },

    created() {
        this.getProductProperties();
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const configPrefix = 'NostoIntegration.settings.',
                defaultConfigs = {
                    tag1: null,
                    tag2: null,
                    tag3: null,
                    gtin: null,
                    googleCategory: null
                };

            /**
             * Initialize config data with default values.
             */
            for (const [key, defaultValue] of Object.entries(defaultConfigs)) {
                if (this.allConfigs['null'][configPrefix + key] === undefined) {
                    this.$set(this.allConfigs['null'], configPrefix + key, defaultValue);
                }
            }
        },

        getProductProperties() {
            this.isLoading = true;
            const criteria = new Criteria();

            return this.propertyRepository.search(criteria).then((items) => {
                this.propertyGroups = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        }
    },
});
