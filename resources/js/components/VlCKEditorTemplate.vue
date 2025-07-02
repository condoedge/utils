<template>
    <div :class="'card-gray-100 p-4 ' + $_config('containerClass')">
        <div>
            <div class="vlFormLabel" v-if="!$_config('removeLabel')" />
            <div class="py-2">
                <div v-if="!$_config('withoutHeight')" class="h-24"></div>

                <vl-form-field v-bind="$_wrapperAttributes">
                    <vlLocaleTabs :locales="locales" :activeLocale="activeLocale" @changeLocale="changeTab" />
                    <ckeditor class="vlFormControl" ref="content" v-model="currentTranslation" v-bind="$_attributes"
                        v-on="$_events" @keydown.stop :editor="editor" :config="editorConfig" />
                </vl-form-field>
            </div>
        </div>
        <div class="py-3">
            <div class="vlFormLabel" v-if="$_config('titleVariables')">{{ $_config('titleVariables') }}</div>
            <div class="flex flex-wrap gap-4 mt-2">
                <div class="flex-1" v-for="(variables, typeLabel) in allVariables" :key="typeLabel">
                    <vl-dropdown :vkompo="variables" />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Translatable from 'vue-kompo/js/form/mixins/Translatable'
import CKEditor from '../kompo-ckeditor-1.0.17/mixins/CKEditor'
import CKEditorUtilities from '../kompo-ckeditor-1.0.17/mixins/CKEditorUtilities'

export default {
    mixins: [CKEditor, Translatable, CKEditorUtilities],
    data() {
        return {
            allVariables: [],
            variableToInsert: null,
            editorInitialized: false
        }
    },
    created() {
        this.allVariables = this.$_config('variables')
        this.$_vlOn('insertVariable', payload => this.insertVariable(payload))
    },
    computed: {

    }
}
</script>
