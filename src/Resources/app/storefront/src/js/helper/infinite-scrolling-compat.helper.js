/**
 * Compatibility with infinite-scrolling listing plugins (e.g. Tanmar Infinite Scrolling).
 *
 * Such plugins remember which listing pages were already rendered and skip rendering a
 * page again. Their own filter plugins clear that memory before a filter change reloads
 * the listing. Our filter plugins override the same Shopware plugins, so theirs never run
 * and the filtered result for page 1 would be ignored ("page 1 already loaded").
 * Call this before a filter change triggers `listing.changeListing()`.
 */
export default function resetInfiniteScrollingState(listing) {
    if (!listing || !listing._tmisActive || typeof listing._visitedPagesClear !== 'function') {
        return;
    }

    listing._tmisListingOption = 'override';
    listing._visitedPagesClear();
    listing._tmisNewPageRequestCounter = 0;
    listing._tmisIsLoading = true;
}
