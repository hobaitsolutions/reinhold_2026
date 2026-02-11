Shopware.Component.register('sw-cms-preview-alm-grid-12', () => import('./preview'));

Shopware.Component.register('sw-cms-block-alm-grid-12', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'alm-grid-12',
    label: 'sw-cms.blocks.almFoundation.block.alm-grid-12.label',
    category: 'almcode',
    component: 'sw-cms-block-alm-grid-12',
    previewComponent: 'sw-cms-preview-alm-grid-12',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        content: 'text'
    }
});
