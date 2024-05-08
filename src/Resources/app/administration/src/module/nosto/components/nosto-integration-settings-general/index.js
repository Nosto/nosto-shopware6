import template from './nosto-integration-settings-general.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/** @private */
Component.register('nosto-integration-settings-general', {
    template,

    inject: [
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('nosto-integration-config-component'),
    ],

    props: {
        selectedSalesChannelId: {
            type: String,
            required: false,
            default: null,
        },
        selectedLanguageId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            productCustomFields: [],
            productTags: [],
            languageCode: null,
        };
    },

    computed: {
        domainCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('salesChannelId', this.selectedSalesChannelId));
            criteria.addFilter(Criteria.equals('languageId', this.selectedLanguageId));
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
        this.initLanguageCode();
        this.getProductCustomFields();
        this.getProductTags();
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.setDefaultConfigs({
                tag1: [],
                tag2: [],
                tag3: [],
                selectedCustomFields: null,
                googleCategory: null,
                isInitializeNostoAfterInteraction: null,
            });
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

        getProductCustomFields() {
            this.initLanguageCode().then(() => {
                const me = this;
                const customFieldsCriteria = new Criteria();
                customFieldsCriteria.setLimit(500);
                customFieldsCriteria.addFilter(Criteria.equals('relations.entityName', 'product'))
                    .addAssociation('customFields')
                    .addAssociation('relations');

                return this.customFieldSetRepository.search(customFieldsCriteria, Shopware.Context.api)
                    .then((customFieldSets) => {
                        customFieldSets.forEach((customFieldSet) => {
                            customFieldSet.customFields.forEach((customField) => {
                                const label = customField.config.label[me.languageCode] || customField.name;
                                me.productCustomFields.push({
                                    label: label,
                                    name: customField.name,
                                    id: customField.name,
                                });
                            });
                        });
                    });
            });
        },

        getProductTags() {
            this.initLanguageCode().then(() => {
                const criteria = new Criteria();
                criteria.setLimit(500);
                return this.tagRepository.search(criteria, Shopware.Context.api).then((tags) => {
                    tags.forEach((tag) => {
                        this.productTags.push({
                            label: tag.name, name: tag.name, id: tag.id,
                        });
                    });
                });
            });
        },
    },
});
