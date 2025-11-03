<!-- SelectField.vue -->
<template>
    <div class="mb-4">
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
            {{ label }}
        </label>

        <Multiselect v-model="selected" :options="computedOptions" :searchable="true" :loading="combinedLoading"
            :label="labelField" :track-by="trackBy" :placeholder="placeholder" :clear-on-select="true"
            :close-on-select="true" :disabled="disabled" @search-change="onSearchChange" @select="handleSelect"
            @remove="handleRemove" />
    </div>
</template>

<script>
import Multiselect from 'vue-multiselect'
import axios from 'axios'

export default {
    name: 'SelectField',
    components: { Multiselect },
    props: {
        modelValue: [String, Number, Object],
        label: String,
        placeholder: {
            type: String,
            default: 'Select...',
        },
        api: {
            type: String,
            default: ''
        },
        options: {
            type: Array,
            default: () => []
        },
        labelField: {
            type: String,
            default: 'CountryName'
        },
        trackBy: {
            type: String,
            default: 'CountryID'
        },
        disabled: {
            type: Boolean,
            default: false
        },
        loading: { // 🔥 NEW: Support loading prop dari parent
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            internalOptions: [],
            selected: null,
            internalLoading: false,
            searchTerm: '',
        }
    },
    computed: {
        computedOptions() {
            return this.options.length > 0 ? this.options : this.internalOptions
        },
        // 🔥 NEW: Combine loading dari prop dan internal loading
        combinedLoading() {
            return this.loading || this.internalLoading
        }
    },
    watch: {
        modelValue: {
            immediate: true,
            handler(val) {
                console.log('🔄 Watch modelValue:', val)

                if (val && this.computedOptions.length > 0) {
                    const found = this.computedOptions.find(option => option[this.trackBy] == val)
                    console.log('🔍 Found:', found)
                    this.selected = found || null
                } else {
                    this.selected = val
                }
            }
        },
        options: {
            immediate: true,
            handler(newOptions) {
                console.log('🔄 Options prop changed:', newOptions)
                if (newOptions.length > 0 && this.modelValue) {
                    const found = newOptions.find(option => option[this.trackBy] == this.modelValue)
                    this.selected = found || null
                }
            }
        }
    },
    mounted() {
        if (!this.api && this.options.length === 0) {
            console.warn('⚠️ SelectField: No API or options provided')
        } else if (this.api) {
            this.fetchOptions()
        }
    },
    methods: {
        async fetchOptions() {
            if (!this.api) return

            this.internalLoading = true
            try {
                const { data } = await axios.get(this.api)
                console.log('📥 Data dari API:', data)
                this.internalOptions = data
            } finally {
                this.internalLoading = false
            }
        },
        onSearchChange(value) {
            this.searchTerm = value
        },
        handleSelect(value) {
            const selectedId = value ? value[this.trackBy] : null
            console.log('🎯 Selected ID:', selectedId)
            this.$emit('update:modelValue', selectedId)
        },
        handleRemove() {
            console.log('🗑️ Selection removed, sending null')
            this.$emit('update:modelValue', null)
        }
    },
}
</script>

<style scoped>
.multiselect {
    width: 100%;
}

/* "Please enter to select" text - Indigo 600 */
:deep(.multiselect__content-wrapper) {
    background: white !important;
}

:deep(.multiselect__option--selected) {
    background: #4f46e5 !important;
    /* Indigo-600 */
    color: white !important;
}

/* Hover option - Indigo 500 */
:deep(.multiselect__option--highlight) {
    background: #6366f1 !important;
    /* Indigo-500 */
    color: white !important;
}

:deep(.multiselect__option--highlight:after) {
    background: #6366f1 !important;
    /* Indigo-500 */
}

/* LOADER YANG BENAR - Spinner mutar */
:deep(.multiselect__spinner) {
    background: transparent !important;
    /* Background transparan */
}

:deep(.multiselect__spinner:before),
:deep(.multiselect__spinner:after) {
    border-color: #6366f1 transparent transparent !important;
    /* Indigo-500 untuk spinner */
}

/* Selected option yang di-hover */
:deep(.multiselect__option--selected.multiselect__option--highlight) {
    background: #4338ca !important;
    /* Indigo-700 */
    color: white !important;
}

/* Tags */
:deep(.multiselect__tag) {
    background: #6366f1 !important;
    /* Indigo-500 */
    color: white !important;
}

:deep(.multiselect__tag-icon:focus),
:deep(.multiselect__tag-icon:hover) {
    background: #4338ca !important;
    /* Indigo-700 */
}

:deep(.multiselect__tag-icon:after) {
    color: white !important;
}
</style>