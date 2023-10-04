import ListingPlugin from 'src/plugin/listing/listing.plugin';

export default class NostoListingPlugin extends ListingPlugin {
    _getDisabledFiltersParamsFromParams(params) {
        const order = params.order;
        const filterParams = super._getDisabledFiltersParamsFromParams(params);
        if (order === 'od-recommendation') {
            filterParams['order'] = order;
        }
        return filterParams;
    }
}
