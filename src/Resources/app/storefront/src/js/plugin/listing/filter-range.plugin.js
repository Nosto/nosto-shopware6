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
        decimalEpsilon: 0.000001,
        decimalPlaces: 2,
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

        const start = this._inputMin.value.length
            ? this._normalizePrecision(this._inputMin.value)
            : this._normalizePrecision(this.options.minInputValue);
        const end = this._inputMax.value.length
            ? this._normalizePrecision(this._inputMax.value)
            : this._normalizePrecision(this.options.maxInputValue);
        const min = this._normalizePrecision(this.options.minInputValue);
        const max = this._normalizePrecision(this.getMax());

        this.options.minInputValue = min;
        this.options.maxInputValue = max;

        noUiSlider.create(this.slider, {
            start: [start, end],
            connect: true,
            range: { min, max },
            step: this._getSliderStep(),
            format: {
                to: this._formatSliderValue.bind(this),
                from: this._toNumber.bind(this),
            },
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
        const min = this._normalizePrecision(this.options.minInputValue);
        const max = this._normalizePrecision(this.options.maxInputValue);

        return this._isEqual(max, min) ? min + 1 : max;
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
            values[this.options.minKey] = this._getExpandedMinQueryValue(this._inputMin.value);
        }

        if (this.hasMaxValueSet()) {
            values[this.options.maxKey] = this._getExpandedMaxQueryValue(this._inputMax.value);
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
                this._inputMin.value = this._normalizePrecision(params[key]);
                this.validateMinInput();
                stateChanged = true;
            }
            if (key === this.options.maxKey) {
                this._inputMax.value = this._normalizePrecision(params[key]);
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
        const minValue = this._normalizePrecision(values[0]);
        const maxValue = this._normalizePrecision(values[1]);
        const rangeMin = this._normalizePrecision(this.options.minInputValue);
        const rangeMax = this._normalizePrecision(this.options.maxInputValue);

        let safeMin = minValue;
        let safeMax = maxValue;

        if (this._isLess(safeMin, rangeMin)) {
            safeMin = rangeMin;
        }
        if (this._isGreater(safeMax, rangeMax)) {
            safeMax = rangeMax;
        }

        this._inputMin.value = this._normalizePrecision(safeMin);
        this._inputMax.value = this._normalizePrecision(safeMax);
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
        const value = this._toNumber(this._inputMin.value);
        const min = this._normalizePrecision(this.options.minInputValue);
        const max = this._normalizePrecision(this.options.maxInputValue);

        if (!this._inputMin.value ||
            this._isLess(value, min) ||
            this._isGreater(value, max)
        ) {
            this.resetMin();
        } else {
            this.setMinKnobValue();
        }
    }

    validateMaxInput() {
        const value = this._toNumber(this._inputMax.value);
        const min = this._normalizePrecision(this.options.minInputValue);
        const max = this._normalizePrecision(this.options.maxInputValue);

        if (
            !this._inputMax.value ||
            this._isGreater(value, max) ||
            this._isLess(value, min)
        ) {
            this.resetMax();
        } else {
            this.setMaxKnobValue();
        }
    }

    resetMin() {
        this._inputMin.value = this._normalizePrecision(this.options.minInputValue);
        this.setMinKnobValue();
    }

    resetMax() {
        this._inputMax.value = this._normalizePrecision(this.options.maxInputValue);
        this.setMaxKnobValue();
    }

    /**
     * @private
     */
    _onChangeMin() {
        this._inputMin.value = this._normalizePrecision(this._inputMin.value);
        this.setMinKnobValue();
        this._onChangeInput();
    }

    /**
     * @private
     */
    _onChangeMax() {
        this._inputMax.value = this._normalizePrecision(this._inputMax.value);
        this.setMaxKnobValue();
        this._onChangeInput();
    }

    _onChangeKnob() {
        this.listing.changeListing(true, { p: 1 });
    }

    hasMinValueSet() {
        this.validateMinInput();
        const value = this._toNumber(this._inputMin.value);
        const min = this._normalizePrecision(this.options.minInputValue);

        return this._inputMin.value.length && this._isGreater(value, min);
    }

    hasMaxValueSet() {
        this.validateMaxInput();
        const value = this._toNumber(this._inputMax.value);
        const max = this._normalizePrecision(this.options.maxInputValue);

        return this._inputMax.value.length && this._isLess(value, max);
    }

    setMinKnobValue() {
        if (this.slider) {
            const min = this._normalizePrecision(this._inputMin.value);
            if (min === null) {
                return;
            }

            this._inputMin.value = min;
            this.slider.noUiSlider.set([min, null]);
        }
    }

    setMaxKnobValue() {
        if (this.slider) {
            const max = this._normalizePrecision(this._inputMax.value);
            if (max === null) {
                return;
            }

            this._inputMax.value = max;
            this.slider.noUiSlider.set([null, max]);
        }
    }

    setBothKnobValues() {
        if (this.slider) {
            const min = this._normalizePrecision(this._inputMin.value);
            const max = this._normalizePrecision(this._inputMax.value);

            if (min === null || max === null) {
                return;
            }

            this._inputMin.value = min;
            this._inputMax.value = max;
            this.slider.noUiSlider.set([min, max]);
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

        const currentSelectedPrices = this._getSelectedDisplayValues();
        const totalRange = {
            min: this._normalizePrecision(property.options[0].min),
            max: this._normalizePrecision(property.options[0].max),
        }

        if (this._isEqual(totalRange.min, totalRange.max)) {
            this.disableFilter();
            return;
        }

        const currentMin = this._normalizePrecision(this.options.minInputValue);
        const currentMax = this._normalizePrecision(this.options.maxInputValue);

        if (!this._isEqual(currentMin, totalRange.min) || !this._isEqual(currentMax, totalRange.max)) {
            this.updateMinAndMaxValues(totalRange.min, totalRange.max);
        } else {
            this.enableFilter();
            return;
        }

        this.updateSelectedRange(currentSelectedPrices, totalRange);

        this.enableFilter();
    }

    updateMinAndMaxValues(minPrice, maxPrice) {
        const normalizedMin = this._normalizePrecision(minPrice);
        const normalizedMax = this._normalizePrecision(maxPrice);

        this.options.minInputValue = normalizedMin;
        this.options.maxInputValue = normalizedMax;

        this.slider.noUiSlider.updateOptions({
            range: {
                'min': normalizedMin,
                'max': normalizedMax,
            },
            step: this._getSliderStep(),
            format: {
                to: this._formatSliderValue.bind(this),
                from: this._toNumber.bind(this),
            },
        });

        this.updateInputsAndSliderValues(normalizedMin, normalizedMax);
    }

    updateInputsAndSliderValues(minPrice, maxPrice) {
        if (minPrice !== null) {
            this._inputMin.value = this._normalizePrecision(minPrice);
        }

        if (maxPrice !== null) {
            this._inputMax.value = this._normalizePrecision(maxPrice);
        }

        this.setBothKnobValues();
    }

    /**
     * @param {Array} currentSelectedPrices
     * @param {Object} totalRangePrices
     */
    updateSelectedRange(currentSelectedPrices, totalRangePrices) {
        const currentSelectedPriceMin = this._normalizePrecision(currentSelectedPrices[this.options.minKey]);
        const currentSelectedPriceMax = this._normalizePrecision(currentSelectedPrices[this.options.maxKey]);

        const updateMin = currentSelectedPriceMin !== null && !this._isLess(currentSelectedPriceMin, totalRangePrices.min);
        const updateMax = currentSelectedPriceMax !== null && !this._isGreater(currentSelectedPriceMax, totalRangePrices.max);

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

    _toNumber(value) {
        const numeric = typeof value === 'number' ? value : parseFloat(value);

        return Number.isFinite(numeric) ? numeric : null;
    }

    _isEqual(a, b) {
        if (a === null || b === null) {
            return false;
        }

        return Math.abs(a - b) <= this.options.decimalEpsilon;
    }

    _isLess(a, b) {
        if (a === null || b === null) {
            return false;
        }

        return a < (b - this.options.decimalEpsilon);
    }

    _isGreater(a, b) {
        if (a === null || b === null) {
            return false;
        }

        return a > (b + this.options.decimalEpsilon);
    }

    _formatSliderValue(value) {
        const numeric = this._normalizePrecision(value);

        if (numeric === null) {
            return '';
        }

        return `${numeric}`;
    }

    _getSliderStep() {
        const precision = Number.isInteger(this.options.decimalPlaces) ? this.options.decimalPlaces : 2;
        return 1 / Math.pow(10, precision);
    }

    _getExpandedMinQueryValue(value) {
        const numeric = this._toNumber(value);
        if (numeric === null) {
            return '';
        }

        const expanded = numeric - this._getHalfStep();
        return this._normalizeQueryPrecision(expanded);
    }

    _getExpandedMaxQueryValue(value) {
        const numeric = this._toNumber(value);
        if (numeric === null) {
            return '';
        }

        const expanded = numeric + this._getHalfStep() - this.options.decimalEpsilon;
        return this._normalizeQueryPrecision(expanded);
    }

    _getHalfStep() {
        return this._getSliderStep() / 2;
    }

    _getSelectedDisplayValues() {
        const values = {};

        if (this.hasMinValueSet()) {
            values[this.options.minKey] = this._normalizePrecision(this._inputMin.value);
        }

        if (this.hasMaxValueSet()) {
            values[this.options.maxKey] = this._normalizePrecision(this._inputMax.value);
        }

        return values;
    }

    _normalizeQueryPrecision(value) {
        const numeric = this._toNumber(value);
        if (numeric === null) {
            return null;
        }

        const precision = Number.isInteger(this.options.decimalPlaces) ? this.options.decimalPlaces : 2;
        const queryPrecision = precision + 4;
        return Number(numeric.toFixed(queryPrecision));
    }

    _normalizePrecision(value) {
        const numeric = this._toNumber(value);

        if (numeric === null) {
            return null;
        }

        const precision = Number.isInteger(this.options.decimalPlaces) ? this.options.decimalPlaces : 2;
        return Number(numeric.toFixed(precision));
    }
}
