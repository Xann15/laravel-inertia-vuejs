<!-- Components/CustomLoader.vue -->
<template>
    <div class="absolute inset-0 bg-white bg-opacity-80 z-10 flex items-center justify-center rounded-lg">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p class="text-gray-600 font-semibold">{{ message }}</p>
        </div>
    </div>
</template>

<script setup>
defineProps({
    message: {
        type: String,
        default: 'Loading data...'
    }
})
</script>