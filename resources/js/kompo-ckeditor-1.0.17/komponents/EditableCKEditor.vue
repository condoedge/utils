<template>
    <div class="vlEditableWrapper">
        <div 
            class="vlEditableContent cursor-pointer" 
            v-if="!editing" 
            v-html="$_value || $_label" 
            @click.stop="startEditing"/>
        
        <VlCKEditor
            v-if="editing" 
            :vkompo="component"
            v-click-out="close"
            @change="change"/>
    </div>
</template>

<script>
import CKEditor from '../mixins/CKEditor'
export default {
    mixins: [CKEditor],
    data(){
        return {
            editing: false
        }
    },
    computed: {

    },
    methods: {
        startEditing(){
            this.editing = true
        },
        close(){
            this.editing = !this.editing
        },
        change(value){
            this.component.value = value
        }
    }
}
</script>

