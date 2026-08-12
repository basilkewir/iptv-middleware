<template>
  <div class="w-full aspect-video bg-gray-900 rounded-lg overflow-hidden">
    <video ref="videoEl" :src="src" controls class="w-full h-full object-contain"></video>
  </div>
</template>

<script setup>
defineProps({ src: String, channel: Object })
</script>
