Shopware.Component.register('sw-cms-preview-alm-grid-6-6', () => import('./preview'));

Shopware.Component.register('sw-cms-block-alm-grid-6-6', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'alm-grid-6-6',
    label: 'sw-cms.blocks.almFoundation.block.alm-grid-6-6.label',
    category: 'almcode',
    component: 'sw-cms-block-alm-grid-6-6',
    previewComponent: 'sw-cms-preview-alm-grid-6-6',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        content1: 'text',
        content2: 'text'
    }
});
