import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'od-nosto',
    label: 'Nosto',
    component: 'sw-cms-el-od-nosto',
    configComponent: 'sw-cms-el-config-od-nosto',
    previewComponent: 'sw-cms-el-preview-od-nosto',
    defaultConfig: {
        nostoElementID: {
            source: 'static',
            value: ''
        }
    }
});
