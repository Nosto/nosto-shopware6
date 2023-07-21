import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'od-nosto',
    label: 'Nosto',
    category: 'od-nosto',
    component: 'sw-cms-block-od-nosto',
    previewComponent: 'sw-cms-preview-od-nosto',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        od_nosto: 'od-nosto',
    }
});
