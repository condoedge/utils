<template>
    <div>
        <div
            v-if="!$_displayNone"
            v-show="!$_hidden"
            v-bind="$_attributes"
            v-html="content"
        />
        <button :style="buttonStyle" :class="buttonClass" v-if="showMoreBtn" @click.stop="() => showingMore = !showingMore">{{showingMore ? showLessText : showMoreText}}</button>
   </div>
</template>

<script>
import Other from 'vue-kompo/js/form/mixins/Other'

export default {
    mixins: [Other],
    computed: {
        content() {
            const originalContent = this.$_config('content');
            let content = originalContent;

            if (this.maxLines != null && !this.showingMore) {
                content = content.split('\r\n').slice(0, this.maxLines).join('\r\n');
            }

            if (this.maxChars != null && !this.showingMore) {
                content = content.slice(0, this.maxChars);
            }

            if (originalContent != content) {
                content += '...';
            }

            return content;
        },
        showMoreBtn() {
            return (this.maxLines != null || this.maxChars != null) && 
                (this.$_config('content').length != this.content.length || this.showingMore);
        }
    },
    data() {
        return {
            maxLines: null,
            maxChars: null,

            showMoreText: '',
            showLessText: '',

            buttonClass: '',
            buttonStyle: '',

            showingMore: false,
        }
    },
    mounted() {
        this.maxLines = this.$_config('maxLines') ?? this.maxLines;
        this.maxChars = this.$_config('maxChars') ?? this.maxChars;

        this.showMoreText = this.$_config('showMoreText') ?? this.showMoreText;
        this.showLessText = this.$_config('showLessText') ?? this.showLessText;

        this.buttonClass = this.$_config('buttonClass') ?? this.buttonClass;
        this.buttonStyle = this.$_config('buttonStyle') ?? this.buttonStyle;
    },
}
</script>
