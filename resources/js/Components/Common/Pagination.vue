<template>
    <nav v-if="links.length > 1" class="pagination">
        <ul class="pagination-list">
            <li v-for="(link, index) in links" :key="index" class="pagination-item">
                <button
                    v-if="link.url"
                    @click="$emit('page-change', link.url)"
                    :class="['pagination-link', { active: link.active }]"
                    :disabled="!link.url"
                    v-html="link.label"
                ></button>
                <span v-else class="pagination-link disabled" v-html="link.label"></span>
            </li>
        </ul>
    </nav>
</template>

<script setup>
defineProps({
    links: {
        type: Array,
        required: true,
    },
});

defineEmits(['page-change']);
</script>

<style scoped>
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
}

.pagination-list {
    display: flex;
    gap: 0.25rem;
    list-style: none;
    padding: 0;
    margin: 0;
}

.pagination-link {
    padding: 0.5rem 0.75rem;
    border: 1px solid #d5dbdb;
    border-radius: 0.375rem;
    background: white;
    color: #2c3e50;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.875rem;
}

.pagination-link:hover:not(.disabled):not(.active) {
    background: #ecf0f1;
}

.pagination-link.active {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

.pagination-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>