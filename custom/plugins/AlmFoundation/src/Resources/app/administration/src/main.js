import './extension/sw-cms/component/sw-cms-sidebar';

import './module/sw-cms/blocks/almcode/alm-grid-12';
import './module/sw-cms/blocks/almcode/alm-grid-9-3';
import './module/sw-cms/blocks/almcode/alm-grid-8-4';
import './module/sw-cms/blocks/almcode/alm-grid-6-6';
import './module/sw-cms/blocks/almcode/alm-grid-4-8';
import './module/sw-cms/blocks/almcode/alm-grid-4-4-4';
import './module/sw-cms/blocks/almcode/alm-grid-3-9';
import './module/sw-cms/blocks/almcode/alm-grid-3-6-3';
import './module/sw-cms/blocks/almcode/alm-grid-3-3-3-3';
import './module/sw-cms/blocks/almcode/alm-grid-2-8-2';
import './module/sw-cms/blocks/almcode/alm-grid-1-1-1-1-1';

import deDE from './module/sw-cms/snippet/de-DE.json';
import enGB from './module/sw-cms/snippet/en-GB.json';
import nlNL from './module/sw-cms/snippet/nl-NL.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('nl-NL', nlNL);
