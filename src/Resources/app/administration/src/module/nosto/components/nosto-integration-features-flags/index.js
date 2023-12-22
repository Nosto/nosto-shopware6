import template from './nosto-integration-features-flags.html.twig';

const { Component } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

/** @private */
Component.register('nosto-integration-features-flags', {
    template,

    inject: ['repositoryFactory'],

    props: {
        actualConfigData: {
            type: Object,
            required: true,
        },
        allConfigs: {
            type: Object,
            required: true,
        },
        configKey: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            isLoading: false,
            propertyGroups: [],
            categoryCollection: [],
        };
    },

    computed: {
        categoryRepository() {
            return this.repositoryFactory.create('category');
        },
        selectedCategories() {
            return this.actualConfigData.categoryBlocklist || this.allConfigs.null.categoryBlocklist;
        },
        selectedCategoriesCriteria() {
            const criteria = new Criteria(null, null);
            criteria.addFilter(Criteria.equalsAny('id', this.selectedCategories));

            return criteria;
        },
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
        createOldJobCleanupPeriodOptions() {
            const dayPeriods = [5, 10, 15, 20, 30, 60, 90];
            const options = [];
            const translationAfter = this.$tc('nosto.configuration.featuresFlags.oldJobCleanupPeriod.after');
            const translationDays = this.$tc('nosto.configuration.featuresFlags.oldJobCleanupPeriod.days');

            dayPeriods.forEach((period) => {
                options.push(
                    {
                        label: `${translationAfter} ${period} ${translationDays}`,
                        value: period,
                    },
                );
            });

            return options;
        },
    },

    watch: {
        configKey() {
            this.createCategoryCollection();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const defaultConfigs = {
                variations: true,
                productProperties: true,
                alternateImages: true,
                productIdentifier: 'product-id',
                ratingsReviews: 'shopware-ratings',
                stockField: 'available-stock',
                crossSellingSync: 'no-sync',
                categoryNaming: 'no-id',
                categoryBlocklist: [],
                inventory: false,
                customerDataToNosto: true,
                syncInactiveProducts: false,
                productPublishedDateTagging: false,
                reloadRecommendations: false,
                enableLabelling: false,
                notLoggedInCache: false,
                dailySynchronizationTime: false,
                domain: null,
                oldJobCleanup: false,
                oldJobCleanupPeriod: 5,
            };

            /**
             * Initialize config data with default values.
             */
            Object.entries(defaultConfigs).forEach(([key, defaultValue]) => {
                if (this.allConfigs.null[key] === undefined) {
                    this.$set(this.allConfigs.null, key, defaultValue);
                }
            });

            this.createCategoryCollection();
        },

        async createCategoryCollection() {
            this.categoryCollection = this.selectedCategories?.length
                ? await this.categoryRepository.search(this.selectedCategoriesCriteria, Shopware.Context.api)
                : new EntityCollection(
                    this.categoryRepository.route,
                    this.categoryRepository.entityName,
                    Shopware.Context.api,
                );
        },

        onCategoryAdd(item) {
            if (this.actualConfigData.categoryBlocklist) {
                this.$set(
                    this.actualConfigData,
                    'categoryBlocklist',
                    [...this.actualConfigData.categoryBlocklist, item.id],
                );
            } else {
                this.$set(this.actualConfigData, 'categoryBlocklist', [item.id]);
            }
        },

        onCategoryRemove(item) {
            this.$set(
                this.actualConfigData,
                'categoryBlocklist',
                this.actualConfigData.categoryBlocklist.filter(
                    categoryId => categoryId !== item.id,
                ),
            );
        },
    },
});
