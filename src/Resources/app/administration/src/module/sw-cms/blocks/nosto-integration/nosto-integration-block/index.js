import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'nosto-integration',
    label: 'Nosto',
    category: 'nosto-integration',
    component: 'sw-cms-block-nosto-integration',
    previewComponent: 'sw-cms-preview-nosto-integration',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        nosto_integration: 'nosto-integration',
    },
});
