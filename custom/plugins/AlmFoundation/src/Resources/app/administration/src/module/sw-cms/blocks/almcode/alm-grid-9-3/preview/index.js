import template from './sw-cms-preview-alm-grid-9-3.html.twig';

import './sw-cms-preview-alm-grid-9-3.scss';
import '../../preview.scss';

export default {
    template,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
