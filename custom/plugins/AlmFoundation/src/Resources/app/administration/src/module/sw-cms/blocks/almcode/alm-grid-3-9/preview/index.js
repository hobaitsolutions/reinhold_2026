import template from './sw-cms-preview-alm-grid-3-9.html.twig';

import './sw-cms-preview-alm-grid-3-9.scss';
import '../../preview.scss';

export default {
    template,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
