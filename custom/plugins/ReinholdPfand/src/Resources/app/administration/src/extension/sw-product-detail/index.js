import template from './sw-product-detail.html.twig';

const { Component } = Shopware;

Component.override('sw-product-detail', {
    template,
    
    data() {
        return {
            ...this.$super('data'),
            pfandPrice: null
        };
    },
    
    watch: {
        product: {
            handler(product) {
                if (product) {
                    this.pfandPrice = product.pfandPrice;
                }
            },
            immediate: true
        }
    }
}); 