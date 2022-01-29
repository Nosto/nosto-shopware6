import template from './nosto-integration-features-flags.html.twig';

const { Component } = Shopware;

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
        }
    },

    data() {
        return {
            isLoading: false,
            propertyGroups: []
        };
    },

    computed: {
        createRatingsAndReviewOptions() {
            return [
                {
                    label: this.$tc('nosto.configuration.featuresFlags.ratingsOptions.shopwareRatings'),
                    value: 'shopware-ratings'
                },
                {
                    label: this.$tc('nosto.configuration.featuresFlags.ratingsOptions.noRatings'),
                    value: 'no-ratings'
                }
            ]
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const configPrefix = 'NostoIntegration.feature.flags.',
                defaultConfigs = {
                    variations: true,
                    productProperties: true,
                    alternateImages: true,
                    ratingsReviews: 'shopware-ratings',
                    inventory: false,
                    customerDataToNosto: true,
                    syncInactiveProducts: false,
                    productPublishedDateTagging: false,
                    reloadRecommendations: false
                };

            /**
             * Initialize config data with default values.
             */
            for (const [key, defaultValue] of Object.entries(defaultConfigs)) {
                if (this.allConfigs['null'][configPrefix + key] === undefined) {
                    this.$set(this.allConfigs['null'], configPrefix + key, defaultValue);
                }
            }
        }
    },
});
