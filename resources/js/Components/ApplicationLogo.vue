<template>
    <div class="flex items-center gap-2">
        <!-- Logo Image -->
        <img 
            :src="logoUrl" 
            :alt="altText" 
            :class="[
                'transition-all duration-300',
                sizeClasses
            ]" 
        />
        
        <!-- Text (opsional) -->
        <span 
            v-if="showText" 
            :class="[
                'font-bold text-gray-800 transition-all duration-300',
                textSizeClasses
            ]"
        >
            {{ logoText }}
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue'

// Props
const props = defineProps({
    size: {
        type: String,
        default: 'medium', // small, medium, large
        validator: (value) => ['small', 'medium', 'large'].includes(value)
    },
    showText: {
        type: Boolean,
        default: true
    },
    altText: {
        type: String,
        default: 'FO Cloud Logo'
    },
    logoText: {
        type: String,
        default: 'FO Cloud'
    }
})

// Computed properties
const logoUrl = computed(() => '/images/focloud.png')

const sizeClasses = computed(() => {
    const sizes = {
        small: 'h-6 w-auto',
        medium: 'h-8 w-auto', 
        large: 'h-12 w-auto'
    }
    return sizes[props.size]
})

const textSizeClasses = computed(() => {
    const sizes = {
        small: 'text-sm',
        medium: 'text-xl', 
        large: 'text-2xl'
    }
    return sizes[props.size]
})
</script>

<!-- Basic usage dengan text default "FO Cloud" -->
<!-- <ApplicationLogo /> -->

<!-- Dengan text custom -->
<!-- <ApplicationLogo logo-text="FO Cloud 2026" /> -->

<!-- Dengan ukuran berbeda dan text custom -->
<!-- <ApplicationLogo size="small" logo-text="FOC" /> -->
<!-- <ApplicationLogo size="large" logo-text="FO Cloud System" /> -->

<!-- Tanpa text -->
<!-- <ApplicationLogo :show-text="false" /> -->

<!-- Custom alt text dan logo text -->
<!-- <ApplicationLogo 
    alt-text="FO Cloud Hotel Management" 
    logo-text="Hotel Management" 
/> -->

<!-- Kombinasi lengkap -->
<!-- <ApplicationLogo 
    size="large" 
    logo-text="Front Office Cloud" 
    alt-text="FO Cloud Hotel System"
    :show-text="true"
/> -->