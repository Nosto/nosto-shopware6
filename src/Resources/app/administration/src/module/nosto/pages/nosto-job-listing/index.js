/* eslint-disable sw-core-rules/require-package-annotation */
import template from './nosto-job-listing.html.twig';
import './nosto-job-listing.scss';
import './components/nosto-integration-job-listing-index';
import './components/nosto-integration-job-sub-jobs';

const { Component, Mixin } = Shopware;

/** @private */
Component.register('nosto-job-listing', {
    template,

    inject: [
        'NostoIntegrationProviderService',
        'repositoryFactory',
        'filterFactory',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            jobItems: null,
            isLoading: false,
            reloadInterval: null,
            showJobInfoModal: false,
            showJobSubsModal: false,
            currentJobID: null,
            showMessagesModal: false,
            currentJobMessages: null,
            sortType: 'status',
            jobDisplayType: null,
            autoLoad: false,
            autoLoadIsActive: false,
            autoReloadInterval: 60000,
            statusFilterOptions: [],
            typeFilterOptions: [],
            filterCriteria: [],
            defaultFilters: [
                'job-status-filter',
                'job-type-filter',
                'job-date-filter',
            ],
            storeKey: 'nosto_filters',
            activeFilterNumber: 0,
            searchConfigEntity: 'nosto_scheduler_job',
            showBulkEditModal: false,
            hideFilters: false,
        };
    },

    computed: {
        jobRepository() {
            return this.repositoryFactory.create('nosto_scheduler_job');
        },

        nostoJobTypes() {
            return [
                'nosto-integration-full-catalog-sync',
                'nosto-integration-marketing-permission-sync',
                'nosto-integration-order-sync',
                'nosto-integration-entity-changelog-sync',
                'nosto-integration-product-sync',
            ];
        },

        listFilters() {
            return this.filterFactory.create('nosto_scheduler_job', {
                'job-status-filter': {
                    property: 'status',
                    type: 'multi-select-filter',
                    label: this.$tc('nosto.job.status-filter.label'),
                    placeholder: this.$tc('nosto.job.status-filter.placeholder'),
                    valueProperty: 'value',
                    labelProperty: 'name',
                    options: this.statusFilterOptions,
                },
                'job-type-filter': {
                    property: 'name',
                    type: 'multi-select-filter',
                    label: this.$tc('nosto.job.type-filter.label'),
                    placeholder: this.$tc('nosto.job.type-filter.placeholder'),
                    valueProperty: 'value',
                    labelProperty: 'name',
                    options: this.typeFilterOptions,
                },
                'job-date-filter': {
                    property: 'createdAt',
                    label: this.$tc('nosto.job.date-filter.label'),
                    dateType: 'datetime-local',
                    fromFieldLabel: this.$tc('nosto.job.date-filter.from'),
                    toFieldLabel: this.$tc('nosto.job.date-filter.to'),
                    showTimeframe: true,
                },
            });
        },

        cancelRunningFullProductSyncJobActionTitle() {
            return this.$tc('nosto.listing.cancelRunningFullProductSyncJobActionTitle');
        },
    },

    methods: {
        onScheduleProductSync() {
            this.isLoading = true;
            this.NostoIntegrationProviderService.scheduleFullProductSync().then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('nosto.job.notification.success'),
                });
                this.onRefresh();
            }).catch((exception) => {
                this.createNotificationError({
                    message: exception?.response?.data?.errors[0]?.detail ?? this.$tc('nosto.job.notification.unknownError'),
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onCancelRunningFullProductSyncJob() {
            this.isLoading = true;
            this.NostoIntegrationProviderService.deleteRunningFullProductSyncJob().then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('nosto.job.notification.cancelSuccess'),
                });
                this.onRefresh();
            }).catch((exception) => {
                this.createNotificationError({
                    message: exception?.response?.data?.errors[0]?.detail ?? this.$tc('nosto.job.notification.unknownError'),
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onDisplayModeChange(mode) {
            const innerBox = this.$el;
            innerBox.classList.remove('no-filter');

            if (mode !== 'list') {
                innerBox.classList.add('no-filter');
                this.$refs.nostoSidebar.closeSidebar();

                if (this.$refs.nostoFilter.$el.length !== 0) {
                    this.$refs.nostoFilter.resetAll();
                }

                this.hideFilters = true;
                return;
            }

            this.hideFilters = false;
        },

        onRefresh() {
            this.$refs.jobListing.onRefresh(this.filterCriteria);
        },

        updateCriteria(criteria) {
            this.page = 1;
            this.filterCriteria = criteria;
            this.activeFilterNumber = criteria.length;
        },

        onJobListMetaLoaded({ statuses = [], types = [] } = {}) {
            this.statusFilterOptions = statuses.map((status) => {
                return {
                    name: this.$tc(`job-listing.page.listing.grid.job-status.${status}`),
                    value: status,
                };
            });

            this.typeFilterOptions = types.map((type) => {
                return {
                    name: type,
                    value: type,
                };
            });
        },
    },
});
