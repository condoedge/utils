<template>
    <vl-form-field v-bind="$_wrapperAttributes" @labelclick="focus">
        <ckeditor 
            class="vlFormControl"
            ref="content"
            v-model="component.value" 
            v-bind="$_attributes"
            v-on="$_events"
            @keydown.stop
            
            :editor="editor" 
            :config="editorConfig" />
        <div 
          v-if="mentions.length" 
          class="mention-triggers border-t border-gray-200 p-2 flex">
          <div 
              v-for="(mention, index) in mentions"
              :key="index"
              v-html="getButtonHtml(mention)"
              @click="activateMention(mention)"
          />
          <VlDate 
              :vkompo="$_config('date-component')"
              @open="onOpen"
              @change="onChange"
              @close="onClose"
          />
        </div>
    </vl-form-field>
</template>

<script>
import CKEditor from '../mixins/CKEditor'
import DoesAxiosRequests from 'vue-kompo/js/form/mixins/DoesAxiosRequests'
import EmitsEvents from 'vue-kompo/js/element/mixins/EmitsEvents'
import HasId from 'vue-kompo/js/element/mixins/HasId'
import HasConfig from 'vue-kompo/js/element/mixins/HasConfig'
import CKEditorUtilities from '../mixins/CKEditorUtilities'

export default {
    mixins: [CKEditor, DoesAxiosRequests, CKEditorUtilities, EmitsEvents, HasId, HasConfig],
    data(){
        return {

        }
    },
    methods: {
        onOpen(value, event){ //not used anymore
            return
        },
        onChange(value, event){ //not used anymore
            return
        },
        onClose(value, event){
            value = value.substr(0, 16)
            var html = '<span class="mention ckeditor-mention icon-calendar" data-mention="icon-calendar|'+value+'">'+value+'</span>'
            this.insertHtml(html)
        },
    },
    created(){
        return
    }

}
</script>

