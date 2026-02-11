import template from './pfand-field.html.twig';

Shopware.Component.register('pfand-field', {
    template,

    props: {
        value: {
            type: Number,
            required: false,
            default: null
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            pfandPrice: this.value
        };
    },

    watch: {
        value(newValue) {
            this.pfandPrice = newValue;
        },
        pfandPrice(newValue) {
            this.$emit('input', newValue);
        }
    }
}); 