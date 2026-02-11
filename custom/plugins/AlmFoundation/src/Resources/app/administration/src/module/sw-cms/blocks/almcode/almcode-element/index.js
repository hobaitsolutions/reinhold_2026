Shopware.Component.register('sw-cms-preview-almcode-element', () => import('./preview'));

Shopware.Component.register('sw-cms-block-almcode-element', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'almcode-element',
    label: 'almcode.almcode-element.label',
    category: 'almcode',
    component: 'sw-cms-block-almcode-element',
    previewComponent: 'sw-cms-preview-almcode-element',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        content: 'text'
    }
});
