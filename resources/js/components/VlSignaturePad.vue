<template>
    <vl-form-field v-bind="$_wrapperAttributes">
        <div class="signature-pad-container">
            <canvas
                ref="canvas"
                :width="canvasWidth"
                :height="canvasHeight"
                class="signature-canvas"
                @mousedown="startDrawing"
                @mousemove="draw"
                @mouseup="stopDrawing"
                @mouseleave="stopDrawing"
                @touchstart="startDrawing"
                @touchmove="draw"
                @touchend="stopDrawing"
            ></canvas>
            <button
                type="button"
                :class="clearButtonClass"
                @click="clearSignature"
            >
                {{ clearButtonText }}
            </button>
        </div>
    </vl-form-field>
</template>

<script>
import Field from 'vue-kompo/js/form/mixins/Field'
import HasInputAttributes from 'vue-kompo/js/form/mixins/HasInputAttributes'

export default {
    mixins: [Field, HasInputAttributes],
    data() {
        return {
            isDrawing: false,
            context: null,
            lastX: 0,
            lastY: 0,
            isEmpty: true
        }
    },
    computed: {
        penColor() { return this.$_config('penColor') || '#000000' },
        penWidth() { return this.$_config('penWidth') || 2 },
        canvasWidth() { return this.$_config('canvasWidth') || 500 },
        canvasHeight() { return this.$_config('canvasHeight') || 200 },
        backgroundColor() { return this.$_config('backgroundColor') || '#ffffff' },
        clearButtonText() { return this.$_config('clearButtonText') || 'Effacer' },
        clearButtonClass() { return this.$_config('clearButtonClass') || 'px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded text-sm' }
    },
    methods: {
        initCanvas() {
            const canvas = this.$refs.canvas
            this.context = canvas.getContext('2d')

            // Fill background
            this.context.fillStyle = this.backgroundColor
            this.context.fillRect(0, 0, this.canvasWidth, this.canvasHeight)

            // Set drawing style
            this.context.strokeStyle = this.penColor
            this.context.lineWidth = this.penWidth
            this.context.lineCap = 'round'
            this.context.lineJoin = 'round'

            // Load existing signature if any
            if (this.component.value) {
                this.loadSignature(this.component.value)
            }
        },

        getCoordinates(event) {
            const canvas = this.$refs.canvas
            const rect = canvas.getBoundingClientRect()

            // Calculer le ratio entre la taille affichée et la taille réelle du canvas
            const scaleX = canvas.width / rect.width
            const scaleY = canvas.height / rect.height

            if (event.touches && event.touches.length > 0) {
                return {
                    x: (event.touches[0].clientX - rect.left) * scaleX,
                    y: (event.touches[0].clientY - rect.top) * scaleY
                }
            }

            return {
                x: (event.clientX - rect.left) * scaleX,
                y: (event.clientY - rect.top) * scaleY
            }
        },

        startDrawing(event) {
            event.preventDefault()
            this.isDrawing = true
            const coords = this.getCoordinates(event)
            this.lastX = coords.x
            this.lastY = coords.y
        },

        draw(event) {
            if (!this.isDrawing) return

            event.preventDefault()
            const coords = this.getCoordinates(event)

            this.context.beginPath()
            this.context.moveTo(this.lastX, this.lastY)
            this.context.lineTo(coords.x, coords.y)
            this.context.stroke()

            this.lastX = coords.x
            this.lastY = coords.y
            this.isEmpty = false

            this.saveSignature()
        },

        stopDrawing() {
            this.isDrawing = false
        },

        clearSignature() {
            this.context.fillStyle = this.backgroundColor
            this.context.fillRect(0, 0, this.canvasWidth, this.canvasHeight)
            this.isEmpty = true
            this.component.value = null
            this.$emit('input', null)
        },

        saveSignature() {
            if (!this.isEmpty) {
                const dataURL = this.$refs.canvas.toDataURL('image/png')
                this.component.value = dataURL
                this.$emit('input', dataURL)
            }
        },

        loadSignature(dataURL) {
            const img = new Image()
            img.onload = () => {
                this.context.drawImage(img, 0, 0)
                this.isEmpty = false
            }
            img.src = dataURL
        }
    },
    mounted() {
        this.initCanvas()
    }
}
</script>

<style scoped>
.signature-pad-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.signature-canvas {
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    cursor: crosshair;
    touch-action: none;
    display: block;
}

.signature-canvas:hover {
    border-color: #9ca3af;
}
</style>
