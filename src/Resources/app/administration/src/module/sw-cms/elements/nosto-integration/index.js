import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'nosto-integration',
    label: 'Nosto',
    component: 'sw-cms-el-nosto-integration',
    configComponent: 'sw-cms-el-config-nosto-integration',
    previewComponent: 'sw-cms-el-preview-nosto-integration',
    defaultConfig: {
        nostoElementID: {
            source: 'static',
            value: ''
        }
    }
});
