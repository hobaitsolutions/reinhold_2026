import template from './sw-cms-preview-alm-grid-4-8.html.twig';

import './sw-cms-preview-alm-grid-4-8.scss';
import '../../preview.scss';

export default {
    template,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
