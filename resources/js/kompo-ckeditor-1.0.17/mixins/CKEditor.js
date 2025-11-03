import CKEditor from '@ckeditor/ckeditor5-vue2'

import { 
    ClassicEditor, 
    Essentials,
    CodeBlock, 
    Alignment, 
    Font, 
    FontBackgroundColor, 
    FontColor, 
    Bold, 
    Italic, 
    Underline, 
    Heading, 
    List, 
    Link, 
    Table, 
    BlockQuote,
    Widget
} from 'ckeditor5';

import Field from 'vue-kompo/js/form/mixins/Field'
import DoesAxiosRequests from 'vue-kompo/js/form/mixins/DoesAxiosRequests'

import CkUploadAdapter from './CkUploadAdapter';

Vue.use( CKEditor );

import 'ckeditor5/ckeditor5.css';

import './translations/fr';   
import './translations/en'; 

import MentionPlugin from '../plugins/MentionPlugin';
import { JsonBlockEditing } from '../plugins/JsonBlockEditing';
import { JsonBlockUI } from '../plugins/JsonBlockUi';

function CkCustomUploadAdapterPlugin( editor ) {
    editor.plugins.get( 'FileRepository' ).createUploadAdapter = ( loader ) => {
        // Configure the URL to the upload script in your back-end here!
        console.log(CkUploadAdapter)
        return new CkUploadAdapter( loader );
    };
}

export default {
    mixins: [Field, DoesAxiosRequests],
    data(){
        return {
            editor: ClassicEditor,
            editorConfig: null,
            mentionNotFound: false,
            newMentions: {},

        }
    },
    computed:{
        toolbar(){ return this.$_config('toolbar') },
        mentions(){ return this.$_config('mentions') || [] },
        focusOnLoad(){ return this.$_config('focusOnLoad') },
        customTextInsertLabel(){ return this.$_config('customTextInsertLabel') },
        customTextInsertion(){ return this.$_config('customTextInsertion') },
    },
    methods: {
        onReady(editor, el) {
            el.ckInstance = editor;

            el.typeSimulated = async (text, options) => {
                const {
                    delay = 15
                } = options;

                const model = editor.model;
                const doc = model.document;

                model.change(writer => {
                writer.remove(writer.createRangeIn(doc.getRoot()));
                });

                for (let i = 0; i < text.length; i++) {
                    model.change(writer => {
                        writer.insertText(text[i], doc.selection.getFirstPosition());
                    });
                    await new Promise(res => setTimeout(res, delay - (Math.abs(text.length / 2 - Math.abs((text.length / 2 - (i + 1))))) / delay));
                }
            };
        },
        focus(){
            //focusing CKeditor not working when click on label :/
            this.$_focusAction()
            setTimeout( () => {
                this.$refs.content.$_instance.editing.view.focus()
            }, 50 )
        },
        $_inputAction(){
            if(typeof window.KCK !== 'undefined') {
                clearTimeout(window.KCK);
            }

            window.KCK = setTimeout(() => {
                this.$_changeAction()            
                this.$emit('change', this.$_value) 
            }, this.$_debounce || 1200);
        },
        /*$_inputAction: _.debounce(function () {  //this works too but doesn't allow me to add this.$_debounce
            this.$_changeAction()            
            this.$emit('change', this.$_value) 
        }, 1500), */

        getButtonHtml(mention){
            return '<div aria-label="'+mention.itemLabel+'" data-balloon-pos="up">'+mention.iconHtml+'</div>'
        },
        insertHTML(html){
            // See: https://ckeditor.com/docs/ckeditor5/latest/builds/guides/faq.html#where-are-the-editorinserthtml-and-editorinserttext-methods-how-to-insert-some-content
            const editor = this.$refs.content?.$_instance
            const viewFragment = editor.data.processor.toView( html )
            const modelFragment = editor.data.toModel( viewFragment )
            editor.model.insertContent(modelFragment)
        },
        insertTextAndFocus(text){
            const editor = this.$refs.content?.$_instance

            editor.editing.view.focus()

            editor.model.change( writer => {
                //I had to focus first then insert the marker then refocus...
                writer.setSelection( writer.createPositionAt( editor.model.document.selection.getFirstPosition(), 'end' ))
            })

            this.$nextTick(()=> {

                editor.model.change( writer => {

                    writer.insertText(text, editor.model.document.selection.getFirstPosition() )

                    writer.setSelectionFocus( writer.createPositionAt( editor.model.document.selection.getFirstPosition(), 'end' ))
                })

            })
        },
        activateMention(mention){

            this.$_focusAction()

            this.insertTextAndFocus(' '+mention.marker)
        },
        insertCustomText(){
            this.insertHTML(this.customTextInsertion)
        },
        customItemRenderer( item ) {
            const itemElement = document.createElement( 'div' )

            itemElement.classList.add('mention-option')

            item.iconClass.split(' ').forEach(iconClass => {
                itemElement.classList.add(iconClass)
            })

            itemElement.textContent = item.text

            return itemElement;
        },
        createFeedCallback( feedItems, itemType ) {
            return feedText => {

                this.mentionNotFound = null

                const filteredItems = _.toArray(feedItems) //crazy: sometimes feedItems is an object...
                    .concat(this.newMentions[itemType] || [])
                    .filter( item => {
                        // Item might be defined as object.
                        const itemId = typeof item == 'string' ? item : String( item.id );

                        // The default feed is case insensitive.
                        return itemId
                            .normalize('NFD').replace(/[\u0300-\u036f]/g, "") //replace accents
                            .toLowerCase().includes( feedText.toLowerCase() );
                    } )
                    //.slice( 0, 10 ); //had to add this function to disable filtering 10 items...

                if(!filteredItems.length)
                    this.mentionNotFound = itemType

                return filteredItems;
            };
        }
    },
    mounted(){
        if(this.focusOnLoad)
            this.focus()
    },
    created(){
        const originalCreate = ClassicEditor.create;
        const self = this;
        ClassicEditor.create = function (element, config) {
            return originalCreate.call(this, element, config).then(editor => {
                element.ckeditorInstance = editor;
                self.onReady(editor, element);
                return editor;
            });
        };

        let extraPlugins = [
            Essentials,
            Alignment,
            Font,
            FontBackgroundColor,
            FontColor,
            Bold,
            Italic,
            Underline,
            Heading,
            List,
            Link,
            Table,
            BlockQuote,
            Widget,
            MentionPlugin,
        ];

        // Add plugins from windo global var if it's defined
        if (window.CKEditorExtraPlugins && Array.isArray(window.CKEditorExtraPlugins)) {
            extraPlugins = extraPlugins.concat(window.CKEditorExtraPlugins);
        }

        /* MENTIONS */
        var mentions = []
        
        this.mentions.forEach((mention) => {

            mentions.push(Object.assign(mention, {
                feed: this.createFeedCallback(mention.initialFeed, mention.itemType),
                itemRenderer: this.customItemRenderer
            }))
        })

        /* IMAGE UPLOAD */
        let hasImageUploadPlugin = this.toolbar.indexOf('imageUpload') > -1
        if (hasImageUploadPlugin) {
            extraPlugins.push(CkCustomUploadAdapterPlugin)            
        }

        /* CODE BLOCK */
        let hasCodeBlockPlugin = this.toolbar.indexOf('codeBlock') > -1
        if (hasCodeBlockPlugin) {
            extraPlugins.push(CodeBlock)
        }

        /* JSON FORMATTER */
        let hasJsonFormatterPlugin = this.toolbar.indexOf('jsonBlock') > -1
        if (hasJsonFormatterPlugin) {
            extraPlugins.push(JsonBlockEditing)
            extraPlugins.push(JsonBlockUI)
        }

        //$_config toolbar was undefined if declared in data()
        this.editorConfig = Object.assign({
            licenseKey: 'GPL',
            alignment: { options: [ 'left', 'right', 'center', 'justify' ] },
            toolbar: this.toolbar,
            table: {
                contentToolbar: [
                    'tableColumn', 'tableRow', 'mergeTableCells'
                ]
            },        
            codeBlock: {
                languages: [
                    { language: 'css', label: 'CSS' },
                    { language: 'html', label: 'HTML' },
                    { language: 'javascript', label: 'JavaScript' },
                    { language: 'json', label: 'JSON' },
                ]
            },
            list: {
                properties: {
                    styles: true,
                    startIndex: true,
                    reversed: true
                }
            },
            language: this.$_config('kompo_locale') || 'en',
        }, !this.mentions.length ? {} : {

            mention: {
                feeds: mentions,
            }
        }, !extraPlugins.length ? {} : {
            extraPlugins: extraPlugins,
        })
    }
}