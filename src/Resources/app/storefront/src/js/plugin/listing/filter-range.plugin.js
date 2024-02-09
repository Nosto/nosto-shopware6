import deepmerge from 'deepmerge';
import DomAccess from 'src/helper/dom-access.helper';
import FilterRange from 'src/plugin/listing/filter-range.plugin';

export default class NostoFilterSliderRange extends FilterRange {
    static options = deepmerge(FilterRange.options, {
        mainFilterButtonSelector: '.filter-panel-item-toggle',
        sliderContainer: '.nosto--range-slider',
        minInputValue: 0,
        maxInputValue: null,
        unit: '',
        shouldExtend: false,
    });

    init() {
        if (!this.options.shouldExtend) {
            super.init();
            return;
        }

        this.resetState();

        this._container = DomAccess.querySelector(this.el, this.options.containerSelector);
        this._inputMin = DomAccess.querySelector(this.el, this.options.inputMinSelector);
        this._inputMax = DomAccess.querySelector(this.el, this.options.inputMaxSelector);
        this._timeout = null;
        this._hasError = false;

        this.slider = document.createElement('div');
        this._sliderContainer = DomAccess.querySelector(this.el, this.options.sliderContainer);
        this._sliderContainer.prepend(this.slider);

        const start = this._inputMin.value.length ? this._inputMin.value : this.options.minInputValue;
        const end = this._inputMax.value.length ? this._inputMax.value : this.options.maxInputValue;
        const min = this.options.minInputValue;
        const max = this.getMax();

        noUiSlider.create(this.slider, {
            start: [start, end],
            connect: true,
            range: { min, max },
        });

        this._registerEvents();
    }

    /**
     * Reset state in case the filter was already loaded once e.g. when opening the off-canvas filter panel
     * multiple times.
     *
     * @private
     */
    resetState() {
        DomAccess.querySelector(this.el, this.options.sliderContainer).innerHTML = '';
    }

    /**
     * @private
     */
    _registerEvents() {
        if (!this.options.shouldExtend) {
            super._registerEvents();
            return;
        }

        // Register slider events
        this.slider.noUiSlider.on('update', this.onUpdateValues.bind(this));
        this.slider.noUiSlider.on('end', this._onChangeKnob.bind(this));

        this._inputMin.addEventListener('blur', this._onChangeMin.bind(this));
        this._inputMax.addEventListener('blur', this._onChangeMax.bind(this));

        this._inputMin.addEventListener('keyup', this._onInput.bind(this));
        this._inputMax.addEventListener('keyup', this._onInput.bind(this));
    }

    /**
     * @returns {number}
     */
    getMax() {
        return this.options.maxInputValue === this.options.minInputValue
            ? this.options.minInputValue + 1
            : this.options.maxInputValue;
    }

    /**
     * @return {Object}
     * @public
     */
    getValues() {
        if (!this.options.shouldExtend) {
            return super.getValues();
        }

        const values = {};

        this.validateMinInput();
        this.validateMaxInput();

        if (this.hasMinValueSet()) {
            values[this.options.minKey] = this._inputMin.value;
        }

        if (this.hasMaxValueSet()) {
            values[this.options.maxKey] = this._inputMax.value;
        }

        return values;
    }

    /**
     * @param {KeyboardEvent} e
     * @private
     */
    _onInput(e) {
        if (e.keyCode === 13) {
            e.target.blur();
        }
    }

    /**
     * @return {Array}
     * @public
     */
    getLabels() {
        if (!this.options.shouldExtend) {
            return super.getLabels();
        }

        let labels = [];

        if (this._inputMin.value.length || this._inputMax.value.length) {
            if (this.hasMinValueSet()) {
                labels.push({
                    label: `${this.options.snippets.filterRangeActiveMinLabel} ${this._inputMin.value} ${this.options.unit}`,
                    id: this.options.minKey,
                });
            }

            if (this.hasMaxValueSet()) {
                labels.push({
                    label: `${this.options.snippets.filterRangeActiveMaxLabel} ${this._inputMax.value} ${this.options.unit}`,
                    id: this.options.maxKey,
                });
            }
        } else {
            labels = [];
        }

        return labels;
    }

    /**
     * @param {Array} params
     * @public
     * @return {boolean}
     */
    setValuesFromUrl(params) {
        if (!this.options.shouldExtend) {
            return super.setValuesFromUrl(params);
        }

        let stateChanged = false;
        Object.keys(params).forEach(key => {
            if (key === this.options.minKey) {
                this._inputMin.value = params[key];
                this.validateMinInput();
                stateChanged = true;
            }
            if (key === this.options.maxKey) {
                this._inputMax.value = params[key];
                this.validateMaxInput();
                stateChanged = true;
            }
        });

        return stateChanged;
    }

    /**
     * @param {Array} values
     */
    onUpdateValues(values) {
        if (values[0] < this.options.minInputValue) {
            values[0] = this.options.minInputValue;
        }
        if (values[1] > this.options.maxInputValue) {
            values[1] = this.options.maxInputValue;
        }

        this._inputMin.value = values[0];
        this._inputMax.value = values[1];
    }

    /**
     * @param {String} id
     * @public
     */
    reset(id) {
        if (!this.options.shouldExtend) {
            super.reset(id);
            return;
        }

        if (id === this.options.minKey) {
            this.resetMin();
        }

        if (id === this.options.maxKey) {
            this.resetMax();
        }

        this._removeError();
    }

    /**
     * @public
     */
    resetAll() {
        if (!this.options.shouldExtend) {
            super.resetAll();
            return;
        }

        this.resetMin();
        this.resetMax();
        this._removeError();
    }

    validateMinInput() {
        if (!this._inputMin.value ||
            this._inputMin.value < this.options.minInputValue ||
            this._inputMin.value > this.options.maxInputValue
        ) {
            this.resetMin();
        } else {
            this.setMinKnobValue();
        }
    }

    validateMaxInput() {
        if (
            !this._inputMax.value ||
            this._inputMax.value > this.options.maxInputValue ||
            this._inputMax.value < this.options.minInputValue
        ) {
            this.resetMax();
        } else {
            this.setMaxKnobValue();
        }
    }

    resetMin() {
        this._inputMin.value = this.options.minInputValue;
        this.setMinKnobValue();
    }

    resetMax() {
        this._inputMax.value = this.options.maxInputValue;
        this.setMaxKnobValue();
    }

    /**
     * @private
     */
    _onChangeMin() {
        this.setMinKnobValue();
        this._onChangeInput();
    }

    /**
     * @private
     */
    _onChangeMax() {
        this.setMaxKnobValue();
        this._onChangeInput();
    }

    _onChangeKnob() {
        this.listing.changeListing(true, { p: 1 });
    }

    hasMinValueSet() {
        this.validateMinInput();
        return this._inputMin.value.length && parseFloat(this._inputMin.value) > this.options.minInputValue;
    }

    hasMaxValueSet() {
        this.validateMaxInput();
        return this._inputMax.value.length && parseFloat(this._inputMax.value) < this.options.maxInputValue;
    }

    setMinKnobValue() {
        if (this.slider) {
            this.slider.noUiSlider.set([this._inputMin.value, null]);
        }
    }

    setMaxKnobValue() {
        if (this.slider) {
            this.slider.noUiSlider.set([null, this._inputMax.value]);
        }
    }

    setBothKnobValues() {
        if (this.slider) {
            this.slider.noUiSlider.set([this._inputMin.value, this._inputMax.value]);
        }
    }

    refreshDisabledState(filter) {
        if (!this.options.shouldExtend) {
            return;
        }

        const properties = filter[this.options.name];
        const entities = properties.entities;

        if (!entities || !entities.length) {
            this.disableFilter();
            return;
        }

        const property = entities.find(entity => entity.translated.name === this.options.propertyName);
        if (!property) {
            this.disableFilter();
            return;
        }

        const currentSelectedPrices = this.getValues();
        const totalRange = {
            min: property.options[0].min,
            max: property.options[0].max,
        }

        if (totalRange.min === totalRange.max) {
            this.disableFilter();
            return;
        }

        if (this.options.minInputValue !== totalRange.min || this.options.maxInputValue !== totalRange.max) {
            this.updateMinAndMaxValues(totalRange.min, totalRange.max);
        } else {
            this.enableFilter();
            return;
        }

        this.updateSelectedRange(currentSelectedPrices, totalRange);

        this.enableFilter();
    }

    updateMinAndMaxValues(minPrice, maxPrice) {
        this.options.minInputValue = minPrice;
        this.options.maxInputValue = maxPrice;

        this.slider.noUiSlider.updateOptions({
            range: {
                'min': minPrice,
                'max': maxPrice,
            },
        });

        this.updateInputsAndSliderValues(minPrice, maxPrice);
    }

    updateInputsAndSliderValues(minPrice, maxPrice) {
        if (minPrice !== null) {
            this._inputMin.value = minPrice;
        }

        if (maxPrice !== null) {
            this._inputMax.value = maxPrice;
        }

        this.setBothKnobValues();
    }

    /**
     * @param {Array} currentSelectedPrices
     * @param {Object} totalRangePrices
     */
    updateSelectedRange(currentSelectedPrices, totalRangePrices) {
        const currentSelectedPriceMin = currentSelectedPrices[this.options.minKey];
        const currentSelectedPriceMax = currentSelectedPrices[this.options.maxKey];

        const updateMin = currentSelectedPriceMin && currentSelectedPriceMin >= totalRangePrices.min;
        const updateMax = currentSelectedPriceMax && currentSelectedPriceMax <= totalRangePrices.max;

        const newSelectedMin = updateMin ? currentSelectedPriceMin : null;
        const newSelectedMax = updateMax ? currentSelectedPriceMax : null;

        this.updateInputsAndSliderValues(newSelectedMin, newSelectedMax);
    }

    disableFilter() {
        const mainFilterButton = DomAccess.querySelector(this.el, this.options.mainFilterButtonSelector);
        mainFilterButton.classList.add('fl-disabled');
        mainFilterButton.setAttribute('disabled', 'disabled');
        mainFilterButton.setAttribute('title', this.options.snippets.disabledFilterText);
    }

    enableFilter() {
        const mainFilterButton = DomAccess.querySelector(this.el, this.options.mainFilterButtonSelector);
        mainFilterButton.classList.remove('fl-disabled');
        mainFilterButton.removeAttribute('disabled');
        mainFilterButton.removeAttribute('title');
    }
}
