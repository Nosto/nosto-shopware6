/**
 * @sw-package discovery
 */

const { Criteria } = Shopware.Data;

function toArray(collection) {
    const items = [];
    collection.forEach((item) => items.push(item));

    return items;
}

/**
 * @private
 */
export default function fetchJobMessages({ messageRepository, jobId, expectedTotal = 0, pageSize = 250 }) {
    const loadPage = (page, collected) => {
        const criteria = new Criteria(page, pageSize);
        criteria.addFilter(Criteria.equals('jobId', jobId));
        criteria.addSorting(Criteria.sort('createdAt', 'ASC', false));

        return messageRepository.search(criteria, Shopware.Context.api).then((messages) => {
            const pageItems = toArray(messages);
            const merged = [...collected, ...pageItems];
            const loadedAllExpected = expectedTotal > 0 && merged.length >= expectedTotal;
            const hasMorePages = pageItems.length === pageSize;

            if (!loadedAllExpected && hasMorePages) {
                return loadPage(page + 1, merged);
            }

            return merged;
        });
    };

    return loadPage(1, []);
}
