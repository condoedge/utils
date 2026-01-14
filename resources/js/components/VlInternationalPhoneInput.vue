<template>
    <vl-form-field v-bind="$_wrapperAttributes">
        <div :class="inputGroupClass">
            <!-- Country Selector -->
            <div class="country-selector" ref="countrySelector">
                <button 
                    type="button"
                    class="country-trigger"
                    :class="{ 'is-open': isDropdownOpen }"
                    @click="toggleDropdown"
                    @keydown="onTriggerKeydown"
                >
                    <img v-if="currentCountry.flag && !isDetectingCountry" :src="currentCountry.flag" :alt="currentCountry.name" class="flag-img" />
                    <span v-else-if="isDetectingCountry" class="flag-loading">⏳</span>
                    <span v-else class="flag-fallback">🌍</span>
                    <span class="code">+{{ currentCountry.dialCode }}</span>
                    <span class="arrow">▼</span>
                </button>
                
                <div 
                    v-if="isDropdownOpen" 
                    class="country-dropdown"
                    @click.stop
                >
                    <div class="search-container">
                        <input 
                            ref="searchInput"
                            v-model="searchTerm"
                            type="text"
                            placeholder="Search countries..."
                            class="search-input"
                            @keydown="onSearchKeydown"
                            @input="onSearchInput"
                        />
                    </div>
                    
                    <div class="countries-list" ref="countriesList">
                        <button
                            v-for="country in filteredCountries"
                            :key="country.code"
                            type="button"
                            class="country-option"
                            :class="{ 'is-selected': country.code === selectedCountryCode }"
                            @click="selectCountry(country)"
                        >
                            <img v-if="country.flag" :src="country.flag" :alt="country.name" class="flag-img" />
                            <span v-else class="flag-fallback">🌍</span>
                            <span class="name">{{ country.name }}</span>
                                <span class="dial-code">+{{ country.dialCode }}</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Phone Input -->
            <input
                ref="phoneInput"
                class="phone-input"
                :value="formattedValue"
                v-bind="$_attributes"
                v-on="$_events"
                @input="onPhoneInput"
                @keydown="onPhoneKeydown"
                @focus="onPhoneFocus"
                @blur="onPhoneBlur"
            />
        </div>
    </vl-form-field>
</template>

<script>
import Field from 'vue-kompo/js/form/mixins/Field'
import HasInputAttributes from 'vue-kompo/js/form/mixins/HasInputAttributes'
import { parsePhoneNumberFromString, getCountries, getCountryCallingCode, AsYouType } from 'libphonenumber-js'
import { COUNTRIES_DATA } from '../data/countries.js'

export default {
    name: 'VlInternationalPhoneInput',
    mixins: [Field, HasInputAttributes],
    
    data() {
        return {
            // Phone number state
            phoneValue: this.component?.value || '',
            
            // Country selection state
            selectedCountryCode: this.$_config('country') || this.$_config('defaultCountry') || 'US',
            isDropdownOpen: false,
            searchTerm: '',
            
            // Input state
            isFocused: false,
            
            // Countries data
            countriesData: COUNTRIES_DATA,
            
            // Geolocation state
            isDetectingCountry: false,
        }
    },
    
    computed: {
        $_attributes() {
            return {
                ...this.$_defaultFieldAttributes,
                ...this.$_defaultInputAttributes,
                type: 'tel',
                inputmode: 'tel',
                autocomplete: 'tel',
                placeholder: this.placeholder,
            }
        },
        
        placeholder() {
            return this.$_config('placeholder') || 'Phone number'
        },
        
        inputGroupClass() {
            const baseClass = 'vlInputGroup rounded-lg flex items-stretch'
            const errorClass = this.showError ? (this.$_config('invalidClass') || '!border !border-red-600') : ''
            return `${baseClass} ${errorClass}`
        },
        
        // Current country info
        currentCountry() {
            const country = this.countries.find(c => c.code === this.selectedCountryCode)
            return country || this.countries[0] // Fallback to first country
        },
        
        // All countries with metadata - static data
        countries() {
            return this.countriesData
        },
        
        // Filtered countries based on search
        filteredCountries() {
            if (!this.searchTerm.trim()) {
                return this.countries
            }
            
            const query = this.searchTerm.toLowerCase()
            return this.countries.filter(country => {
                // Search by country name
                if (country.name.toLowerCase().includes(query)) return true
                
                // Search by country code
                if (country.code.toLowerCase().includes(query)) return true
                
                // Search by dial code (with or without +)
                const dialCode = country.dialCode
                const dialCodeWithPlus = `+${dialCode}`
                if (dialCode.includes(query) || dialCodeWithPlus.includes(query)) return true
                
                // Search by dial code without the + prefix
                if (query.startsWith('+')) {
                    const queryWithoutPlus = query.substring(1)
                    if (dialCode.includes(queryWithoutPlus)) return true
                }
                
                return false
            })
        },
        
        // Formatted phone value for display
        formattedValue() {
            if (!this.phoneValue) return ''

            try {
                // If phone value starts with the country code, show it formatted
                if (this.phoneValue.startsWith('+')) {
                    const asYouType = new AsYouType()
                    const formatted = asYouType.input(this.phoneValue)
                    return formatted || this.phoneValue
                } else {
                    // For national numbers, format with country context
                    const asYouType = new AsYouType(this.selectedCountryCode)
                    const formatted = asYouType.input(this.phoneValue)
                    return formatted || this.phoneValue
                }
            } catch (error) {
                return this.phoneValue
            }
        },
        
        // Validation state
        isValidPhone() {
            if (!this.phoneValue) return true // Empty is valid
            
            try {
                const phoneNumber = parsePhoneNumberFromString(this.phoneValue, this.selectedCountryCode)
                return phoneNumber ? phoneNumber.isValid() : false
            } catch (error) {
                return false
            }
        },
        
        showError() {
            const validateFront = this.$_config('validateFront')
            if (!validateFront) return false
            
            return this.phoneValue && !this.isValidPhone && !this.isFocused
        },
    },
    
    watch: {
        'component.value'(newValue) {
            this.phoneValue = newValue || ''
            
            // Auto-detect country from phone number if no country is explicitly set
            if (newValue && !this.$_config('country')) {
                this.autoDetectCountryFromPhone(newValue)
            }
        },
        
        selectedCountryCode(newCode, oldCode) {
            if (newCode !== oldCode && this.phoneValue) {
                this.reformatWithCountry(newCode)
            }
        }
    },
    
    mounted() {
        // Close dropdown when clicking outside
        document.addEventListener('click', this.handleClickOutside)
        
        // Set initial value
        // this.component.value = this.phoneValue
        
        // Auto-detect country if no explicit country is set and no phone value exists
        if (!this.$_config('country') && !this.phoneValue) {
            if (!this.autoDetectCountryFromPhone(this.phoneValue)) {
                this.detectCountryByLocation()
            }
        }
    },
    
    beforeDestroy() {
        document.removeEventListener('click', this.handleClickOutside)
    },
    
    methods: {
        // Country selection methods
        toggleDropdown() {
            this.isDropdownOpen = !this.isDropdownOpen
            if (this.isDropdownOpen) {
                this.$nextTick(() => {
                    this.focusSearchInput()
                })
            } else {
                this.searchTerm = ''
            }
        },
        
        selectCountry(country) {
            const previousCountryCode = this.selectedCountryCode;
            const previousCountry = this.countries.find(c => c.code === previousCountryCode);
            this.selectedCountryCode = country.code
            this.isDropdownOpen = false
            this.searchTerm = ''

            // Get current and new dial codes
            const previousDialCode = previousCountry ? previousCountry.dialCode : '';
            const newDialCode = country.dialCode;

            // Check if current value is just a country code (like +54, +1, etc.)
            const currentDialCode = `+${previousDialCode}`
            const isOnlyCountryCode = !this.phoneValue ||
                                    this.phoneValue === currentDialCode ||
                                    this.phoneValue === previousDialCode ||
                                    this.phoneValue.replace(/\D/g, '') === previousDialCode

            if (isOnlyCountryCode) {
                // Set new country code and ensure component.value is complete
                this.phoneValue = `+${newDialCode}`
                this.component.value = `+${newDialCode}`

                // Update input display immediately
                this.$nextTick(() => {
                    if (this.$refs.phoneInput) {
                        this.$refs.phoneInput.value = this.formattedValue
                    }
                })
            } else {
                // Extract national number and reformat with new country
                let nationalNumber = this.phoneValue;
                console.log(nationalNumber, previousDialCode);
                // Remove previous country code if it exists
                if (nationalNumber.startsWith(`+${previousDialCode}`)) {
                    nationalNumber = nationalNumber.substring((`+${previousDialCode}`).length).trim();
                } else if (nationalNumber.startsWith(previousDialCode)) {
                    nationalNumber = nationalNumber.substring(previousDialCode.length).trim();
                } else if (nationalNumber.startsWith('+')) {
                    // Try to parse and extract national number
                    try {
                        const parsed = parsePhoneNumberFromString(nationalNumber);
                        if (parsed && parsed.nationalNumber) {
                            nationalNumber = parsed.nationalNumber;
                        }
                    } catch (error) {
                        // Keep original if parsing fails
                    }
                }

                // Set new phone value with new country code
                this.phoneValue = `+${newDialCode}${nationalNumber}`;
                this.component.value = this.phoneValue;

                // Try to format properly
                try {
                    const phoneNumber = parsePhoneNumberFromString(this.phoneValue);
                    if (phoneNumber && phoneNumber.isValid()) {
                        this.component.value = phoneNumber.number;
                    }
                } catch (error) {
                    // Keep current value if parsing fails
                }
            }

            // Focus back to phone input
            this.$nextTick(() => {
                this.focusPhoneInput()
            })
        },
        
        
        reformatWithCountry(phoneValue, countryCode) {
            try {
                const phoneNumber = parsePhoneNumberFromString(phoneValue, countryCode)
                if (phoneNumber && phoneNumber.isValid()) {
                    // Always keep the full international number in component.value
                    this.phoneValue = phoneNumber.number
                    this.component.value = phoneNumber.number
                } else {
                    // Fallback: ensure we have at least the country code
                    const country = this.countries.find(c => c.code === countryCode);
                    if (country) {
                        const cleanValue = phoneValue.replace(/\D/g, '');
                        this.phoneValue = `+${country.dialCode}${cleanValue}`;
                        this.component.value = this.phoneValue;
                    }
                }
            } catch (error) {
                // Keep current value if parsing fails
            }
        },
        
        // Search methods
        focusSearchInput() {
            if (this.$refs.searchInput) {
                this.$refs.searchInput.focus()
            }
        },
        
        onSearchInput() {
            // Search is reactive via computed property
        },
        
        onSearchKeydown(event) {
            switch (event.key) {
                case 'Escape':
                    this.closeDropdown()
                    this.focusPhoneInput()
                    break
                case 'ArrowDown':
                    event.preventDefault()
                    this.focusFirstCountry()
                    break
                case 'Enter':
                    event.preventDefault()
                    if (this.filteredCountries.length > 0) {
                        this.selectCountry(this.filteredCountries[0])
                    }
                    break
            }
        },
        
        focusFirstCountry() {
            this.$nextTick(() => {
                const firstOption = this.$refs.countriesList?.querySelector('.country-option')
                if (firstOption) {
                    firstOption.focus()
                }
            })
        },
        
        // Phone input methods
        onPhoneInput(event) {
            const input = event.target
            const newValue = input.value
            
            // Check if input contains country code (starts with +)
            if (newValue.startsWith('+')) {
                this.handleInputWithCountryCode(newValue)
                return
            }
            
            // Clean the input value
            const cleanedValue = this.cleanPhoneInput(newValue)
            
            // Check if value exceeds maximum length
            if (!this.isWithinMaxLength(cleanedValue)) {
                // Restore previous value
                input.value = this.formattedValue
                return
            }
            
            this.updateInput(input, cleanedValue)
        },

        updateInput(input, cleanedValue = null) {
            // Always ensure we have the country code in the phone value
            const country = this.countries.find(c => c.code === this.selectedCountryCode);
            let fullPhoneValue = cleanedValue;

            if (cleanedValue && country) {
                // If the cleaned value doesn't start with the country code, add it
                if (!cleanedValue.startsWith(`+${country.dialCode}`) && !cleanedValue.startsWith(country.dialCode)) {
                    fullPhoneValue = `+${country.dialCode}${cleanedValue.replace(/^\+/, '')}`;
                } else if (cleanedValue.startsWith(country.dialCode) && !cleanedValue.startsWith(`+${country.dialCode}`)) {
                    fullPhoneValue = `+${cleanedValue}`;
                }
            }

            // Update internal state - phoneValue for display, component.value for the complete number
            this.phoneValue = fullPhoneValue || cleanedValue
            this.component.value = this.getE164Number(this.phoneValue)

            // Update display value with proper formatting
            this.$nextTick(() => {
                const formatted = this.formattedValue
                input.value = formatted

                // Maintain cursor position
                const cursorPos = Math.min(input.selectionStart, formatted.length)
                input.setSelectionRange(cursorPos, cursorPos)
            })
        },
        
        handleInputWithCountryCode(inputValue) {
            try {
                // Parse the full international number
                const phoneNumber = parsePhoneNumberFromString(inputValue)
                
                if (phoneNumber && phoneNumber.country) {
                    // Update country if detected
                    this.selectedCountryCode = phoneNumber.country
                    
                    // Store the full international number to preserve country code in display
                    this.phoneValue = inputValue
                    this.component.value = phoneNumber.number || inputValue
                } else {
                    // If can't parse, treat as regular input
                    const cleanedValue = this.cleanPhoneInput(inputValue)
                    this.phoneValue = cleanedValue
                    this.component.value = this.getE164Number(cleanedValue)
                }
            } catch (error) {
                console.warn('Error parsing phone with country code:', error)
                // Fallback to regular input handling
                const cleanedValue = this.cleanPhoneInput(inputValue)
                this.phoneValue = cleanedValue
                this.component.value = this.getE164Number(cleanedValue)
            }
        },
        
        onPhoneKeydown(event) {
            // Allow control keys
            const controlKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Tab', 'Escape', 'Enter']
            if (controlKeys.includes(event.key)) return
            
            // Allow copy/paste shortcuts
            const isShortcut = (event.ctrlKey || event.metaKey) && 
                             ['a', 'c', 'v', 'x'].includes(event.key.toLowerCase())
            if (isShortcut) return
            
            // Allow only digits, spaces, dashes, parentheses, and plus
            const allowedChars = /[0-9\s\-\(\)\+]/
            if (!allowedChars.test(event.key)) {
                event.preventDefault()
                return
            }
            
            // Handle plus sign - only allow at beginning
            if (event.key === '+') {
                const input = event.target
                const cursorPos = input.selectionStart
                const hasExistingPlus = input.value.includes('+')
                
                if (hasExistingPlus || cursorPos !== 0) {
                    event.preventDefault()
                }
            }
        },
        
        onPhoneFocus() {
            this.isFocused = true
        },
        
        onPhoneBlur() {
            this.isFocused = false
        },
        
        focusPhoneInput() {
            if (this.$refs.phoneInput) {
                this.$refs.phoneInput.focus()
            }
        },
        
        // Utility methods
        cleanPhoneInput(value) {
            if (!value) return ''
            
            // Remove all non-digit characters except spaces, dashes, parentheses, and plus
            return value.replace(/[^0-9\s\-\(\)\+]/g, '')
        },
        
        isWithinMaxLength(value) {
            // E.164 maximum is 15 digits total
            const digitsOnly = value.replace(/\D/g, '')
            return digitsOnly.length <= 15
        },
        
        getE164Number(phoneValue) {
            if (!phoneValue) return ''

            try {
                // If it already looks like an international number, try to parse it as-is
                if (phoneValue.startsWith('+')) {
                    const phoneNumber = parsePhoneNumberFromString(phoneValue)
                    return phoneNumber ? phoneNumber.number : phoneValue
                }

                // Otherwise, try to parse it with the selected country
                const phoneNumber = parsePhoneNumberFromString(phoneValue, this.selectedCountryCode)
                return phoneNumber ? phoneNumber.number : phoneValue
            } catch (error) {
                return phoneValue
            }
        },
        
        closeDropdown() {
            this.isDropdownOpen = false
            this.searchTerm = ''
        },
        
        handleClickOutside(event) {
            if (this.isDropdownOpen && !this.$refs.countrySelector.contains(event.target)) {
                this.closeDropdown()
            }
        },
        
        // Auto-detect country from IP geolocation
        async detectCountryByLocation() {
            if (this.isDetectingCountry) return
            
            this.isDetectingCountry = true
            
            try {
                // Try multiple geolocation services for better reliability
                const countryCode = await this.getCountryFromIP()
                if (countryCode && this.countries.find(c => c.code === countryCode)) {
                    this.selectedCountryCode = countryCode
                }
            } catch (error) {
                console.warn('Failed to detect country by location:', error)
            } finally {
                this.isDetectingCountry = false
            }
        },
        
        async getCountryFromIP() {
            // Try ipapi.co first (free, reliable)
            try {
                const response = await fetch('https://ipapi.co/json/', {
                    timeout: 5000
                })
                const data = await response.json()
                return data.country_code
            } catch (error) {
                console.warn('ipapi.co failed:', error)
            }
            
            // Fallback to ip-api.com
            try {
                const response = await fetch('http://ip-api.com/json/', {
                    timeout: 5000
                })
                const data = await response.json()
                return data.countryCode
            } catch (error) {
                console.warn('ip-api.com failed:', error)
            }
            
            // Fallback to ipinfo.io
            try {
                const response = await fetch('https://ipinfo.io/json', {
                    timeout: 5000
                })
                const data = await response.json()
                return data.country
            } catch (error) {
                console.warn('ipinfo.io failed:', error)
            }
            
            return null
        },
        
        // Auto-detect country from existing phone number
        autoDetectCountryFromPhone(phoneValue) {
            try {
                const phoneNumber = parsePhoneNumberFromString(phoneValue)
                if (phoneNumber && phoneNumber.country) {
                    this.selectedCountryCode = phoneNumber.country

                    return true;
                }
            } catch (error) {
                console.warn('Failed to auto-detect country from phone:', error)
            }

            return false;
        },
        
        // API methods
        async loadCountriesData() {
            try {
                this.isLoadingCountries = true
                
                // Fetch from REST Countries API
                const response = await fetch('https://restcountries.com/v3.1/all?fields=name,cca2,idd,flags')
                const countries = await response.json()
                
                // Transform data to our format
                this.countriesData = countries
                    .filter(country => country.idd?.root && country.idd?.suffixes?.length > 0)
                    .map(country => ({
                        code: country.cca2,
                        name: country.name.common,
                        dialCode: country.idd.root + country.idd.suffixes[0],
                        flag: country.flags?.png || null
                    }))
                    .sort((a, b) => a.name.localeCompare(b.name))
                
            } catch (error) {
                console.error('Failed to load countries data:', error)
                // Fallback to libphonenumber-js data
                this.countriesData = getCountries().map(code => ({
                    code,
                    name: this.getCountryName(code),
                    dialCode: getCountryCallingCode(code),
                    flag: null
                }))
            } finally {
                this.isLoadingCountries = false
            }
        },
        
        // Fallback country data methods
        getCountryName(code) {
            const countryNames = {
                'US': 'United States', 'CA': 'Canada', 'GB': 'United Kingdom', 'FR': 'France',
                'DE': 'Germany', 'IT': 'Italy', 'ES': 'Spain', 'AU': 'Australia',
                'JP': 'Japan', 'CN': 'China', 'IN': 'India', 'BR': 'Brazil',
                'MX': 'Mexico', 'AR': 'Argentina', 'RU': 'Russia', 'ZA': 'South Africa',
                'EG': 'Egypt', 'NG': 'Nigeria', 'KE': 'Kenya', 'MA': 'Morocco',
                'SA': 'Saudi Arabia', 'AE': 'UAE', 'IL': 'Israel', 'TR': 'Turkey',
                'TH': 'Thailand', 'SG': 'Singapore', 'MY': 'Malaysia', 'ID': 'Indonesia',
                'PH': 'Philippines', 'VN': 'Vietnam', 'KR': 'South Korea', 'TW': 'Taiwan',
                'HK': 'Hong Kong', 'NZ': 'New Zealand', 'CH': 'Switzerland', 'AT': 'Austria',
                'BE': 'Belgium', 'NL': 'Netherlands', 'DK': 'Denmark', 'SE': 'Sweden',
                'NO': 'Norway', 'FI': 'Finland', 'PL': 'Poland', 'CZ': 'Czech Republic',
                'HU': 'Hungary', 'RO': 'Romania', 'BG': 'Bulgaria', 'GR': 'Greece',
                'PT': 'Portugal', 'IE': 'Ireland', 'IS': 'Iceland', 'LU': 'Luxembourg',
                'MT': 'Malta', 'CY': 'Cyprus', 'EE': 'Estonia', 'LV': 'Latvia',
                'LT': 'Lithuania', 'SK': 'Slovakia', 'SI': 'Slovenia', 'HR': 'Croatia',
                'RS': 'Serbia', 'BA': 'Bosnia', 'ME': 'Montenegro', 'MK': 'North Macedonia',
                'AL': 'Albania', 'XK': 'Kosovo', 'MD': 'Moldova', 'UA': 'Ukraine',
                'BY': 'Belarus', 'GE': 'Georgia', 'AM': 'Armenia', 'AZ': 'Azerbaijan',
                'KZ': 'Kazakhstan', 'UZ': 'Uzbekistan', 'KG': 'Kyrgyzstan', 'TJ': 'Tajikistan',
                'TM': 'Turkmenistan', 'MN': 'Mongolia', 'AF': 'Afghanistan', 'PK': 'Pakistan',
                'BD': 'Bangladesh', 'LK': 'Sri Lanka', 'MV': 'Maldives', 'BT': 'Bhutan',
                'NP': 'Nepal', 'MM': 'Myanmar', 'LA': 'Laos', 'KH': 'Cambodia',
                'BN': 'Brunei', 'TL': 'East Timor', 'FJ': 'Fiji', 'PG': 'Papua New Guinea',
                'SB': 'Solomon Islands', 'VU': 'Vanuatu', 'NC': 'New Caledonia', 'PF': 'French Polynesia',
                'WS': 'Samoa', 'TO': 'Tonga', 'KI': 'Kiribati', 'TV': 'Tuvalu',
                'NR': 'Nauru', 'PW': 'Palau', 'FM': 'Micronesia', 'MH': 'Marshall Islands',
                'CK': 'Cook Islands', 'NU': 'Niue', 'TK': 'Tokelau', 'WF': 'Wallis and Futuna',
                'AS': 'American Samoa', 'GU': 'Guam', 'MP': 'Northern Mariana Islands', 'VI': 'US Virgin Islands',
                'PR': 'Puerto Rico', 'DO': 'Dominican Republic', 'CU': 'Cuba', 'JM': 'Jamaica',
                'HT': 'Haiti', 'TT': 'Trinidad and Tobago', 'BB': 'Barbados', 'LC': 'Saint Lucia',
                'VC': 'Saint Vincent', 'GD': 'Grenada', 'AG': 'Antigua and Barbuda', 'KN': 'Saint Kitts and Nevis',
                'DM': 'Dominica', 'BZ': 'Belize', 'GT': 'Guatemala', 'SV': 'El Salvador',
                'HN': 'Honduras', 'NI': 'Nicaragua', 'CR': 'Costa Rica', 'PA': 'Panama',
                'CO': 'Colombia', 'VE': 'Venezuela', 'GY': 'Guyana', 'SR': 'Suriname',
                'GF': 'French Guiana', 'UY': 'Uruguay', 'PY': 'Paraguay', 'BO': 'Bolivia',
                'PE': 'Peru', 'EC': 'Ecuador', 'CL': 'Chile', 'FK': 'Falkland Islands',
                'GS': 'South Georgia', 'SH': 'Saint Helena', 'AC': 'Ascension Island', 'TA': 'Tristan da Cunha',
                'CV': 'Cape Verde', 'ST': 'São Tomé and Príncipe', 'GQ': 'Equatorial Guinea', 'GA': 'Gabon',
                'CG': 'Republic of the Congo', 'CD': 'Democratic Republic of the Congo', 'CF': 'Central African Republic',
                'TD': 'Chad', 'CM': 'Cameroon', 'NE': 'Niger', 'BF': 'Burkina Faso',
                'ML': 'Mali', 'SN': 'Senegal', 'GM': 'Gambia', 'GW': 'Guinea-Bissau',
                'GN': 'Guinea', 'SL': 'Sierra Leone', 'LR': 'Liberia', 'CI': 'Ivory Coast',
                'GH': 'Ghana', 'TG': 'Togo', 'BJ': 'Benin', 'DZ': 'Algeria',
                'TN': 'Tunisia', 'LY': 'Libya', 'SD': 'Sudan', 'SS': 'South Sudan',
                'ET': 'Ethiopia', 'ER': 'Eritrea', 'DJ': 'Djibouti', 'SO': 'Somalia',
                'UG': 'Uganda', 'RW': 'Rwanda', 'BI': 'Burundi', 'TZ': 'Tanzania',
                'MW': 'Malawi', 'ZM': 'Zambia', 'ZW': 'Zimbabwe', 'BW': 'Botswana',
                'NA': 'Namibia', 'SZ': 'Eswatini', 'LS': 'Lesotho', 'MG': 'Madagascar',
                'MU': 'Mauritius', 'SC': 'Seychelles', 'KM': 'Comoros', 'YT': 'Mayotte',
                'RE': 'Réunion', 'MZ': 'Mozambique', 'AO': 'Angola'
            }
            return countryNames[code] || code
        },
        
        onTriggerKeydown(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault()
                this.toggleDropdown()
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
}

.country-selector {
    position: relative;
    flex-shrink: 0;
}

.country-trigger {
    display: flex;
    align-items: center;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-right: none;
    border-radius: 0.5rem 0 0 0.5rem;
    background: white;
    cursor: pointer;
    user-select: none;
    min-width: 120px;
    transition: border-color 0.15s ease-in-out;
}

.country-trigger:hover {
    border-color: #9ca3af;
}

.country-trigger.is-open {
    border-color: #3b82f6;
    box-shadow: 0 0 0 1px #3b82f6;
}

.flag-img {
    width: 20px;
    height: 15px;
    margin-right: 0.375rem;
    border-radius: 2px;
    object-fit: cover;
}

.flag-fallback {
    margin-right: 0.375rem;
    font-size: 1rem;
}
.flag-loading {
    margin-right: 0.375rem;
    font-size: 0.875rem;
    animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.country-trigger .code {
    font-weight: 500;
    color: #374151;
    margin-right: auto;
}

.country-trigger .arrow {
    font-size: 0.625rem;
    color: #6b7280;
    transition: transform 0.15s ease-in-out;
}

.country-trigger.is-open .arrow {
    transform: rotate(180deg);
}

.country-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 50;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    max-height: 400px;
    min-width: 320px;
    width: max-content;
    overflow: hidden;
}

.search-container {
    padding: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.search-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    outline: none;
    font-size: 0.875rem;
}

.search-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 1px #3b82f6;
}

.countries-list {
    max-height: 340px;
    overflow-y: auto;
    overflow-x: hidden;
}

.country-option {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: none;
    background: none;
    text-align: left;
    cursor: pointer;
    transition: background-color 0.15s ease-in-out;
}

.country-option:hover {
    background-color: #f9fafb;
}

.country-option.is-selected {
    background-color: #eff6ff;
    color: #1d4ed8;
}

.country-option .flag-img {
    width: 20px;
    height: 15px;
    margin-right: 0.5rem;
    border-radius: 2px;
    object-fit: cover;
}

.country-option .flag-fallback {
    margin-right: 0.5rem;
    font-size: 1rem;
}

.country-option .name {
    flex: 1;
    font-size: 0.875rem;
}

.country-option .dial-code {
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 500;
}

.phone-input {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0 0.5rem 0.5rem 0;
    outline: none;
    font-size: 1rem;
}

.phone-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 1px #3b82f6;
}

.phone-input:disabled {
    background-color: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
}
</style> 