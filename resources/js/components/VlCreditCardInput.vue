<template>
    <vl-form-field v-bind="$_wrapperAttributes">
        <div :class="'vlInputGroup rounded-lg ' + (showError ? invalidClass : '')">
            <input
                v-model="visualValue"
                class="vlFormControl"
                v-bind="$_attributes"
                v-on="$_events"
                placeholder="1234 1234 1234 1234"
                @input="onInputCardNumber"
                @focus="onFocus"
                @blur="focused = false"
                ref="input"
            />
            <div class="justify-center flex items-center px-4" v-if="showError">
                <span class="invalid-icon">!</span>
            </div>
        </div>
    </vl-form-field>
</template>

<script>
import Field from 'vue-kompo/js/form/mixins/Field'
import HasInputAttributes from 'vue-kompo/js/form/mixins/HasInputAttributes'

export default {
    mixins: [Field, HasInputAttributes],
    data() {
        return {
            visualValue: this.component?.value || '',
            focused: false,
            dirty: false
        }
    },
    computed: {
        $_attributes() {
            return {
                ...this.$_defaultFieldAttributes,
                ...this.$_defaultInputAttributes
            }
        },
        focusOnLoad(){ return this.$_config('focusOnLoad') },
        invalidClass() { return this.$_config('invalidClass')},

        rawCardNumbers() { return this.visualValue.replaceAll(/\D/g, '') },

        formatedCardNumber() {
            const rawNumbers = this.rawCardNumbers;

            return this.performNumberFormatting(rawNumbers);
        },

        showError() {
            return !this.isValidCard && !this.focused && this.dirty;
        },

        isValidCard() {
            const cardNumber = this.rawCardNumbers;
            const amex = /^3[47][0-9]{13}$/;
            const visa = /^4[0-9]{12}(?:[0-9]{3})?$/;
            const mastercard = /^5[1-5][0-9]{14}$/;
            const discover = /^6(?:011|5[0-9]{2})[0-9]{12}$/;

            return amex.test(cardNumber) || visa.test(cardNumber) || mastercard.test(cardNumber) || discover.test(cardNumber);
        }
    },
    methods: {
        focus(){
            this.$refs.input.focus()
        },

        performNumberFormatting(number){
            return number.slice(0, 16).replaceAll(/(.{4})/g, '$1 ').trim();
        },

        onInputCardNumber() {
            this.visualValue = this.formatedCardNumber;
            this.component.value = this.rawCardNumbers;
        },

        onFocus() {
            this.focused = true;
            this.dirty = true;
        }
    },
    mounted(){
        if(this.focusOnLoad)
            this.focus()

        if(this.component.value) {
            this.visualValue = this.performNumberFormatting(this.component.value)
        }
    },
}
</script>

<style scoped>
.invalid-icon {
    color: red;
    font-weight: bold;
    margin-left: 8px;
    cursor: pointer;
}
</style>
