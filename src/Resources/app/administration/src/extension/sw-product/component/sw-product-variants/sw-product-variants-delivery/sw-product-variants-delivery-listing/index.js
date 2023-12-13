import template from './sw-product-variants-delivery-listing.html.twig';

Shopware.Component.override('sw-product-variants-delivery-listing', {
    template,

    computed: {
        mainVariantModeOptions() {
            return [
                {
                    value: 'displayParent',
                    name: this.$tc('sw-product.variations.deliveryModal.listingLabelModeDisplayParent'),
                },
                {
                    value: 'displayCheapestVariant',
                    name: this.$tc('sw-product.variations.deliveryModal.listingLabelModeDisplayCheapestVariant'),
                    helpText: this.$tc('sw-product.variations.deliveryModal.listingLabelModeDisplayCheapestVariantHelpText'),
                },
                {
                    value: 'displayMainVariant',
                    name: this.$tc('sw-product.variations.deliveryModal.listingLabelMainVariant'),
                },
            ];
        },
        listingMode() {
            const displayParent = this.product.variantListingConfig.displayParent;
            const displayCheapestVariant = this.product.variantListingConfig.displayCheapestVariant;

            return this.mainVariant || displayParent === true || displayCheapestVariant === true
                ? 'single'
                : 'expanded';
        },
    },

    methods: {
        getInitialVariantMode() {
            if (this.product.variantListingConfig.displayParent) {
                return 'displayParent';
            }

            if (this.product.variantListingConfig.displayCheapestVariant) {
                return 'displayCheapestVariant';
            }

            return 'displayMainVariant';
        },
        updateVariantMode(value) {
            this.product.variantListingConfig.displayParent = false;
            this.product.variantListingConfig.displayCheapestVariant = false;
            this.product.variantListingConfig.displayMainVariant = false;

            this.product.variantListingConfig[value] = true;
        },
        updateListingMode(value) {
            if (value === 'expanded') {
                this.product.variantListingConfig.displayParent = true;
                this.product.variantListingConfig.displayCheapestVariant = false;
                this.product.variantListingConfig.displayMainVariant = false;
            }

            this.product.listingMode = value;
        },
    },
});
