<template>
  <div class="relative" ref="dropdownRef">
    <div @click="open = !open">
      <slot name="trigger" />
    </div>

    <Transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-show="open"
        :class="[
          'absolute z-50 mt-2 rounded-lg shadow-lg bg-gray-800 border border-gray-700 ring-1 ring-black ring-opacity-5',
          widthClass,
          alignmentClass
        ]"
      >
        <div class="py-1">
          <slot name="content" />
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  align: {
    type: String,
    default: 'left',
  },
  width: {
    type: String,
    default: '48',
  },
})

const dropdownRef = ref(null)
const open = ref(false)

const widthClass = computed(() => {
  const widths = {
    '48': 'w-48',
    '56': 'w-56',
    '64': 'w-64',
    '72': 'w-72',
  }
  return widths[props.width] || 'w-48'
})

const alignmentClass = computed(() => {
  const alignments = {
    left: 'left-0',
    right: 'right-0',
  }
  return alignments[props.align] || 'left-0'
})

const close = (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('click', close))
onUnmounted(() => document.removeEventListener('click', close))
</script>
