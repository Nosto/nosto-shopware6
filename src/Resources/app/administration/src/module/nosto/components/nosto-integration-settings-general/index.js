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
            productCustomFields: [],
            productTags: [],
            languageCode: null
        };
    },

    computed: {
        domainCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('salesChannelId', this.selectedSalesChannelId));
            return criteria;
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        tagRepository() {
            return this.repositoryFactory.create('tag');
        },
    },

    created() {
        this.initLanguageCode()
        this.getProductCustomFields();
        this.getProductTags();
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const configPrefix = 'NostoIntegration.settings.',
                defaultConfigs = {
                    tag1: null,
                    tag2: null,
                    tag3: null,
                    selectedCustomFields: null,
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

        async initLanguageCode() {
            this.languageCode = await this.getSystemCurrentLocale();
        },

        async getSystemCurrentLocale() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('id', Shopware.Context.api.languageId));
            criteria.addAssociation('locale');
            const languages = await this.languageRepository.search(criteria, Shopware.Context.api);
            return languages.first().locale.code;
        },

        clearTagValue(tag) {
            this.allConfigs['null']['NostoIntegration.settings.' + tag] = null;
        },

        getProductCustomFields() {
            this.initLanguageCode().then(() => {
                var me = this;
                const customFieldsCriteria = new Criteria();
                customFieldsCriteria.setLimit(50000);
                customFieldsCriteria.addFilter(Criteria.equals('relations.entityName', 'product'))
                    .addAssociation('customFields')
                    .addAssociation('relations');

                return this.customFieldSetRepository.search(customFieldsCriteria, Shopware.Context.api).then((customFieldSets) => {
                    customFieldSets.forEach((customFieldSet) => {
                        customFieldSet.customFields.forEach((customField) => {
                            me.productCustomFields.push({
                                label: me.languageCode && customField.config.label !== undefined && customField.config.label[me.languageCode] !== undefined ? customField.config.label[me.languageCode] : customField.name,
                                name: customField.name,
                                id: customField.name
                            });
                        })
                    });
                });
            });
        },

        getProductTags() {
            this.initLanguageCode().then(() => {
                const criteria = new Criteria();
                criteria.setLimit(50000);
                return this.tagRepository.search(criteria, Shopware.Context.api).then((tags) => {
                    tags.forEach((tag) => {
                        this.productTags.push({
                            label: tag.name, name: tag.name, id: tag.id
                        });
                    });
                });
            });
        }
    },
});
