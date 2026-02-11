import template from './sw-cms-preview-alm-grid-8-4.html.twig';

import './sw-cms-preview-alm-grid-8-4.scss';
import '../../preview.scss';

export default {
    template,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
