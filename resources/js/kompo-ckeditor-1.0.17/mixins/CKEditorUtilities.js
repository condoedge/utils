export default {
    methods: {
        insertVariable(payload) {
            // Insertar el span sin espacios adicionales para evitar problemas con el cursor
            const html = '<span class="mention" contenteditable="false" data-mention="' + payload.type + '" data-original="' + payload.label + '">' + payload.label + '</span>'
            this.insertHtml(html)

            // Ahora asegurémonos de que el cursor quede correctamente posicionado después del span
            const editor = this.$refs.content?.$_instance
            editor.model.change(writer => {
                // Este paso adicional ayuda a asegurar que el cursor quede en una posición editable
                const selection = editor.model.document.selection
                if (!selection.isCollapsed) {
                    writer.setSelection(selection.getLastPosition())
                }
            })

            this.variableToInsert = null
        },
        insertHtml(html) {
            const editor = this.$refs.content?.$_instance

            if (!editor) return

            editor.model.change(writer => {
                // Si el editor está vacío (no hay ningún bloque), creamos un <paragraph> de arranque
                if (editor.model.document.getRoot().childCount === 0) {
                    const p = writer.createElement('paragraph')
                    writer.insert(p, editor.model.document.getRoot(), 0)
                    // Movemos la selección al inicio del nuevo párrafo
                    writer.setSelection(p, 0)
                }

                // Procesar la cadena HTML al modelo
                const viewFragment = editor.data.processor.toView(html)
                const modelFragment = editor.data.toModel(viewFragment)

                // Insertar el fragmento en la posición de la selección
                editor.model.insertContent(modelFragment, editor.model.document.selection)
            })
        },
        insertText(text) {
            const editor = this.$refs.content?.$_instance
            if (!editor) return
            
            editor.model.change(writer => {
                const insertPosition = editor.model.document.selection.getFirstPosition()
                writer.insertText(text, insertPosition)
            })
        },
    },
}