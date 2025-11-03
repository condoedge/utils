import {Plugin} from 'ckeditor5';
// Ensure highlight.js is imported in the environment (e.g., via global script)
import hljs from 'highlight.js';  // (optional, if using bundling)
import { JsonBlockCommand } from './JsonBlockCommand';

import json from 'highlight.js/lib/languages/json';
import 'highlight.js/styles/atom-one-dark.css'; // or the theme of your choice
import { padStart } from 'lodash';

hljs.registerLanguage('json', json);

const TAB_SIZE = window.TAB_SIZE || 2;

function getJsonText(jsonBlockElement) {
  let text = '';
  for (const child of jsonBlockElement.getChildren()) {
    if (child.is('$text')) {
      text += child.data;
    } else if (child.name === 'softBreak') {
      text += '\n';
    }
  }
  return text;
}

export class JsonBlockEditing extends Plugin {
  static get pluginName() { return 'JsonBlockEditing'; }

  init() {
    const editor = this.editor;
    const schema = editor.model.schema;
    const conversion = editor.conversion;
    const model = editor.model;

    // **Schema**: Register the 'jsonBlock' model element
    schema.register('jsonBlock', {
      allowWhere: '$block',
      allowContentOf: '$block',
      isBlock: true,
      isLimit: true  // This is key!
    });
    // Restrict attributes within the jsonBlock (only plain text, no styles)
    schema.addAttributeCheck((context, attributeName) => {
      if (context.endsWith('jsonBlock')) {
        // Do not allow any formatting attributes in text within the JSON block
        return false;
      }
    });

    // **Upcast Conversion (view -> model)**: from <pre class="json-block"><code class="language-json"> to model
    conversion.for('upcast').elementToElement({
      view: {
        name: 'pre',
        classes: 'json-block'
      },
      model: (viewElement, { writer }) => {
        const jsonBlock = writer.createElement('jsonBlock');
      
        return jsonBlock;
      }
    });

    // Add dynamic highlighting using highlight.js without interfering with editing
    conversion.for('downcast').elementToElement({
      model: 'jsonBlock',
      view: (modelItem, conversionApi) => {
        const { writer } = conversionApi;

        const pre = writer.createContainerElement('pre', { class: 'json-block' });

        return pre;
      }
    });

    // Intercept the Enter key within jsonBlock to insert a new line instead of exiting the block
    editor.editing.view.document.on('enter', (evt, data) => {
      const pos = editor.model.document.selection.getFirstPosition();
      if (pos.parent.is('element', 'jsonBlock')) {
        editor.model.change(writer => {
          // Insert a newline character at the current position
          writer.insertText('\n', pos);
        });
        evt.stop();  // prevent default behavior (exiting or splitting the block)
      }
    }, { priority: 'high' });

    editor.editing.view.document.on('keydown', (evt, data) => {
      const selection = model.document.selection;
      const position = selection.getFirstPosition();

      // Detect if Tab is pressed
      if (data.keyCode === 9) {
        model.change(writer => {
            writer.insertText(''.padStart(TAB_SIZE, ' '), position);
        });

        data.preventDefault();
        evt.stop();
      }
    });

    editor.editing.view.document.on('blur', (evt, data) => {
      const pos = editor.model.document.selection.getFirstPosition();
      const jsonBlock = pos.findAncestor('jsonBlock');
      if (jsonBlock) {
        editor.model.change(writer => {
          let jsonTextWithMentions = '';
          let jsonMappingMentions = [];
          for (const child of jsonBlock.getChildren()) {
            if (child.is('$text')) {
              jsonTextWithMentions += child.data;
            } else if (child.hasAttribute('data-mention')) {
              const mention = child.getAttribute('data-mention');
              jsonTextWithMentions += '{{ ' + mention + ' }}'; // Use the mention name for JSON
              jsonMappingMentions[mention] = '<span class="mention" contenteditable="false" data-mention="' + mention + '" data-original="' + child.getAttribute('data-original') + '">' + child.getAttribute('data-original') + '</span>';
            } else if (child.name === 'softBreak') {
              jsonTextWithMentions += '\n';
            }
          }

          try {
            let formatted = JSON.stringify(JSON.parse(jsonTextWithMentions), null, 2);
            const mentionRegex = /\{\{\s*[a-zA-Z_]*?\s\}\}/;
            const splitLines = [];

            let remainingLine = formatted;
            let match;

            while ((match = mentionRegex.exec(remainingLine)) !== null) {
                const parts = remainingLine.split(match[0]);

                if (parts[0]) {
                    splitLines.push(parts[0]); // Agregar texto antes del mention
                }

                splitLines.push(jsonMappingMentions[match[0].replaceAll(/[\{\}\s]/g, '')]); // Agregar el mention como un nodo intermedio

                remainingLine = parts[1];
            }

            if (remainingLine) {
                splitLines.push(remainingLine); // Agregar texto después del mention
            }

            const previousChildren = Array.from(jsonBlock.getChildren());

            previousChildren.forEach((child) => {
              if (child.is('$text')) {
                writer.remove(child)
              }
            })

            let insertPosition = writer.createPositionAt(jsonBlock, 'end');

            for (const line of splitLines) {
              // If it has <>, it's a mention span
              if (line.startsWith('<') && line.endsWith('>')) {
                const mentionElement = writer.createElement('mention', {
                  'data-mention': line.match(/data-mention="([^"]+)"/)[1],
                  'data-original': line.match(/data-original="([^"]+)"/)[1]
                });

                writer.insert(mentionElement, insertPosition);
                // Actualizar la posición después de insertar
                insertPosition = writer.createPositionAfter(mentionElement);
              } else {
                  // Si es texto, insertarlo en la posición actual
                  writer.insertText(line, insertPosition);
                  // Actualizar la posición después de insertar
                  insertPosition = writer.createPositionAt(jsonBlock, 'end');
              }
            }

            previousChildren.forEach((child) => {
              if (!child.is('$text')) {
                writer.remove(child)
              }
            })


          } catch (e) {
            // If JSON is invalid, do nothing
          }
        });
      }
    });

    // Register the 'jsonBlock' command
    editor.commands.add('jsonBlock', new JsonBlockCommand(editor));

    // Selection management for highlighting/validation (see below)
    this._setupHighlightAndValidation();
  }

  _setupHighlightAndValidation() {
    const editor = this.editor;
    const model = editor.model;
    const view = editor.editing.view;

    // Detect changes in the JSON block content for validation
    model.document.on('change:data', () => {
      const jsonBlock = model.document.selection.getFirstPosition().findAncestor('jsonBlock');
      if (!jsonBlock) return;
      // Validate JSON syntax of the current content
      const jsonText = getJsonText(jsonBlock);
      let isValid = true;
      try {
        if (jsonText.trim() !== '') {
          JSON.parse(jsonText);
        }
      } catch (e) {
        isValid = false;
      }
      // Update CSS classes in the view based on validity (e.g., red/green border)
      const viewElement = editor.editing.mapper.toViewElement(jsonBlock);
      if (!viewElement) return; // ✅ Protect against errors if the block was removed

      view.change(writer => {
        writer.removeClass([ 'json-valid', 'json-invalid' ], viewElement);
        writer.addClass(isValid ? 'json-valid' : 'json-invalid', viewElement);
        writer.setAttribute('data-validity', isValid ? 'Valid JSON' : 'Invalid JSON', viewElement);
      });
    });
  }
}
