import template from './sw-cms-preview-alm-grid-2-8-2.html.twig';

import './sw-cms-preview-alm-grid-2-8-2.scss';
import '../../preview.scss';

export default {
    template,

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
};
