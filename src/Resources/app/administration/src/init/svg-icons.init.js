import iconComponents from '../app/assets/icons/icons';

const { Component } = Shopware;

/** @private */
export default (() => {
    return iconComponents.map((component) => {
        return Component.register(component.name, component);
    });
})();
