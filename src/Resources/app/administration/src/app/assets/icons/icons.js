/* eslint-disable sw-core-rules/require-package-annotation */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */
const icons = import.meta.glob('./svg/*.svg', { eager: true });

export default Object.keys(icons).map((path) => {
    const componentName = path.replace(/^\.\/svg\/|\.svg$/g, '');

    return {
        name: componentName,
        functional: true,
        render(createElement, elementContext) {
            const data = elementContext.data;

            return createElement('span', {
                class: [data.staticClass, data.class],
                style: data.style,
                attrs: data.attrs,
                on: data.on,
                domProps: {
                    innerHTML: icons[path].default, // Use the default export for the SVG file
                },
            });
        },
    };
});
