<template>
    <vl-form-field v-bind="$_wrapperAttributes">
        <div :class="'vlInputGroup rounded-lg ' + (showError ? invalidClass : '')">
            <input :value="visualValue" class="vlFormControl" v-bind="$_attributes" v-on="$_events"
                @input="onInputValue" @focus="onFocus" @blur="focused = false" ref="input" />
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
            rawValue: this.component?.value || '',
            pastValue: this.component?.value || '',
            focused: false,
            dirty: false
        }
    },
    watch: {
        'component.value'(value) {
            this.rawValue = value ?? '';
            this.pastValue = value ?? '';
        }
    },
    computed: {
        visualValue() {
            return this.formatToModel(this.rawValue)
        },
        $_attributes() {
            return {
                ...this.$_defaultFieldAttributes,
                ...this.$_defaultInputAttributes
            }
        },
        focusOnLoad() { return this.$_config('focusOnLoad') },
        invalidClass() { return this.$_config('invalidClass') },
        formatModels() { return this.$_config('formatModels') },
        validateFormat() { return this.$_config('validateFormat') },
        allowFormat() { return this.$_config('allowFormat') },
        showError() {
            return !this.isValidValue && !this.focused && this.dirty;
        },
        isValidValue() {
            return (new RegExp(this.validateFormat)).test(this.component?.value || '')
        }
    },
    methods: {
        onInputValue(e) {
            const input = e.target;
            const oldFormatted = this.visualValue;
            const newFormatted = input.value;
            const oldRaw = this.rawValue;
            const cursorPos = input.selectionStart;
            const selectionEnd = input.selectionEnd;

            // Detect operation type
            const operation = this.detectOperation(oldFormatted, newFormatted, cursorPos, selectionEnd);

            // Extract raw value considering the operation
            const extractionResult = this.extractRawValue(newFormatted, oldFormatted, oldRaw, operation);

            if (!extractionResult.valid) {
                // Restore previous state
                this.$nextTick(() => {
                    input.value = oldFormatted;
                    input.setSelectionRange(operation.originalCursor, operation.originalCursor);
                });
                return;
            }

            // Update raw value
            this.rawValue = extractionResult.raw;
            this.component.value = extractionResult.raw;

            // Calculate new cursor position
            this.$nextTick(() => {
                const formattedValue = this.formatToModel(extractionResult.raw);
                const newCursorPos = this.calculateNewCursorPosition(
                    operation,
                    extractionResult.raw,
                    formattedValue,
                    extractionResult.rawCursorPos
                );

                input.value = formattedValue;
                input.setSelectionRange(newCursorPos, newCursorPos);
            });
        },

        detectOperation(oldFormatted, newFormatted, cursorPos, selectionEnd) {
            const lengthDiff = newFormatted.length - oldFormatted.length;
            const hasSelection = selectionEnd > cursorPos;

            return {
                type: lengthDiff > 0 ? 'insert' : lengthDiff < 0 ? 'delete' : 'replace',
                lengthDiff,
                cursorPos,
                selectionEnd,
                hasSelection,
                originalCursor: cursorPos
            };
        },

        extractRawValue(newFormatted, oldFormatted, oldRaw, operation) {
            let raw = '';
            let rawCursorPos = 0;

            // For delete operations, check if we're deleting at a format boundary
            if (operation.type === 'delete' && operation.lengthDiff === -1) {
                // Find what was deleted
                let deletedIndex = -1;
                for (let i = 0; i < oldFormatted.length; i++) {
                    if (i >= newFormatted.length || oldFormatted[i] !== newFormatted[i]) {
                        deletedIndex = i;
                        break;
                    }
                }

                const deletedChar = oldFormatted[deletedIndex];
                const isFormattingChar = !(new RegExp(this.allowFormat)).test(deletedChar);

                if (isFormattingChar) {
                    // A formatting character was deleted
                    let rawIndexBeforeDeletion = 0;
                    for (let i = 0; i < deletedIndex; i++) {
                        if ((new RegExp(this.allowFormat)).test(oldFormatted[i])) {
                            rawIndexBeforeDeletion++;
                        }
                    }

                    raw = oldRaw.slice(0, rawIndexBeforeDeletion - 1) + oldRaw.slice(rawIndexBeforeDeletion);
                    rawCursorPos = rawIndexBeforeDeletion - 1;
                } else {
                    // Normal character deletion
                    // Calculate raw position where deletion occurred
                    let rawDeletePos = 0;
                    for (let i = 0; i < deletedIndex; i++) {
                        if ((new RegExp(this.allowFormat)).test(oldFormatted[i])) {
                            rawDeletePos++;
                        }
                    }

                    // Build new raw by removing character at rawDeletePos
                    raw = oldRaw.slice(0, rawDeletePos) + oldRaw.slice(rawDeletePos + 1);
                    rawCursorPos = rawDeletePos; // Cursor stays at deletion point
                }
            } else {
                // Insert or replace - normal extraction
                for (let i = 0; i < newFormatted.length; i++) {
                    const char = newFormatted[i];
                    if ((new RegExp(this.allowFormat)).test(char)) {
                        raw += char;
                        if (i < operation.cursorPos) {
                            rawCursorPos++;
                        }
                    }
                }
            }

            const isValid = (new RegExp(this.allowFormat)).test(raw || '');

            return {
                raw,
                rawCursorPos,
                valid: isValid
            };
        },

        buildFormattedToRawMapping(formatted, raw) {
            const mapping = [];
            let rawIndex = 0;

            for (let i = 0; i < formatted.length; i++) {
                const char = formatted[i];
                const isRawChar = (new RegExp(this.allowFormat)).test(char);

                mapping.push({
                    formattedIndex: i,
                    rawIndex: isRawChar ? rawIndex++ : -1,
                    isRawChar,
                    char
                });
            }

            return mapping;
        },

        calculateNewCursorPosition(operation, newRaw, newFormatted, rawCursorPos) {
            // Si estamos al final del raw, posicionar al final del formatted
            if (rawCursorPos >= newRaw.length) {
                return newFormatted.length;
            }

            // Build the mapping for the new formatted string
            const mapping = this.buildFormattedToRawMapping(newFormatted, newRaw);

            // Find the formatted position that corresponds to our raw cursor position
            let targetRawIndex = rawCursorPos;
            let formattedPos = 0;

            for (let i = 0; i < mapping.length; i++) {
                const entry = mapping[i];

                if (entry.isRawChar && entry.rawIndex === targetRawIndex) {
                    formattedPos = i;
                    break;
                } else if (entry.isRawChar && entry.rawIndex > targetRawIndex) {
                    // We've passed our target, position cursor before this character
                    formattedPos = i;
                    break;
                }
            }

            // Remove the special handling that was causing issues
            // The cursor position is already correctly calculated above

            return formattedPos;
        },
        onFocus() {
            this.focused = true;
            this.dirty = true;
        },
        formatToModel(value) {
            if (!value) return '';

            if (this.formatModels && Object.entries(this.formatModels).length > 0) {
                Object.entries(this.formatModels).forEach(([from, to]) => {
                    value = value.replace(new RegExp(from), to)
                })
            }
            return value;
        }
    },
    mounted() {
        if (this.focusOnLoad)
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