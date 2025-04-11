<template>
    <vl-form-field v-bind="$_wrapperAttributes">
        <div :class="'vlInputGroup rounded-lg ' + (showError ? invalidClass : '')">
            <input
                v-model="visualValue"
                class="vlFormControl"
                v-bind="$_attributes"
                v-on="$_events"
                :placeholder="formatDateStructure"
                @input="onInputDateNumber"
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

        validateJustFuture() { return this.$_config('validateJustFuture') },

        formatDateStructure() { return this.$_config('dateFormat') },

        rawDateNumbers() { return this.visualValue.replace(/\D/g, '') },

        quantityOfNumbersDate() { return this.formatDateStructure.replace(/[^a-zA-Z]/g, '').length },

        formatedDate() {
            return this.formatDate(this.rawDateNumbers);
        },

        rawDate() {
            const rawDateNumbers = this.rawDateNumbers;
            const formatParts = this.formatDateStructure.split(/[^a-zA-Z]+/);
            const dateParts = [];
            let index = 0;

            formatParts.forEach(part => {
                dateParts.push(rawDateNumbers.slice(index, index + part.length));
                index += part.length;
            });

            if (!dateParts.length) return null;

            const [day, month, year] = formatParts.length === 3 ? dateParts : [null, ...dateParts];

            if (formatParts.length === 3) {
                return new Date(`${month}-${day}-${year}`);
            } else {
                return new Date(`${month}-01-${year}`);
            }
        },

        showError() {
            return !this.isValidDate && !this.focused && this.dirty;
        },

        isValidDate() {
            const date = this.rawDate;

            if (!date || date == 'Invalid Date') return false;

            if (this.validateJustFuture) {
                if (date < new Date()) return false;
            }

            return true;
        }
    },
    methods: {
        focus(){
            this.$refs.input.focus()
        },
        
        onInputDateNumber() {
            this.visualValue = this.formatedDate;
            this.component.value = this.isValidDate ? this.rawDate.toLocaleDateString() : null;
        },

        onFocus() {
            this.focused = true;
            this.dirty = true;
        },
        dateToRawNumbers(date) {
            if (!date) return '';

            return (new Date(date)).toLocaleDateString().replace(/\D/g, '');
        },
        formatDate(rawDateNumbers) {
            let formatted = '';
            let index = 0;

            for (let char of this.formatDateStructure) {
                if (!rawDateNumbers[index]) break;

                if (/[^a-zA-Z]/.test(char)) {
                    formatted += char;
                } else {
                    formatted += rawDateNumbers[index] || '';
                    index++;
                }
            }

            return formatted;
        },
    },
    mounted(){
        if(this.component.value) {
            this.visualValue = this.component.value
            this.onInputDateNumber()
        }

        if(this.focusOnLoad)
            this.focus()
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
