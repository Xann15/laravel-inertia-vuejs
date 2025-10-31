<!-- Components/GuestGridModal.vue -->
<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { DxDataGrid, DxColumn, DxPaging, DxFilterRow, DxSearchPanel, DxSelection, DxLoadPanel, DxScrolling, DxPager } from 'devextreme-vue/data-grid'

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    guests: {
        type: Array,
        default: () => []
    },
    totalCount: {
        type: Number,
        default: 0
    },
    initialSearchValue: {
        type: String,
        default: ''
    },
    isLoading: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['close', 'select-guest', 'load-guests'])

// 🔹 State untuk search dan filter
const isLoadingInternal = ref(false)
const showSearchWarning = ref(false)
const searchWarningTimer = ref(null)
const dataGridInstance = ref(null)

// Search state
const searchValue = ref('')
const selectedFields = ref(['GuestName']) // Default checked: GuestName
const selectAllFields = ref(false)
const hasSearched = ref(false) // Flag untuk menandai apakah sudah melakukan search

// DataGrid state
const currentPage = ref(0)
const pageSize = ref(20)

// 🔹 Computed untuk data source - KOSONG jika belum search
const dataGridDataSource = computed(() => {
    if (!hasSearched.value) {
        return {
            store: {
                type: 'array',
                data: [],
                key: 'clientID'
            },
            paginate: true,
            pageSize: pageSize.value
        }
    }
    
    return {
        store: {
            type: 'array',
            data: formattedGuests.value,
            key: 'clientID'
        },
        paginate: true,
        pageSize: pageSize.value
    }
})

// 🔹 Watch untuk modal show/hide
watch(() => props.show, (newVal) => {
    if (newVal) {
        console.log('🔹 Modal opened with initialSearchValue:', props.initialSearchValue)
        
        // Reset state - TIDAK AUTO LOAD
        currentPage.value = 0
        searchValue.value = props.initialSearchValue || ''
        hasSearched.value = false // Reset search flag
        
        // Auto focus search input
        nextTick(() => {
            setTimeout(() => {
                const searchInput = document.getElementById('searchInput')
                if (searchInput) {
                    searchInput.focus()
                    searchInput.select()
                }
            }, 100)
        })
    } else {
        // Reset ketika modal ditutup
        searchValue.value = ''
        selectedFields.value = ['GuestName']
        selectAllFields.value = false
        showSearchWarning.value = false
        hasSearched.value = false
        
        if (searchWarningTimer.value) {
            clearTimeout(searchWarningTimer.value)
        }
    }
})

// 🔹 Watch untuk select all fields
watch(selectAllFields, (newVal) => {
    if (newVal) {
        selectedFields.value = ['GuestName', 'TypeIDNumber', 'Address', 'Phone', 'Email', 'City']
    } else {
        selectedFields.value = ['GuestName']
    }
})

// 🔹 Watch untuk individual field changes
watch(selectedFields, (newVal) => {
    if (newVal.length === 6) { // Total 6 fields available
        selectAllFields.value = true
    } else if (newVal.length < 6) {
        selectAllFields.value = false
    }
})

// 🔹 Load guests function
const loadGuests = () => {
    console.log('🔄 loadGuests called with search:', searchValue.value)

    // Validasi minimal 3 karakter
    if (searchValue.value && searchValue.value.length < 3) {
        console.log('⚠️ Search too short, showing warning')
        showWarning()
        return
    }

    isLoadingInternal.value = true
    hasSearched.value = true // Set flag bahwa sudah melakukan search

    const params = {
        skip: currentPage.value * pageSize.value,
        take: pageSize.value,
    }

    // Add search value jika ada dan valid
    if (searchValue.value && searchValue.value.length >= 3) {
        params.searchValue = searchValue.value
    }

    // Add selected fields
    if (selectedFields.value.length > 0) {
        params.fields = selectedFields.value.join(',')
    }

    console.log('📤 Emitting load-guests with params:', params)
    emit('load-guests', params)
}

// 🔹 Search handler
const handleSearch = () => {
    currentPage.value = 0
    loadGuests()
}

// 🔹 Enter key handler untuk search
const handleKeypress = (e) => {
    if (e.key === 'Enter') {
        handleSearch()
    }
}

// 🔹 Show warning
const showWarning = () => {
    showSearchWarning.value = true

    if (searchWarningTimer.value) {
        clearTimeout(searchWarningTimer.value)
    }

    searchWarningTimer.value = setTimeout(() => {
        showSearchWarning.value = false
    }, 3000)
}

// 🔹 Format guest data
const formattedGuests = computed(() => {
    return props.guests.map(guest => ({
        clientID: guest.clientID,
        TypeIDNumber: guest.TypeIDNumber || '',
        GuestName: guest.GuestName || `${guest.FirstName || ''} ${guest.LastName || ''}`.trim(),
        Phone: guest.Phone || '',
        Address: guest.Address || '',
        City: guest.City || '',
        CountryName: guest.CountryName || '',
        CompName: guest.CompName || '',
        cBlackList: guest.cBlackList ? 'YES' : 'NO',
        Language: guest.Language || '',
        CountryID: guest.CountryID || '',
        TypeID: guest.TypeID || '',
        FirstName: guest.FirstName || '',
        LastName: guest.LastName || '',
        Email: guest.Email || '',
        BirthDate: guest.BirthDate || '',
        CompID: guest.CompID || '',
        cGroupName: guest.cGroupName || '',
        // Tambahkan field untuk kompatibilitas
        guestName: guest.GuestName || `${guest.FirstName || ''} ${guest.LastName || ''}`.trim(),
        title: guest.TypeID || 'Mr',
        idType: guest.TypeID || 'KTP',
        idNumber: guest.TypeIDNumber || '',
        nationality: guest.CountryName || 'Indonesia',
        birthDate: guest.BirthDate || '',
        guestType: 'Individual',
        vip: ''
    }))
})

// 🔹 Close modal
function closeModal() {
    emit('close')
}

// 🔹 Handle guest selection
function handleSelectGuest(e) {
    if (e.selectedRowsData && e.selectedRowsData.length > 0) {
        const selectedGuest = e.selectedRowsData[0]
        console.log('✅ Guest selected from grid:', selectedGuest)
        emit('select-guest', selectedGuest)
        closeModal()
    }
}

function onRowDblClick(e) {
    console.log('👆 Guest double-clicked:', e.data)
    emit('select-guest', e.data)
    closeModal()
}

// 🔹 DataGrid events
function onContentReady(e) {
    console.log('✅ DataGrid content ready')
    isLoadingInternal.value = false

    if (!dataGridInstance.value) {
        dataGridInstance.value = e.component
    }
}

function onOptionChanged(e) {
    if (e.fullName === 'paging.pageIndex') {
        console.log('📄 Page changed to:', e.value)
        currentPage.value = e.value
        loadGuests()
    } else if (e.fullName === 'paging.pageSize') {
        console.log('📏 Page size changed to:', e.value)
        pageSize.value = e.value
        currentPage.value = 0
        loadGuests()
    }
}

// 🔹 Custom cell templates
const blackListCellTemplate = (cellData) => {
    const isBlacklisted = cellData.value === 'YES'
    return `
    <span class="px-2 py-1 rounded-full text-xs font-semibold ${
        isBlacklisted 
            ? 'bg-red-100 text-red-800 border border-red-200' 
            : 'bg-green-100 text-green-800 border border-green-200'
    }">
        ${isBlacklisted ? '🚫 Blacklisted' : '✅ Active'}
    </span>
    `
}

const guestNameCellTemplate = (cellData) => {
    return `
    <div class="flex flex-col">
        <span class="font-semibold text-gray-800">${cellData.value || ''}</span>
        <span class="text-xs text-gray-500">ID: ${cellData.data.clientID || ''}</span>
    </div>
    `
}

// 🔹 Highlight search text in cells
const highlightSearchCellTemplate = (cellData) => {
    if (!searchValue.value || searchValue.value.length < 3) {
        return cellData.value || ''
    }

    const text = cellData.value || ''
    const searchRegex = new RegExp(`(${escapeRegex(searchValue.value)})`, 'gi')
    const highlightedText = text.replace(searchRegex, '<mark class="bg-yellow-200 px-1 rounded">$1</mark>')

    return highlightedText
}

const escapeRegex = (string) => {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0"
            enter-to-class="opacity-100" leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                @click.self="closeModal">
                <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100" leave-active-class="transition-all duration-200"
                    leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                    <div v-if="show"
                        class="bg-white rounded-2xl shadow-2xl w-full max-w-7xl max-h-[95vh] flex flex-col relative modal-fullscreen-custom"
                        @click.stop>
                        
                        <!-- Search Warning Popover -->
                        <Transition enter-active-class="transition-all duration-200"
                            enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition-all duration-200"
                            leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-2">
                            <div v-if="showSearchWarning"
                                class="absolute top-32 left-1/2 transform -translate-x-1/2 z-10 bg-amber-500 text-white px-6 py-3 rounded-lg shadow-xl flex items-center gap-2">
                                <span class="text-xl">⚠️</span>
                                <span class="font-semibold">Minimum search is 3 characters</span>
                            </div>
                        </Transition>

                        <!-- Modal Header -->
                        <div class="flex justify-between items-center mx-2 mt-4 px-4">
                            <h4 class="fw-bold my-auto text-2xl font-bold text-gray-800">Select Guest</h4>
                            <div class="flex gap-2">
                                <button type="button" 
                                    @click="handleSelectGuest"
                                    :disabled="!hasSearched || formattedGuests.length === 0"
                                    :class="[
                                        'px-4 py-2 text-white font-bold rounded-lg transition-colors',
                                        hasSearched && formattedGuests.length > 0
                                            ? 'bg-green-500 hover:bg-green-600'
                                            : 'bg-gray-400 cursor-not-allowed'
                                    ]">
                                    Select
                                </button>
                                <button type="button" 
                                    @click="closeModal"
                                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white font-bold rounded-lg transition-colors">
                                    Close
                                </button>
                            </div>
                        </div>

                        <!-- Modal Body -->
                        <div class="modal-body flex-1 overflow-hidden p-6">
                            <!-- Search Bar -->
                            <div class="flex items-center mb-4">
                                <div class="w-1/6 flex">
                                    <h5 class="my-auto text-lg font-semibold text-gray-700">Search Guest</h5>
                                </div>

                                <div class="w-2/3 flex gap-2">
                                    <input type="text" 
                                        id="searchInput"
                                        v-model="searchValue"
                                        @keypress="handleKeypress"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="Enter at least 3 characters to search..."
                                        :title="searchValue && searchValue.length < 3 ? 'Minimal 3 karakter diperlukan' : ''" />
                                    <button @click="handleSearch" 
                                        class="px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        Search
                                    </button>
                                </div>
                                <div class="w-1/6"></div>
                            </div>

                            <!-- Search By -->
                            <div class="w-3/4 mx-auto flex justify-between items-center p-4 mb-6 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="w-1/6 flex">
                                    <p class="my-auto mb-0 font-semibold text-gray-700">Search By:</p>
                                </div>
                                <div class="w-1/5 flex">
                                    <div class="flex items-center">
                                        <input class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500" 
                                            type="checkbox" 
                                            value="GuestName"
                                            v-model="selectedFields"
                                            id="searchGuestName" />
                                        <label class="ml-2 text-sm text-gray-700" for="searchGuestName">
                                            Guest Name
                                        </label>
                                    </div>
                                </div>
                                <div class="w-1/6 flex">
                                    <div class="flex items-center">
                                        <input class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500" 
                                            type="checkbox" 
                                            value="TypeIDNumber"
                                            v-model="selectedFields"
                                            id="searchIDNumber" />
                                        <label class="ml-2 text-sm text-gray-700" for="searchIDNumber">
                                            ID Number
                                        </label>
                                    </div>
                                </div>
                                <div class="w-1/6 flex">
                                    <div class="flex items-center">
                                        <input class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500" 
                                            type="checkbox" 
                                            value="Address"
                                            v-model="selectedFields"
                                            id="searchAddress" />
                                        <label class="ml-2 text-sm text-gray-700" for="searchAddress">
                                            Address
                                        </label>
                                    </div>
                                </div>
                                <div class="w-1/4 flex">
                                    <div class="flex items-center">
                                        <input class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500" 
                                            type="checkbox" 
                                            v-model="selectAllFields"
                                            id="selectAllFields" />
                                        <label class="ml-2 text-sm text-gray-700" for="selectAllFields">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty State / Data Grid -->
                            <div class="border border-gray-200 rounded-lg" style="height: 60vh;">
                                <!-- Empty State -->
                                <div v-if="!hasSearched" class="h-full flex flex-col items-center justify-center bg-gray-50 rounded-lg">
                                    <div class="text-center">
                                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                        <h3 class="text-lg font-semibold text-gray-700 mb-2">Search for Guests</h3>
                                        <p class="text-gray-500 max-w-md">
                                            Enter at least 3 characters in the search box above to find guests.
                                            You can search by name, ID number, address, phone, email, or city.
                                        </p>
                                    </div>
                                </div>

                                <!-- Data Grid (hanya tampil setelah search) -->
                                <div v-else class="h-full">
                                    <DxDataGrid 
                                        :data-source="dataGridDataSource" 
                                        key-expr="clientID" 
                                        :show-borders="true"
                                        :allow-column-reordering="true" 
                                        :allow-column-resizing="true"
                                        :hover-state-enabled="true" 
                                        :show-row-lines="true" 
                                        :show-column-lines="true"
                                        :column-auto-width="false" 
                                        :word-wrap-enabled="true"
                                        @selection-changed="handleSelectGuest" 
                                        @row-dbl-click="onRowDblClick"
                                        @content-ready="onContentReady" 
                                        @option-changed="onOptionChanged" 
                                        height="100%">
                                        
                                        <DxSelection mode="single" />

                                        <DxPaging :enabled="true" :page-size="pageSize" :page-index="currentPage" />

                                        <DxPager 
                                            :show-page-size-selector="true" 
                                            :allowed-page-sizes="[10, 20, 50, 100]"
                                            :show-info="true" 
                                            :info-text="'Page {0} of {1} ({2} items)'"
                                            :visible="true" />

                                        <DxScrolling mode="virtual" />

                                        <DxFilterRow :visible="true" />

                                        <DxSearchPanel :visible="false" /> <!-- Hide default search panel -->

                                        <DxLoadPanel :enabled="true" />

                                        <!-- Columns -->
                                        <DxColumn data-field="clientID" caption="Guest ID" :width="100" />
                                        <DxColumn data-field="TypeIDNumber" caption="ID Number" :width="150" />
                                        <DxColumn data-field="GuestName" caption="Guest Name" :width="200"
                                            cell-template="guestNameTemplate" />
                                        <DxColumn data-field="Phone" caption="Phone" :width="150"
                                            cell-template="phoneTemplate" />
                                        <DxColumn data-field="Address" caption="Address" :width="200"
                                            cell-template="addressTemplate" />
                                        <DxColumn data-field="City" caption="City" :width="120" />
                                        <DxColumn data-field="CountryName" caption="Country" :width="130" />
                                        <DxColumn data-field="CompName" caption="Company" :width="150" />
                                        <DxColumn data-field="cBlackList" caption="Black List" :width="120"
                                            cell-template="blackListTemplate" />

                                        <!-- Custom Templates -->
                                        <template #blackListTemplate="{ data }">
                                            <div v-html="blackListCellTemplate(data)"></div>
                                        </template>

                                        <template #guestNameTemplate="{ data }">
                                            <div v-html="guestNameCellTemplate(data)"></div>
                                        </template>

                                        <template #phoneTemplate="{ data }">
                                            <div v-html="highlightSearchCellTemplate(data)"></div>
                                        </template>

                                        <template #addressTemplate="{ data }">
                                            <div v-html="highlightSearchCellTemplate(data)"></div>
                                        </template>
                                    </DxDataGrid>
                                </div>
                            </div>

                            <!-- Loading Indicator -->
                            <div v-if="isLoading || isLoadingInternal" class="mt-4 text-center">
                                <div class="inline-flex items-center gap-2 px-4 py-2 bg-blue-100 text-blue-700 rounded-lg">
                                    <svg class="animate-spin h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="text-sm font-medium">Loading guests...</span>
                                </div>
                            </div>

                            <!-- Search Info -->
                            <div v-if="hasSearched && searchValue && searchValue.length >= 3" class="mt-3 text-center">
                                <p class="text-sm text-gray-600">
                                    <span v-if="formattedGuests.length > 0">
                                        Total: {{ totalCount }} guests found | 
                                        Showing: {{ formattedGuests.length }} records | 
                                        Page {{ currentPage + 1 }} of {{ Math.ceil(totalCount / pageSize) || 1 }}
                                    </span>
                                    <span v-else class="text-amber-600">
                                        No guests found matching your search criteria.
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Modal Footer Tips -->
                        <div class="p-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                            <p class="text-sm text-gray-600 text-center">
                                <span v-if="!hasSearched">
                                    💡 <strong>Tip:</strong> Enter at least 3 characters in the search box to find guests
                                </span>
                                <span v-else>
                                    💡 <strong>Tip:</strong> Double-click a row to select guest | 
                                    🔍 Search highlights matching text in yellow |
                                    📄 Use pagination to navigate through results
                                </span>
                            </p>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-fullscreen-custom {
    min-height: 90vh;
}

/* Transition styles */
.transition-opacity {
    transition-property: opacity;
}

.transition-all {
    transition-property: all;
}

/* Animation styles */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Custom styles for form controls */
.form-control:focus {
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
    border-color: #6366f1;
}

/* Style untuk highlight mark */
mark {
    background-color: rgb(253 224 71);
    padding: 0 2px;
    border-radius: 2px;
}

/* Responsive design */
@media (max-width: 1024px) {
    .modal-fullscreen-custom {
        margin: 1rem;
        width: calc(100% - 2rem);
    }
}
</style>