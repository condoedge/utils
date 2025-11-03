<template>
    <vl-form-field v-bind="$_wrapperAttributes" @labelclick="focus">
        <ckeditor 
            class="vlFormControl"
            ref="content"
            v-model="component.value" 
            v-bind="$_attributes"
            v-on="$_events"
            @keydown.stop
            @ready="onReady"
            
            :editor="editor" 
            :config="editorConfig" />
        <div 
          v-if="mentions.length" 
          class="mention-triggers border-t border-gray-200 p-2 flex">
          <div 
              v-for="(mention, index) in mentions"
              v-html="getButtonHtml(mention)"
              @click="activateMention(mention)"
              />
        </div>
        <a v-if="customTextInsertion" 
            @click="insertCustomText" 
            class="cursor-pointer absolute bottom-2 right-2 text-sm" 
            v-html="customTextInsertLabel" /> 
    </vl-form-field>
</template>

<script>
import CKEditor from '../mixins/CKEditor'

export default {
    mixins: [CKEditor]
}
</script>
