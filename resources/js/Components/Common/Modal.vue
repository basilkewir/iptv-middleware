<template>
    <Transition name="modal">
        <div v-if="show" class="modal-overlay" @click.self="$emit('close')">
            <div class="modal-container" :class="sizeClass">
                <div class="modal-header" v-if="title">
                    <h3 class="modal-title">{{ title }}</h3>
                    <button class="modal-close" @click="$emit('close')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <slot />
                </div>
                <div class="modal-footer" v-if="$slots.footer">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg', 'xl'].includes(value),
    },
})

defineEmits(['close'])

const sizeClass = computed(() => `modal-${props.size}`)
</script>

<style scoped>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 1rem;
}

.modal-container {
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    max-height: 90vh;
    overflow-y: auto;
    width: 100%;
}

.modal-sm {
    max-width: 24rem;
}

.modal-md {
    max-width: 32rem;
}

.modal-lg {
    max-width: 48rem;
}

.modal-xl {
    max-width: 64rem;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #ecf0f1;
}

.modal-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: #7f8c8d;
    cursor: pointer;
    padding: 0.25rem;
    transition: color 0.2s;
}

.modal-close:hover {
    color: #2c3e50;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #ecf0f1;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-active .modal-container,
.modal-leave-active .modal-container {
    transition: transform 0.3s ease;
}

.modal-enter-from .modal-container,
.modal-leave-to .modal-container {
    transform: scale(0.95);
}
</style>