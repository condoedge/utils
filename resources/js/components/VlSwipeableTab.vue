<template>
    <section
        v-if="!isDisabled"
        :aria-hidden="!activeTab"
        v-show="swipeable || activeTab"
        v-bind="$_layoutWrapperAttributes"
        class="vlTab"
        :class="{ 'vlTab--swipeable': swipeable }"
        role="tabpanel">

        <component
            v-bind="$_attributes(tab)"
            v-for="(tab,index) in elements"
            :key="index" />

    </section>
</template>

<script>
import Layout from 'vue-kompo/js/form/mixins/Layout'

export default {
    mixins: [Layout],
    props: {
        activeTab: { type: Boolean, required: true },
        swipeable: { type: Boolean, default: false },
    },
    computed: {
        isDisabled(){
            return this.$_config('tabDisabled')
        },
    },
}
</script>
