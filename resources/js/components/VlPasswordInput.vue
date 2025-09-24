<template>
    <vl-form-field v-bind="$_wrapperAttributes" class="[&>.vlInputWrapper]:!bg-transparent [&>.vlInputWrapper]:!border-none">
        <div :class="inputGroupClass">
            <!-- Toggle button on the left -->
            <button
                v-if="showToggle && togglePosition === 'left'"
                type="button"
                class="toggle-button toggle-left"
                :class="{ 'disabled': !allowToggle }"
                @click="togglePasswordVisibility"
                :disabled="!allowToggle"
                :title="isVisible ? 'Hide password' : 'Show password'"
            >
                <span class="toggle-icon" v-html="currentToggleIcon"></span>
            </button>

            <!-- Password Input -->
            <input
                ref="passwordInput"
                class="vlFormControl password-input"
                :class="{ 'has-left-toggle': showToggle && togglePosition === 'left', 'has-right-toggle': showToggle && togglePosition === 'right' }"
                :type="isVisible ? 'text' : 'password'"
                :value="component?.value || ''"
                v-bind="$_attributes"
                v-on="$_events"
                @input="onPasswordInput"
                @focus="onPasswordFocus"
                @blur="onPasswordBlur"
            />

            <!-- Toggle button on the right -->
            <button
                v-if="showToggle && togglePosition === 'right'"
                type="button"
                class="toggle-button toggle-right"
                :class="{ 'disabled': !allowToggle }"
                @click="togglePasswordVisibility"
                :disabled="!allowToggle"
                :title="isVisible ? 'Hide password' : 'Show password'"
            >
                <span class="toggle-icon" v-html="currentToggleIcon"></span>
            </button>
        </div>

        <!-- Optional Strength Indicator -->
        <div v-if="strengthIndicator && component?.value" class="strength-indicator">
            <div class="strength-bar">
                <div
                    class="strength-fill"
                    :class="strengthClass"
                    :style="{ width: strengthPercentage + '%' }"
                ></div>
            </div>
            <div class="strength-text" :class="strengthClass">
                {{ strengthLabel }}
            </div>
        </div>
    </vl-form-field>
</template>

<script>
import Field from 'vue-kompo/js/form/mixins/Field'
import HasInputAttributes from 'vue-kompo/js/form/mixins/HasInputAttributes'

export default {
    name: 'VlPasswordInput',
    mixins: [Field, HasInputAttributes],

    data() {
        return {
            isVisible: false,
            isFocused: false,
        }
    },

    computed: {
        $_attributes() {
            return {
                ...this.$_defaultFieldAttributes,
                ...this.$_defaultInputAttributes,
                autocomplete: 'current-password',
                placeholder: this.placeholder,
            }
        },

        placeholder() {
            return this.$_config('placeholder') || ''
        },

        showToggle() {
            return this.$_config('showToggle') !== false
        },

        togglePosition() {
            return this.$_config('togglePosition') || 'right'
        },

        allowToggle() {
            return this.$_config('allowToggle') !== false
        },

        toggleIconShow() {
            return this.$_config('toggleIconShow') || this.defaultShowIcon
        },

        toggleIconHide() {
            return this.$_config('toggleIconHide') || this.defaultHideIcon
        },

        defaultShowIcon() {
            return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>`
        },

        defaultHideIcon() {
            return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>`
        },

        strengthIndicator() {
            return this.$_config('strengthIndicator') === true
        },

        currentToggleIcon() {
            return this.isVisible ? this.toggleIconHide : this.toggleIconShow
        },

        inputGroupClass() {
            const baseClass = 'vlInputGroup rounded-lg flex items-stretch'
            const errorClass = this.showError ? (this.$_config('invalidClass') || '!border !border-red-600') : ''
            return `${baseClass} ${errorClass}`
        },

        showError() {
            const validateFront = this.$_config('validateFront')
            if (!validateFront) return false

            return this.component?.value && !this.isValidPassword && !this.isFocused
        },

        isValidPassword() {
            // Basic validation - can be extended
            const value = this.component?.value || ''
            return value.length >= 1 // At least one character
        },

        // Password strength calculation
        passwordStrength() {
            const password = this.component?.value || ''
            if (!password) return 0

            let score = 0

            // Length
            if (password.length >= 8) score += 25
            if (password.length >= 12) score += 25

            // Character variety
            if (/[a-z]/.test(password)) score += 12.5
            if (/[A-Z]/.test(password)) score += 12.5
            if (/[0-9]/.test(password)) score += 12.5
            if (/[^A-Za-z0-9]/.test(password)) score += 12.5

            return Math.min(score, 100)
        },

        strengthPercentage() {
            return this.passwordStrength
        },

        strengthClass() {
            const strength = this.passwordStrength
            if (strength < 25) return 'strength-weak'
            if (strength < 50) return 'strength-fair'
            if (strength < 75) return 'strength-good'
            return 'strength-strong'
        },

        strengthLabel() {
            const strength = this.passwordStrength
            if (strength < 25) return 'Weak'
            if (strength < 50) return 'Fair'
            if (strength < 75) return 'Good'
            return 'Strong'
        }
    },

    methods: {
        togglePasswordVisibility() {
            if (!this.allowToggle) return

            this.isVisible = !this.isVisible

            // Keep focus on input after toggle
            this.$nextTick(() => {
                this.focusPasswordInput()
            })
        },

        onPasswordInput(event) {
            const value = event.target.value
            this.component.value = value
        },

        onPasswordFocus() {
            this.isFocused = true
        },

        onPasswordBlur() {
            this.isFocused = false
        },

        focusPasswordInput() {
            if (this.$refs.passwordInput) {
                this.$refs.passwordInput.focus()
            }
        }
    }
}
</script>

<style scoped>
.vlInputGroup {
    display: flex;
    align-items: stretch;
    border-radius: 0.5rem;
    position: relative;

    background-color: var(--form-control-bg);
    border: var(--form-control-border);
}

.toggle-button {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    background: white;
    cursor: pointer;
    user-select: none;
    width: 40px;
    min-width: 40px;
}

.toggle-button:hover:not(.disabled) {
    background-color: white;
    border-color: #d1d5db;
}

.toggle-button:hover:not(.disabled) .toggle-icon svg {
    stroke-width: 3;
    stroke: #374151;
}

.toggle-button:focus {
    outline: none;
    border-color: #d1d5db;
    box-shadow: none;
}

.toggle-button:active {
    background-color: white;
    border-color: #d1d5db;
    box-shadow: none;
    transform: none;
}

.toggle-button.disabled {
    background-color: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
}

.toggle-left {
    border-right: none;
    border-radius: 0.5rem 0 0 0.5rem;
    padding-left: 1.25rem;
}

.toggle-right {
    border-left: none;
    border-radius: 0 0.5rem 0.5rem 0;
    padding-right: 1.25rem;
}

.toggle-icon {
    font-size: 1rem;
    line-height: 1;
}

.password-input.has-left-toggle {
    border-radius: 0 0.5rem 0.5rem 0;
    border-left: none;
}

.password-input.has-right-toggle {
    border-radius: 0.5rem 0 0 0.5rem;
    border-right: none;
}

.password-input:not(.has-left-toggle):not(.has-right-toggle) {
    border-radius: 0.5rem;
}

/* Strength Indicator */
.strength-indicator {
    margin-top: 1rem;
}

.strength-bar {
    height: 4px;
    background-color: #e5e7eb;
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 0.25rem;
}

.strength-fill {
    height: 100%;
    transition: width 0.3s ease-in-out, background-color 0.3s ease-in-out;
    border-radius: 2px;
}

.strength-text {
    font-size: 0.75rem;
    font-weight: 500;
}

/* Strength Colors */
.strength-weak {
    color: #dc2626;
}
.strength-weak .strength-fill,
.strength-fill.strength-weak {
    background-color: #dc2626;
}

.strength-fair {
    color: #ea580c;
}
.strength-fair .strength-fill,
.strength-fill.strength-fair {
    background-color: #ea580c;
}

.strength-good {
    color: #ca8a04;
}
.strength-good .strength-fill,
.strength-fill.strength-good {
    background-color: #ca8a04;
}

.strength-strong {
    color: #16a34a;
}
.strength-strong .strength-fill,
.strength-fill.strength-strong {
    background-color: #16a34a;
}
</style>