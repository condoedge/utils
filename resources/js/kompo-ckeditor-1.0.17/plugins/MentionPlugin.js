import { Plugin } from 'ckeditor5';

export default class MentionPlugin extends Plugin {
    init() {
        const editor = this.editor;

        editor.model.schema.register('mention', {
            isInline: true,
            isObject: true,
            allowWhere: '$text',
            allowAttributes: ['data-mention', 'data-original']
        });

        // Allow data-mention as an attribute in $text:
        editor.model.schema.extend('$text', {
            allowAttributes: ['data-mention', 'data-original']
        });

        // Upcast the <span class="mention" data-mention="..."> view to the model:
        editor.conversion.for('upcast').elementToElement({
            view: {
                name: 'span',
                classes: 'mention'
            },
            model: (viewElement, { writer }) => {
                return writer.createElement('mention', {
                    'data-mention': viewElement.getAttribute('data-mention'),
                    'data-original': viewElement.getAttribute('data-original') || viewElement.getChild(0).data
                });
            }
        });

        editor.conversion.for('downcast').elementToElement({
            model: 'mention',
            view: (modelItem, { writer }) => {
                const dataMention = modelItem.getAttribute('data-mention');
                const dataOriginal = modelItem.getAttribute('data-original');

                // Create the <span> element with attributes and inner content
                const span = writer.createContainerElement('span', {
                    class: 'mention',
                    'data-mention': dataMention,
                    'data-original': dataOriginal,
                    'contenteditable': 'false'
                });

                // Set the inner content of the <span> to the value of data-original
                const textNode = writer.createText(dataOriginal);
                writer.insert(writer.createPositionAt(span, 0), textNode);

                return span;
            }
        });

        // Implement a robust postfixer to maintain mention integrity
        editor.model.document.registerPostFixer(writer => {
            let wasFixed = false;
            const changes = editor.model.document.differ.getChanges();

            function isSpanNode(node) {
                return node && node.is('$text') && node.hasAttribute('data-mention');
            }

            for (const change of changes) {
                if (change.type === 'remove') {
                    const position = change.position;

                    if (isSpanNode(position.nodeAfter) && position.nodeBefore.getAttribute('data-original') != position.nodeBefore._data) {
                        writer.remove(position.nodeAfter);
                        wasFixed = true;
                    } else if (isSpanNode(position.nodeBefore) && position.nodeAfter.getAttribute('data-original') != position.nodeAfter._data) {
                        writer.remove(position.nodeBefore);
                        wasFixed = true;
                    }
                }
            }

            return wasFixed;
        });

        // Add a patch to handle the `document-selection-wrong-position` error when clicking on a mention span
        editor.editing.view.document.on('mousedown', (evt, data) => {
            try {
                const domTarget = data.domTarget;

                if (domTarget.nodeName === 'SPAN' && domTarget.classList.contains('mention')) {
                    const domMention = domTarget.closest('.mention');
                    const viewMention = editor.editing.view.domConverter.mapDomToView(domMention);

                    if (viewMention) {
                        const modelMention = editor.editing.mapper.toModelElement(viewMention);
                        if (modelMention) {
                            editor.editing.view.focus();

                            editor.model.change(writer => {
                                // Select the span element completely
                                const range = writer.createRangeIn(modelMention);
                                writer.setSelection(range);
                            });

                            // Prevent default behavior to avoid invalid selection
                            data.preventDefault();
                            evt.stop();
                        }
                    }
                }
            } catch (error) {
                if (error.name === 'CKEditorError' && error.message.includes('document-selection-wrong-position')) {
                    editor.model.change(writer => {
                        writer.setSelection(editor.model.document.getRoot(), 'end');
                    });
                } else {
                    throw error;
                }
            }
        });
    }
}