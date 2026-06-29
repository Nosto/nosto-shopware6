import PluginManager from 'src/plugin-system/plugin.manager';
import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';

export default class NostoFilterPropertySelectPlugin extends PluginManager.getPlugin('FilterPropertySelect').get('class') {
    getValues() {
        const checkedCheckboxes =
            DomAccess.querySelectorAll(this.el, `${this.options.checkboxSelector}:checked`, false);

        const selection = [];

        if (checkedCheckboxes) {
            Iterator.iterate(checkedCheckboxes, (checkbox) => {
                selection.push(checkbox.value);
            });
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

        const labels = [];

        if (activeCheckboxes) {
            Iterator.iterate(activeCheckboxes, (checkbox) => {
                labels.push({
                    label: checkbox.dataset.label,
                    id: checkbox.id,
                });
            });
        }

        return labels;
    }

    setValuesFromUrl(params = {}) {
        let stateChanged = false;

        const properties = params[this.options.name];
        const ids = properties ? properties.split('|') : [];

        const uncheckItems = this.selection.filter(x => !ids.includes(x));
        const checkItems = ids.filter(x => !this.selection.includes(x));

        if (uncheckItems.length > 0 || checkItems.length > 0) {
            stateChanged = true;
        }

        checkItems.forEach(id => {
            const checkboxEl = this._getCheckboxByIdOrValue(id);

            if (checkboxEl) {
                checkboxEl.checked = true;
                this.selection.push(checkboxEl.value);
            }
        });

        uncheckItems.forEach(id => {
            this.reset(id);

            this.selection = this.selection.filter(item => item !== id);
        });

        this._updateCount();

        return stateChanged;
    }

    reset(id) {
        const checkboxEl = this._getCheckboxByIdOrValue(id);

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
            this.disableFilter();
            return;
        }

        const property = entities.find(entity => entity.translated.name === this.options.propertyName);
        if (property) {
            activeItems.push(...property.options);
        } else {
            this.disableFilter();
            return;
        }

        const actualValues = this.getValues();

        if (activeItems.length < 1 && actualValues[this.options.name].length === 0) {
            this.disableFilter();
            return;
        }
        this.enableFilter();


        this._disableInactiveFilterOptions(activeItems.map(entity => entity.id));
    }

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

    _getCheckboxByIdOrValue(id) {
        const checkboxes = DomAccess.querySelectorAll(this.el, this.options.checkboxSelector, false);

        if (!checkboxes) {
            return null;
        }

        return Array.from(checkboxes).find(checkbox => checkbox.id === id || checkbox.value === id);
    }
}
