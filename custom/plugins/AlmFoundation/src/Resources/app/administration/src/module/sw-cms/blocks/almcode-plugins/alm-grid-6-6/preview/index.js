import template from './sw-cms-preview-alm-grid-6-6.html.twig';

import './sw-cms-preview-alm-grid-6-6.scss';
import '../../preview.scss';

export default {
    template,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
