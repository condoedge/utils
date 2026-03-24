<template>
    <div class="vlTabs"
        v-bind="$_layoutWrapperAttributes"
        v-show="!$_hidden">
        <ul role="tablist" class="flex">
            <li
                v-for="(element, index) in elements"
                :key="index"
                role="presentation"
            >
                <vl-form-tab-label
                    :activeTab="tabActive(index)"
                    :selectedClass="$_selectedClass"
                    :unselectedClass="$_unselectedClass"
                    :commonClass="$_commonClass"
                    :disabledClass="disabledClass"
                    :vkompo="element"
                    @selectTab="selectTab(index)" />
            </li>
        </ul>
        <div class="vlTabContent" :class="{ 'vlTabContent--swipeable': isSwipeable }">
            <div v-if="isSwipeable"
                 class="vlTabSwipeContainer"
                 :style="swipeContainerStyle"
                 @touchstart="onTouchStart"
                 @touchmove="onTouchMove"
                 @touchend="onTouchEnd">
                <component
                    v-for="(tab, index) in elements"
                    :key="index"
                    :activeTab="tabActive(index)"
                    :swipeable="true"
                    v-bind="$_attributes(tab)"/>
            </div>
            <template v-else>
                <component
                    v-for="(tab,index) in elements"
                    :key="index"
                    :activeTab="tabActive(index)"
                    v-bind="$_attributes(tab)"/>
            </template>
        </div>
    </div>
</template>

<script>
import Layout from 'vue-kompo/js/form/mixins/Layout'
import HasSelectedClass from 'vue-kompo/js/form/mixins/HasSelectedClass'

export default {
    mixins: [Layout, HasSelectedClass],
    data(){
        return {
            activeTab: 0,
            // Swipe state
            swipeOffset: 0,
            isSwiping: false,
            isAnimating: false,
            startX: 0,
            startY: 0,
            directionLocked: false,
        }
    },
    computed: {
        defaultActiveTab(){
            return this.$_config('activeTab')
        },
        tabParamKey(){
            return 'tab_number'
        },
        selectId(){
            return this.$_config('selectId')
        },
        disabledClass(){
            return this.$_config('disabledClass')
        },
        isSwipeable(){
            return !!this.$_config('swipeable')
        },
        swipeContainerStyle(){
            var baseOffset = -this.activeTab * 100

            if (this.isSwiping) {
                return {
                    transform: 'translateX(calc(' + baseOffset + '% + ' + this.swipeOffset + 'px))',
                    transition: 'none',
                }
            }

            return {
                transform: 'translateX(' + baseOffset + '%)',
                transition: this.isAnimating ? 'transform 0.3s ease' : 'none',
            }
        },
    },
    methods:{
        $_validate(errors) {
            Layout.methods.$_validate.call(this, errors)
            this.elements.forEach( (tab, index) => {
                var errors = []
                tab.$_getErrors(errors)
                if(errors.length)
                    this.activeTab = index
            })
        },
        selectTab(index) {
            if (this.isSwipeable) {
                this.isAnimating = true
            }

            this.activeTab = index
            this.persistActiveTab()
            this.syncSelect()

            this.$_runOwnInteractions('click')

            if (this.isSwipeable) {
                setTimeout(() => {
                    this.isAnimating = false
                }, 300)
            }
        },
        tabActive(index) {
            return index == this.activeTab
        },
        tabDisabled(index) {
            return this.elements[index].isDisabled
        },

        // --- Swipe methods ---

        beginSwipe(startX, startY) {
            this.startX = startX
            this.startY = startY
            this.swipeOffset = 0
            this.isSwiping = false
            this.directionLocked = false
        },

        updateSwipe(clientX, clientY, preventDefault) {
            if (this.isAnimating) return

            var deltaX = clientX - this.startX
            var deltaY = clientY - this.startY

            // Lock direction on first significant movement
            if (!this.directionLocked) {
                if (Math.abs(deltaX) < 5 && Math.abs(deltaY) < 5) return

                if (Math.abs(deltaY) > Math.abs(deltaX)) {
                    // Vertical scroll — do nothing, let browser handle it
                    this.directionLocked = true
                    this.isSwiping = false
                    return
                }

                this.directionLocked = true
                this.isSwiping = true
            }

            if (!this.isSwiping) return

            if (preventDefault)
                preventDefault()

            // Boundary resistance: dampen swipe at edges
            if (this.activeTab === 0 && deltaX > 0) {
                deltaX = deltaX * 0.3
            } else if (this.activeTab === this.elements.length - 1 && deltaX < 0) {
                deltaX = deltaX * 0.3
            }

            this.swipeOffset = deltaX
        },

        completeSwipe() {
            if (!this.isSwiping) {
                this.directionLocked = false
                return
            }

            var container = this.$el.querySelector('.vlTabContent')
            var containerWidth = container ? container.offsetWidth : 1
            var swipePercent = Math.abs(this.swipeOffset) / containerWidth
            var swipedLeft = this.swipeOffset < 0
            var swipedRight = this.swipeOffset > 0

            this.isSwiping = false
            this.isAnimating = true

            if (swipePercent >= 0.4) {
                // Find next valid (non-disabled) tab
                var nextIndex = this.activeTab

                if (swipedLeft && this.activeTab < this.elements.length - 1) {
                    nextIndex = this.findNextEnabledTab(this.activeTab, 1)
                } else if (swipedRight && this.activeTab > 0) {
                    nextIndex = this.findNextEnabledTab(this.activeTab, -1)
                }

                if (nextIndex !== this.activeTab) {
                    this.activeTab = nextIndex
                    this.persistActiveTab()
                    this.syncSelect()
                    this.$_runOwnInteractions('click')
                }
            }
            // else: snap back (activeTab unchanged, animation brings it back)

            this.swipeOffset = 0

            setTimeout(() => {
                this.isAnimating = false
            }, 300)
        },

        onTouchStart(e) {
            if (this.isAnimating) return

            var touch = e.touches[0]
            this.beginSwipe(touch.clientX, touch.clientY)
        },

        onTouchMove(e) {
            var touch = e.touches[0]
            this.updateSwipe(touch.clientX, touch.clientY, () => e.preventDefault())
        },

        onTouchEnd() {
            this.completeSwipe()
        },

        findNextEnabledTab(fromIndex, direction) {
            var nextIndex = fromIndex + direction

            while (nextIndex >= 0 && nextIndex < this.elements.length) {
                if (!this.elements[nextIndex].isDisabled) {
                    return nextIndex
                }
                nextIndex += direction
            }

            return fromIndex // No valid tab found, stay put
        },

        persistActiveTab() {
            if (!this.tabParamKey || typeof window === 'undefined') return

            var url = new URL(window.location.href)
            url.searchParams.set(this.tabParamKey, this.activeTab)
            window.history.replaceState({}, '', url.toString())
        },

        getPersistedTab() {
            if (!this.tabParamKey || typeof window === 'undefined') return null

            var params = new URLSearchParams(window.location.search)
            var tabParam = params.get(this.tabParamKey)

            if (tabParam === null) return null

            var parsed = parseInt(tabParam, 10)
            return isNaN(parsed) ? null : parsed
        },

        syncSelect() {
            if (!this.selectId || typeof window === 'undefined') return

            this.$nextTick(() => {
                var selectEl = document.getElementById(this.selectId)
                if (!selectEl) return

                var wrapper = selectEl.closest('.vlInputWrapper') || selectEl.parentElement
                if (!wrapper) return

                var options = wrapper.querySelectorAll('.vlOption')
                if (!options || !options.length) return

                options.forEach((opt, i) => {
                    opt.classList.toggle('vlSelected', i === this.activeTab)
                })

                var display = wrapper.querySelector('.vlSelectInput') || wrapper.querySelector('input[type="text"]')
                if (display && options[this.activeTab]) {
                    display.value = options[this.activeTab].textContent.trim()
                }
            })
        },
    },
    created(){
        var persistedTab = this.getPersistedTab()

        if(persistedTab !== null){
            this.activeTab = persistedTab
        }else if(this.defaultActiveTab !== undefined && this.defaultActiveTab !== null){
            this.activeTab = this.defaultActiveTab
        }
    },
    mounted(){
        this.syncSelect()
    },
}
</script>
