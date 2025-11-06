<template>
    <vl-form-field v-bind="$_wrapperAttributes">
        <vlLocaleTabs 
            :locales="locales" 
            :activeLocale="activeLocale"
            @changeLocale="changeTab"/>
        <ckeditor 
            class="vlFormControl"
            ref="content"
            v-model="currentTranslation" 
            v-bind="$_attributes"
            v-on="$_events"
            @keydown.stop
            
            :editor="editor" 
            :config="editorConfig" />
    </vl-form-field>
</template>

<script>
import CKEditor from '../mixins/CKEditor'
import Translatable from 'vue-kompo/js/form/mixins/Translatable'

export default {
    mixins: [CKEditor, Translatable],
}
</script>

