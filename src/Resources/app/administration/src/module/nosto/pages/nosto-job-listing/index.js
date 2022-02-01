import template from './nosto-job-listing.html.twig';

const {Component, Mixin} = Shopware;;

Component.register('nosto-job-listing', {
    template,

    inject: ['OdNostoProviderService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false
        }
    },

    computed: {
        nostoJobTypes() {
            return [
                'od-nosto-full-catalog-sync',
                'od-nosto-marketing-permission-sync',
                'od-nosto-order-sync',
                'od-nosto-entity-changelog-sync',
                'od-nosto-product-sync'
            ];
        },
    },

    methods: {
        onRefresh: function () {
            this.$refs.jobListing.onRefresh();
        },

        onScheduleProductSync() {
            this.isLoading = true;
            this.OdNostoProviderService.index('', {}).then(response => {
                this.createNotificationSuccess({
                    message: 'Success!'
                });
            }).catch((exception) => {
                this.createNotificationError({
                    message: 'Something went wrong!'
                });
            }).finally(() => {
                this.isLoading = false;
            });
        }
    }
});
