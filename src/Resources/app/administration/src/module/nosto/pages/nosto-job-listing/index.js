import template from './nosto-job-listing.html.twig';
import './nosto-job-listing.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('nosto-job-listing', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    inject: [
        'OdNostoProviderService',
        'repositoryFactory',
        'filterFactory',
        'feature',
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
                'job-date-filter'
            ],
            storeKey: 'nosto_filters',
            activeFilterNumber: 0,
            searchConfigEntity: 'od_scheduler_job',
            showBulkEditModal: false,
            hideFilters: false
        }
    },

    computed: {
        jobRepository() {
            return this.repositoryFactory.create('od_scheduler_job');
        },

        nostoJobTypes() {
            return [
                'od-nosto-full-catalog-sync',
                'od-nosto-marketing-permission-sync',
                'od-nosto-order-sync',
                'od-nosto-entity-changelog-sync',
                'od-nosto-product-sync'
            ];
        },

        listFilters() {
            return this.filterFactory.create('od_scheduler_job', {
                'job-status-filter': {
                    property: 'status',
                    type: 'multi-select-filter',
                    label: 'Job Status',
                    placeholder: 'Select status...',
                    valueProperty: 'value',
                    labelProperty: 'name',
                    options: this.statusFilterOptions
                },
                'job-type-filter': {
                    property: 'name',
                    type: 'multi-select-filter',
                    label: 'Job Type',
                    placeholder: 'Select type...',
                    valueProperty: 'value',
                    labelProperty: 'name',
                    options: this.typeFilterOptions
                },
                'job-date-filter': {
                    property: 'createdAt',
                    label: 'Job Created At',
                    dateType: 'datetime-local',
                    fromFieldLabel: 'From',
                    toFieldLabel: 'To',
                    showTimeframe: true,
                },
            });
        }
    },

    created() {
        this.createdComponent()
    },

    methods: {
        createdComponent() {
            this.loadFilterValues();
        },

        onScheduleProductSync() {
            this.isLoading = true;
            this.OdNostoProviderService.scheduleFullProductSync().then(() => {
                this.createNotificationSuccess({
                    message: 'Job has been scheduled successfully!'
                });
                this.onRefresh();
            }).catch((exception) => {
                this.createNotificationError({
                    message: exception?.response?.data?.errors[0]?.detail ?? 'Unknown error.'
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onDisplayModeChange(mode) {
            let innerBox = this.$el;
            innerBox.classList.remove('no-filter');

            if (mode !== 'list') {
                innerBox.classList.add('no-filter');
                this.$refs.odSidebar.closeSidebar();

                if (this.$refs.odFilter.$el.length !== 0){
                    this.$refs.odFilter.resetAll();
                }

                return this.hideFilters = true
            }

            this.hideFilters = false;
            this.loadFilterValues();
        },

        onRefresh() {
            this.$refs.jobListing.onRefresh(this.filterCriteria);
            this.loadFilterValues();
        },

        updateCriteria(criteria) {
            this.page = 1;
            this.filterCriteria = criteria;
            this.activeFilterNumber = criteria.length;
        },

        loadFilterValues() {
            this.filterLoading = true;

            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('parentId', null));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC', false));
            criteria.addFilter(Criteria.equalsAny('type', this.nostoJobTypes));

            return this.jobRepository.search(criteria, Shopware.Context.api).then((items) => {
                const statuses = [...new Set(items.map(item => item.status))];
                const types = [...new Set(items.map(item => item.name))];

                this.statusFilterOptions = [];
                this.typeFilterOptions = [];

                statuses.forEach((status) => {
                    this.statusFilterOptions.push({
                        name: this.$tc('job-listing.page.listing.grid.job-status.' + status),
                        value: status
                    })
                })

                types.forEach((type) => {
                    this.typeFilterOptions.push({
                        name: type,
                        value: type
                    })
                })

                this.filterLoading = false;

                return Promise.resolve();
            }).catch(() => {
                this.filterLoading = false;
            });
        },
    }
});
