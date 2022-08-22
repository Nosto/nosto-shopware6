import template from './nosto-integration-settings-general.html.twig';

const { Component, Mixin, Context } = Shopware;
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
            productCustomFields: []
        };
    },

    computed: {
        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },
    },

    created() {
        this.getProductCustomFields();
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const configPrefix = 'NostoIntegration.settings.',
                defaultConfigs = {
                    tag1: null,
                    tag2: null,
                    tag3: null,
                    googleCategory: null,
                    isInitializeNostoAfterInteraction: null
                };

            /**
             * Initialize config data with default values.
             */
            for (const [key, defaultValue] of Object.entries(defaultConfigs)) {
                if (this.allConfigs['null'][configPrefix + key] === undefined) {
                    this.$set(this.allConfigs['null'], configPrefix + key, defaultValue);
                }
            }

            // For old single select config
            for (let i = 1; i < 4; i++) {
                let key = 'NostoIntegration.settings.tag' + i
                if (typeof this.allConfigs['null'][key] === 'string' || this.allConfigs['null'][key] instanceof String) {
                    this.allConfigs['null'][key] = [this.allConfigs['null'][key]]
                }
                if (typeof this.actualConfigData[key] === 'string' || this.actualConfigData[key] instanceof String) {
                    this.actualConfigData[key] = [this.actualConfigData[key]]
                }
            }
        },

        clearTagValue(tag) {
            this.allConfigs['null']['NostoIntegration.settings.' + tag] = null;
        },

        getProductCustomFields() {
            var me = this;
            const customFieldsCriteria = new Criteria();
            customFieldsCriteria.addFilter(Criteria.equals('relations.entityName', 'product'))
                .addAssociation('customFields')
                .addAssociation('relations');

            return this.customFieldSetRepository.search(customFieldsCriteria, Shopware.Context.api).then((customFieldSets) => {
                customFieldSets.forEach((customFieldSet) => {
                    customFieldSet.customFields.forEach((customField) => {
                        me.productCustomFields.push({
                            name: customField.name,
                            id: customField.name
                        });
                    })
                });
            });
        }
    },
});
