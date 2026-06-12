import FilterPropertySelectPlugin from 'src/plugin/listing/filter-property-select.plugin';

export default class NostoFilterPropertySelectPlugin extends FilterPropertySelectPlugin {
    refreshDisabledState(filter) {
        // Prevent disabling if propertyName is not set correctly
        if (this.options.propertyName === '') {
            return;
        }

        const activeItems = [];
        const properties = filter[this.options.name];
        const entities = properties.entities;

        if (!entities || !entities.length) {
            this._refreshOptionCounts(activeItems);
            this.disableFilter();
            return;
        }

        const property = entities.find(entity => entity.translated.name === this.options.propertyName);
        if (property) {
            activeItems.push(...property.options);
        } else {
            this._refreshOptionCounts(activeItems);
            this.disableFilter();
            return;
        }

        const actualValues = this.getValues();

        if (activeItems.length < 1 && actualValues[this.options.name].length === 0) {
            this._refreshOptionCounts(activeItems);
            this.disableFilter();
            return;
        }
        this.enableFilter();

        this._refreshOptionCounts(activeItems);
        this._disableInactiveFilterOptions(activeItems.map(entity => entity.id));
    }

    /**
     * Updates the result count rendered next to each filter option with the
     * counts of the current filter response. Options missing from the
     * response have no matching products, so their count is set to 0.
     *
     * @private
     */
    _refreshOptionCounts(activeItems) {
        const counts = {};
        activeItems.forEach(item => {
            if (typeof item.count === 'number') {
                counts[item.id] = item.count;
            }
        });

        const checkboxes = this.el.querySelectorAll(this.options.checkboxSelector);
        checkboxes.forEach(checkbox => {
            const countEl = this.el.querySelector(
                `label[for="${CSS.escape(checkbox.id)}"] .filter-option-count`,
            );
            if (!countEl) {
                return;
            }

            const count = Object.prototype.hasOwnProperty.call(counts, checkbox.id)
                ? counts[checkbox.id]
                : 0;
            countEl.textContent = `(${count})`;
        });
    }
}
