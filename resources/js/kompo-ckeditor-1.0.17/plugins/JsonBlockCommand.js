import {Command} from 'ckeditor5';

export class JsonBlockCommand extends Command {
    refresh() {
        const model = this.editor.model;
        const selection = model.document.selection;
        // Determine if the command can be used at the current position
        const allowedParent = model.schema.findAllowedParent(selection.getFirstPosition(), 'jsonBlock');
        this.isEnabled = allowedParent !== null;
        // Determine state: true if the selection is inside an existing JSON block
        const jsonBlock = selection.getFirstPosition().findAncestor('jsonBlock');
        this.value = !!jsonBlock;
    }

    execute() {
        const model = this.editor.model;
        const selection = model.document.selection;
        const jsonBlockElem = selection.getFirstPosition().findAncestor('jsonBlock');

        model.change(writer => {
            if (jsonBlockElem) {
                // Move the selection out of the block before deleting it
                const nextPosition = writer.createPositionBefore(jsonBlockElem);
                writer.setSelection(nextPosition);

                // Get the text of the jsonBlock
                const jsonText = getJsonText(jsonBlockElem);
                const lines = jsonText.split('\n');

                let prevParagraph = null;
                for (let i = 0; i < lines.length; i++) {
                    const paragraph = writer.createElement('paragraph');
                    if (lines[i] !== '') {
                        writer.insertText(lines[i], paragraph, 0);
                    }
                    if (prevParagraph) {
                        writer.insert(paragraph, writer.createPositionAfter(prevParagraph));
                    } else {
                        writer.insert(paragraph, writer.createPositionBefore(jsonBlockElem));
                    }
                    prevParagraph = paragraph;
                }

                // Remove the JSON block
                writer.remove(jsonBlockElem);

                // Place the cursor back in the last inserted paragraph
                if (prevParagraph) {
                    writer.setSelection(prevParagraph, 'end');
                }
            } else {
                // Insert: create a new jsonBlock element at the current position
                const jsonBlock = writer.createElement('jsonBlock');
                // If there was selected content, move it as text inside the new JSON block
                if (!selection.isCollapsed) {
                    const plainText = getSelectionPlainText(selection);
                    if (plainText) {
                        writer.insertText(plainText, jsonBlock, 0);
                    }
                }
                model.insertContent(jsonBlock);
                // Place cursor inside the JSON block to start editing
                writer.setSelection(jsonBlock, 'end');
            }
        });
    }
}

// Helper function: gets the plain text (with \n) of a jsonBlock element from the model
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

// Helper function: converts the selected content (model.DocumentSelection) to plain text
function getSelectionPlainText(selection) {
    const fragment = selection.getFirstRange() ? selection.getFirstRange().clone().getItems() : [];
    let text = '';
    // Iterate through all nodes within the selection (fragment)
    for (const node of fragment) {
        if (node.is('$text')) {
            text += node.data;
        } else if (node.is('element')) {
            if (node.name === 'softBreak') {
                text += '\n';
            } else if (selection.containsEntireContent(node)) {
                // If it's a fully selected block element, add its text with a newline
                text += getJsonText(node) + '\n';
            }
        }
    }
    return text;
}
