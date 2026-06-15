import FilterPropertySelectPlugin from 'src/plugin/listing/filter-property-select.plugin';
import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';

/**
 * The DOM id of each checkbox is prefixed with the facet id to stay unique across
 * filters that share option values (e.g. "Black" in colour and material). The raw
 * option value lives in the value attribute, so unlike the parent plugin, all
 * value-related logic here reads checkbox.value instead of checkbox.id.
 */
export default class NostoFilterPropertySelectPlugin extends FilterPropertySelectPlugin {
    getValues() {
        const checkedCheckboxes =
            DomAccess.querySelectorAll(this.el, `${this.options.checkboxSelector}:checked`, false);

        let selection = [];

        if (checkedCheckboxes) {
            Iterator.iterate(checkedCheckboxes, (checkbox) => {
                selection.push(checkbox.value);
            });
        } else {
            selection = [];
        }

        this.selection = selection;
        this._updateCount();

        const values = {};
        values[this.options.name] = selection;

        return values;
    }

    getLabels() {
        const activeCheckboxes =
            DomAccess.querySelectorAll(this.el, `${this.options.checkboxSelector}:checked`, false);

        let labels = [];

        if (activeCheckboxes) {
            Iterator.iterate(activeCheckboxes, (checkbox) => {
                labels.push({
                    label: checkbox.dataset.label,
                    id: checkbox.value,
                    previewHex: checkbox.dataset.previewHex,
                    previewImageUrl: checkbox.dataset.previewImageUrl,
                });
            });
        } else {
            labels = [];
        }

        return labels;
    }

    setValuesFromUrl(params = {}) {
        let stateChanged = false;

        const properties = params[this.options.name];

        const values = properties ? properties.split('|') : [];

        const uncheckItems = this.selection.filter(x => !values.includes(x));
        const checkItems = values.filter(x => !this.selection.includes(x));

        if (uncheckItems.length > 0 || checkItems.length > 0) {
            stateChanged = true;
        }

        checkItems.forEach(value => {
            const checkboxEl = this._findCheckboxByValue(value);

            if (checkboxEl) {
                checkboxEl.checked = true;
                this.selection.push(checkboxEl.value);
            }
        });

        uncheckItems.forEach(value => {
            this.reset(value);

            this.selection = this.selection.filter(item => item !== value);
        });

        this._updateCount();

        return stateChanged;
    }

    reset(value) {
        const checkboxEl = this._findCheckboxByValue(value);

        if (checkboxEl) {
            checkboxEl.checked = false;
        }
    }

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
     * Same as the parent implementation, but options are matched by value
     * instead of by DOM id.
     *
     * @private
     */
    _disableInactiveFilterOptions(activeItemIds) {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector);
        Iterator.iterate(checkboxes, (checkbox) => {
            if (checkbox.checked === true) {
                return;
            }

            if (activeItemIds.includes(checkbox.value)) {
                this.enableOption(checkbox);
            } else {
                this.disableOption(checkbox);
            }
        });
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
            const countEl = this.el.querySelector(`label[for="${CSS.escape(checkbox.id)}"] .filter-option-count`);
            if (!countEl) {
                return;
            }

            const count = Object.prototype.hasOwnProperty.call(counts, checkbox.value)
                ? counts[checkbox.value]
                : 0;
            countEl.textContent = `(${count})`;
        });
    }

    /**
     * @private
     */
    _findCheckboxByValue(value) {
        return this.el.querySelector(
            `${this.options.checkboxSelector}[value="${CSS.escape(value)}"]`
        );
    }
}
