import template from './nosto-integration-account-general.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('nosto-integration-account-general', {
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
            configPath: 'NostoIntegration.settings.accounts',
            isLoading: false,
            systemLanguages: [],
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        accountNames: {
            get: function () {
                return this.allConfigs[this.selectedSalesChannelId]['StylaCmsIntegration.settings.accounts'];
            }
        }
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            const criteria = new Criteria();
            criteria.addSorting(Criteria.sort('createdAt', 'ASC'));

            this.languageRepository.search(criteria, Shopware.Context.api).then(result => {
                this.systemLanguages = result;
                this.initLanguageConfig();
            }).finally(() => {
                this.isLoading = false;
            });
        },

        initLanguageConfig() {
            if (this.allConfigs[this.selectedSalesChannelId][this.configPath] === undefined) {
                /**
                 * Here is a trick: we are using "accountNames" computed prop only for reading data in template
                 * and creating config entry here to make it reactive, cuz our account config is an object.
                 */
                this.$set(this.allConfigs[this.selectedSalesChannelId], 'NostoIntegration.settings.accounts', {});
            }
        },

        checkTextFieldInheritance(value) {
            if (typeof value !== 'string') {
                return true;
            }

            return value.length <= 0;
        },
    },
});
