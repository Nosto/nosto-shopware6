import template from './nosto-job-listing.html.twig';

const {Component} = Shopware;

Component.register('nosto-job-listing', {
    template,

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
        }
    }
});
