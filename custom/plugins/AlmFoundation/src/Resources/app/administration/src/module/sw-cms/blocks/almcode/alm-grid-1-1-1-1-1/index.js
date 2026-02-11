Shopware.Component.register('sw-cms-preview-alm-grid-1-1-1-1-1', () => import('./preview'));

Shopware.Component.register('sw-cms-block-alm-grid-1-1-1-1-1', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'alm-grid-1-1-1-1-1',
    label: 'sw-cms.blocks.almFoundation.block.alm-grid-1-1-1-1-1.label',
    category: 'almcode',
    component: 'sw-cms-block-alm-grid-1-1-1-1-1',
    previewComponent: 'sw-cms-preview-alm-grid-1-1-1-1-1',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        content1: 'text',
        content2: 'text',
        content3: 'text',
        content4: 'text',
        content5: 'text'
    }
});
