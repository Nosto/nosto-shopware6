import template from './nosto-integration-features-flags.html.twig';

const { Component } = Shopware;

/** @private */
Component.register('nosto-integration-features-flags', {
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
        },
    },

    data() {
        return {
            isLoading: false,
            propertyGroups: [],
        };
    },

    computed: {
        createProductIdentifierOptions() {
            return [
                {
                    label: this.$tc('nosto.configuration.featuresFlags.productIdentifierOptions.productId'),
                    value: 'product-id',
                },
                {
                    label: this.$tc('nosto.configuration.featuresFlags.productIdentifierOptions.productNumber'),
                    value: 'product-number',
                },
            ];
        },
        createRatingsAndReviewOptions() {
            return [
                {
                    label: this.$tc('nosto.configuration.featuresFlags.ratingsOptions.shopwareRatings'),
                    value: 'shopware-ratings',
                },
                {
                    label: this.$tc('nosto.configuration.featuresFlags.ratingsOptions.noRatings'),
                    value: 'no-ratings',
                },
            ];
        },
        createStockOptions() {
            return [
                {
                    label: this.$tc('sw-product.settingsForm.labelAvailableStock'),
                    value: 'available-stock',
                },
                {
                    label: this.$tc('sw-product.settingsForm.labelStock'),
                    value: 'actual-stock',
                },
            ];
        },
        createCrossSellingSyncOptions() {
            return [
                {
                    label: this.$tc('nosto.configuration.featuresFlags.crossSellingSyncOptions.noSync'),
                    value: 'no-sync',
                },
                {
                    label: this.$tc('nosto.configuration.featuresFlags.crossSellingSyncOptions.onlyActiveSync'),
                    value: 'only-active-sync',
                },
                {
                    label: this.$tc('nosto.configuration.featuresFlags.crossSellingSyncOptions.allSync'),
                    value: 'all-sync',
                },
            ];
        },
        createCategoryNamingOptions() {
            return [
                {
                    label: this.$tc('nosto.configuration.featuresFlags.categoryNamingOptions.nameWithoutId'),
                    value: 'no-id',
                },
                {
                    label: this.$tc('nosto.configuration.featuresFlags.categoryNamingOptions.nameWithId'),
                    value: 'with-id',
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const configPrefix = 'NostoIntegration.config.';
            const defaultConfigs = {
                variations: true,
                productProperties: true,
                alternateImages: true,
                productIdentifier: 'product-id',
                ratingsReviews: 'shopware-ratings',
                crossSellingSync: 'no-sync',
                categoryNaming: 'no-id',
                inventory: false,
                customerDataToNosto: true,
                syncInactiveProducts: false,
                productPublishedDateTagging: false,
                reloadRecommendations: false,
                enableLabelling: false,
                notLoggedInCache: false,
                dailySynchronizationTime: false,
                domain: null,
            };

            /**
             * Initialize config data with default values.
             */
            Object.entries(defaultConfigs).forEach(([key, defaultValue]) => {
                if (this.allConfigs.null[configPrefix + key] === undefined) {
                    this.$set(this.allConfigs.null, configPrefix + key, defaultValue);
                }
            });
        },
    },
});
