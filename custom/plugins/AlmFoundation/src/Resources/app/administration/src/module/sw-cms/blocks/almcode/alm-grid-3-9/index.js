Shopware.Component.register('sw-cms-preview-alm-grid-3-9', () => import('./preview'));

Shopware.Component.register('sw-cms-block-alm-grid-3-9', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'alm-grid-3-9',
    label: 'sw-cms.blocks.almFoundation.block.alm-grid-3-9.label',
    category: 'almcode',
    component: 'sw-cms-block-alm-grid-3-9',
    previewComponent: 'sw-cms-preview-alm-grid-3-9',
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
