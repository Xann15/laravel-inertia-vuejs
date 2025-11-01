<!-- Components/CompanyGridModal.vue -->
<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { DxDataGrid, DxColumn, DxPaging, DxFilterRow, DxSearchPanel, DxSelection, DxLoadPanel, DxScrolling, DxPager } from 'devextreme-vue/data-grid'

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    companies: {
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

const emit = defineEmits(['close', 'select-company', 'load-companies'])

// 🔹 State untuk search dan filter
const isLoadingInternal = ref(false)
const showSearchWarning = ref(false)
const searchWarningTimer = ref(null)
const dataGridInstance = ref(null)

// Search state
const searchValue = ref('')
const selectedFields = ref(['CompanyName']) // Default checked: CompanyName
const selectAllFields = ref(false)
const hasSearched = ref(false) // Flag untuk menandai apakah sudah melakukan search
const isAutoSearching = ref(false) // Flag untuk mencegah multiple calls

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
                key: 'CompID'
            },
            paginate: true,
            pageSize: pageSize.value
        }
    }

    return {
        store: {
            type: 'array',
            data: formattedCompanies.value,
            key: 'CompID'
        },
        paginate: true,
        pageSize: pageSize.value
    }
})

// 🔹 Watch untuk modal show/hide
watch(() => props.show, (newVal) => {
    if (newVal) {
        console.log('🔹 Company Modal opened with initialSearchValue:', props.initialSearchValue)

        // Reset state
        currentPage.value = 0
        searchValue.value = props.initialSearchValue || ''
        isAutoSearching.value = false

        // 🔹 Jika ada initialSearchValue yang valid, langsung search
        if (props.initialSearchValue && props.initialSearchValue.length >= 3) {
            hasSearched.value = true
            isAutoSearching.value = true
            console.log('🔹 Auto-searching company with:', props.initialSearchValue)
            loadCompanies()
        } else {
            hasSearched.value = false
        }

        // Auto focus search input
        nextTick(() => {
            setTimeout(() => {
                const searchInput = document.getElementById('searchInputCompany')
                if (searchInput) {
                    searchInput.focus()
                    searchInput.select()
                }
            }, 100)
        })
    } else {
        // Reset ketika modal ditutup
        searchValue.value = ''
        selectedFields.value = ['CompanyName']
        selectAllFields.value = false
        showSearchWarning.value = false
        hasSearched.value = false
        isAutoSearching.value = false

        if (searchWarningTimer.value) {
            clearTimeout(searchWarningTimer.value)
        }
    }
})

// 🔹 Watch untuk companies data changes
watch(() => props.companies, (newCompanies) => {
    console.log('📊 Companies data updated:', newCompanies.length, 'items')
    if (newCompanies && newCompanies.length > 0) {
        isLoadingInternal.value = false
        isAutoSearching.value = false
    }
})

// 🔹 Watch untuk isLoading changes
watch(() => props.isLoading, (newVal) => {
    console.log('🔄 isLoading changed:', newVal)
    isLoadingInternal.value = newVal
    if (!newVal) {
        isAutoSearching.value = false
    }
})

// 🔹 Watch untuk select all fields
watch(selectAllFields, (newVal) => {
    if (newVal) {
        selectedFields.value = ['CompanyName', 'CompID', 'Address', 'CompPhone']
    } else {
        selectedFields.value = ['CompanyName']
    }
})

// 🔹 Watch untuk individual field changes
watch(selectedFields, (newVal) => {
    if (newVal.length === 4) { // Total 4 fields available
        selectAllFields.value = true
    } else if (newVal.length < 4) {
        selectAllFields.value = false
    }
})

// 🔹 Load companies function
const loadCompanies = () => {
    console.log('🔄 loadCompanies called with search:', searchValue.value)

    // Validasi minimal 3 karakter
    if (searchValue.value && searchValue.value.length < 3) {
        console.log('⚠️ Search too short, showing warning')
        showWarning()
        return
    }

    // Cegah multiple calls
    if (isLoadingInternal.value) {
        console.log('⏸️ Skip load - already loading')
        return
    }

    isLoadingInternal.value = true
    hasSearched.value = true

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
        params.searchFields  = selectedFields.value.join(',')
    }

    console.log('📤 Emitting load-companies with params:', params)
    emit('load-companies', params)
}

// 🔹 Search handler
const handleSearch = () => {
    currentPage.value = 0
    isAutoSearching.value = false
    loadCompanies()
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

// 🔹 Format company data
const formattedCompanies = computed(() => {
    return props.companies.map(company => ({
        CompID: company.CompID,
        CompName: company.CompName || '',
        CompPhone: company.CompPhone || '',
        CompEmail: company.CompEmail || '',
        creditFacility: company.creditFacility || 0,
        busName: company.busName || '',
        strCreditFacility: company.strCreditFacility || 'Not Approve',
        typeName: company.typeName || '',
        isCActive: company.isCActive ? 'Active' : 'Inactive',
        isBlackList: company.isBlackList ? 'YES' : 'NO',
        BlackListSTR: company.BlackListSTR || 'NO',
        cGroupName: company.cGroupName || '',
        segmentName: company.segmentName || '',
        Address: company.Address || ''
    }))
})

// 🔹 Close modal
function closeModal() {
    emit('close')
}

// 🔹 Handle company selection
function handleSelectCompany(e) {
    if (e.selectedRowsData && e.selectedRowsData.length > 0) {
        const selectedCompany = e.selectedRowsData[0]
        console.log('✅ Company selected from grid:', selectedCompany)
        emit('select-company', selectedCompany)
        closeModal()
    }
}

function onRowDblClick(e) {
    console.log('👆 Company double-clicked:', e.data)
    emit('select-company', e.data)
    closeModal()
}

// 🔹 DataGrid events
function onContentReady(e) {
    console.log('✅ DataGrid content ready')
}

function onOptionChanged(e) {
    if (e.fullName === 'paging.pageIndex') {
        console.log('📄 Page changed to:', e.value)
        currentPage.value = e.value
        isAutoSearching.value = false
        loadCompanies()
    } else if (e.fullName === 'paging.pageSize') {
        console.log('📏 Page size changed to:', e.value)
        pageSize.value = e.value
        currentPage.value = 0
        isAutoSearching.value = false
        loadCompanies()
    }
}

// 🔹 Custom cell templates
const activeCellTemplate = (cellData) => {
    const isActive = cellData.value === 'Active'
    return `
    <div class="flex items-center justify-center">
        <input type="checkbox" ${isActive ? 'checked' : ''} disabled 
            class="w-4 h-4 text-green-600 rounded cursor-not-allowed" />
    </div>
    `
}

const blackListCellTemplate = (cellData) => {
    const isBlacklisted = cellData.value === 'YES'
    return `
    <div class="flex items-center justify-center">
        <input type="checkbox" ${isBlacklisted ? 'checked' : ''} disabled 
            class="w-4 h-4 text-red-600 rounded cursor-not-allowed" />
    </div>
    `
}

const creditFacilityCellTemplate = (cellData) => {
    const hasCredit = cellData.data.creditFacility > 0
    return `
    <span class="px-3 py-1 rounded-full text-xs font-semibold ${
        hasCredit
            ? 'bg-green-100 text-green-800 border border-green-200'
            : 'bg-red-100 text-red-800 border border-red-200'
    }">
        ${cellData.value || 'Not Approve'}
    </span>
    `
}

const companyNameCellTemplate = (cellData) => {
    return `
    <div class="flex flex-col py-1">
        <span class="font-semibold text-gray-800">${cellData.value || ''}</span>
        <span class="text-xs text-gray-500">${cellData.data.cGroupName || ''}</span>
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
                            <h4 class="fw-bold my-auto text-2xl font-bold text-gray-800 flex items-center gap-2">
                                <span class="text-3xl">🏢</span>
                                Select Company
                            </h4>
                            <div class="flex gap-2">
                                <button type="button" @click="handleSelectCompany"
                                    :disabled="!hasSearched || formattedCompanies.length === 0" :class="[
                                        'px-4 py-2 text-white font-bold rounded-lg transition-colors',
                                        hasSearched && formattedCompanies.length > 0
                                            ? 'bg-green-500 hover:bg-green-600'
                                            : 'bg-gray-400 cursor-not-allowed'
                                    ]">
                                    Select
                                </button>
                                <button type="button" @click="closeModal"
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
                                    <h5 class="my-auto text-lg font-semibold text-gray-700">Search Company</h5>
                                </div>

                                <div class="w-2/3 flex gap-2">
                                    <input type="text" id="searchInputCompany" v-model="searchValue"
                                        @keypress="handleKeypress"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="Enter at least 3 characters to search..."
                                        :title="searchValue && searchValue.length < 3 ? 'Minimal 3 karakter diperlukan' : ''" />
                                    <button @click="handleSearch" :disabled="isLoading || isLoadingInternal" :class="[
                                        'px-4 py-2 text-white rounded-lg transition-colors flex items-center gap-2',
                                        isLoading || isLoadingInternal
                                            ? 'bg-gray-400 cursor-not-allowed'
                                            : 'bg-indigo-500 hover:bg-indigo-600'
                                    ]">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        <span v-if="isLoading || isLoadingInternal">Searching...</span>
                                        <span v-else>Search</span>
                                    </button>
                                </div>
                                <div class="w-1/6"></div>
                            </div>

                            <!-- Search By -->
                            <div
                                class="w-3/4 mx-auto flex justify-between items-center p-4 mb-6 bg-gradient-to-r from-gray-50 to-indigo-50 rounded-lg border border-indigo-200">
                                <div class="w-1/6 flex">
                                    <p class="my-auto mb-0 font-semibold text-gray-700">Search By:</p>
                                </div>
                                <div class="w-1/5 flex">
                                    <div class="flex items-center">
                                        <input class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500"
                                            type="checkbox" value="CompanyName" v-model="selectedFields"
                                            id="searchCompanyName" />
                                        <label class="ml-2 text-sm text-gray-700 font-medium" for="searchCompanyName">
                                            Company Name
                                        </label>
                                    </div>
                                </div>
                                <div class="w-1/6 flex">
                                    <div class="flex items-center">
                                        <input class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500"
                                            type="checkbox" value="CompID" v-model="selectedFields" id="searchCompID" />
                                        <label class="ml-2 text-sm text-gray-700 font-medium" for="searchCompID">
                                            Company ID
                                        </label>
                                    </div>
                                </div>
                                <div class="w-1/6 flex">
                                    <div class="flex items-center">
                                        <input class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500"
                                            type="checkbox" value="Address" v-model="selectedFields"
                                            id="searchAddressCompany" />
                                        <label class="ml-2 text-sm text-gray-700 font-medium" for="searchAddressCompany">
                                            Address
                                        </label>
                                    </div>
                                </div>
                                <div class="w-1/4 flex">
                                    <div class="flex items-center">
                                        <input class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500"
                                            type="checkbox" v-model="selectAllFields" id="selectAllFieldsCompany" />
                                        <label class="ml-2 text-sm text-gray-700 font-medium"
                                            for="selectAllFieldsCompany">
                                            Select All
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty State / Data Grid -->
                            <div class="border border-gray-200 rounded-lg" style="height: 60vh;">
                                <!-- Empty State -->
                                <div v-if="!hasSearched"
                                    class="h-full flex flex-col items-center justify-center bg-gradient-to-br from-gray-50 to-indigo-50 rounded-lg">
                                    <div class="text-center">
                                        <svg class="w-20 h-20 text-indigo-400 mx-auto mb-4" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                            </path>
                                        </svg>
                                        <h3 class="text-xl font-bold text-gray-700 mb-2">🔍 Search for Companies</h3>
                                        <p class="text-gray-500 max-w-md">
                                            Enter at least 3 characters in the search box above to find companies.
                                            You can search by company name, ID, or address.
                                        </p>
                                    </div>
                                </div>

                                <!-- Loading State -->
                                <div v-else-if="isLoading || isLoadingInternal"
                                    class="h-full flex flex-col items-center justify-center bg-gradient-to-br from-gray-50 to-indigo-50 rounded-lg">
                                    <div class="text-center">
                                        <svg class="animate-spin w-16 h-16 text-indigo-600 mx-auto mb-4"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                        <h3 class="text-lg font-semibold text-gray-700 mb-2">
                                            {{ isAutoSearching ? '🔄 Auto-searching...' : '🔍 Searching Companies...' }}
                                        </h3>
                                        <p class="text-gray-500">
                                            Searching for "{{ searchValue }}"...
                                        </p>
                                    </div>
                                </div>

                                <!-- No Results State -->
                                <div v-else-if="hasSearched && formattedCompanies.length === 0"
                                    class="h-full flex flex-col items-center justify-center bg-gradient-to-br from-gray-50 to-amber-50 rounded-lg">
                                    <div class="text-center">
                                        <svg class="w-16 h-16 text-amber-400 mx-auto mb-4" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                            </path>
                                        </svg>
                                        <h3 class="text-lg font-semibold text-gray-700 mb-2">📭 No Companies Found</h3>
                                        <p class="text-gray-500 max-w-md">
                                            No companies found matching "{{ searchValue }}".
                                            Try adjusting your search criteria or search by different fields.
                                        </p>
                                    </div>
                                </div>

                                <!-- Data Grid -->
                                <div v-else class="h-full">
                                    <DxDataGrid :data-source="dataGridDataSource" key-expr="CompID" :show-borders="true"
                                        :allow-column-reordering="true" :allow-column-resizing="true"
                                        :hover-state-enabled="true" :show-row-lines="true" :show-column-lines="true"
                                        :column-auto-width="false" :word-wrap-enabled="true"
                                        @selection-changed="handleSelectCompany" @row-dbl-click="onRowDblClick"
                                        @content-ready="onContentReady" @option-changed="onOptionChanged" height="100%">

                                        <DxSelection mode="single" />

                                        <DxPaging :enabled="true" :page-size="pageSize" :page-index="currentPage" />

                                        <DxPager :show-page-size-selector="true" :allowed-page-sizes="[10, 20, 50, 100]"
                                            :show-info="true" :info-text="'Page {0} of {1} ({2} items)'"
                                            :visible="true" />

                                        <DxScrolling mode="virtual" />

                                        <DxFilterRow :visible="true" />

                                        <DxSearchPanel :visible="false" />

                                        <DxLoadPanel :enabled="true" />

                                        <!-- Columns -->
                                        <DxColumn data-field="CompID" caption="Company ID" :width="120" />
                                        <DxColumn data-field="CompName" caption="Company Name" :width="220"
                                            cell-template="companyNameTemplate" />
                                        <DxColumn data-field="typeName" caption="Type" :width="120" />
                                        <DxColumn data-field="CompPhone" caption="Phone" :width="130"
                                            cell-template="phoneTemplate" />
                                        <DxColumn data-field="CompEmail" caption="E-Mail" :width="200"
                                            cell-template="emailTemplate" />
                                        <DxColumn data-field="busName" caption="Business Source" :width="150" />
                                        <DxColumn data-field="strCreditFacility" caption="Credit Facility" :width="150"
                                            cell-template="creditFacilityTemplate" />
                                        <DxColumn data-field="isCActive" caption="Active" :width="100"
                                            cell-template="activeTemplate" alignment="center" />
                                        <DxColumn data-field="isBlackList" caption="Blacklist" :width="100"
                                            cell-template="blackListTemplate" alignment="center" />

                                        <!-- Custom Templates -->
                                        <template #companyNameTemplate="{ data }">
                                            <div v-html="companyNameCellTemplate(data)"></div>
                                        </template>

                                        <template #phoneTemplate="{ data }">
                                            <div v-html="highlightSearchCellTemplate(data)"></div>
                                        </template>

                                        <template #emailTemplate="{ data }">
                                            <div v-html="highlightSearchCellTemplate(data)"></div>
                                        </template>

                                        <template #creditFacilityTemplate="{ data }">
                                            <div v-html="creditFacilityCellTemplate(data)"></div>
                                        </template>

                                        <template #activeTemplate="{ data }">
                                            <div v-html="activeCellTemplate(data)"></div>
                                        </template>

                                        <template #blackListTemplate="{ data }">
                                            <div v-html="blackListCellTemplate(data)"></div>
                                        </template>
                                    </DxDataGrid>
                                </div>
                            </div>

                            <!-- Search Info -->
                            <div v-if="hasSearched && searchValue && searchValue.length >= 3 && !isLoading && !isLoadingInternal && formattedCompanies.length > 0"
                                class="mt-3 text-center">
                                <p class="text-sm text-gray-600">
                                    <span class="font-semibold">Total: {{ totalCount }} companies found</span> |
                                    Showing: {{ formattedCompanies.length }} records |
                                    Page {{ currentPage + 1 }} of {{ Math.ceil(totalCount / pageSize) || 1 }}
                                </p>
                            </div>
                        </div>

                        <!-- Modal Footer Tips -->
                        <div
                            class="p-4 border-t border-gray-200 bg-gradient-to-r from-gray-50 to-indigo-50 rounded-b-2xl">
                            <p class="text-sm text-gray-600 text-center">
                                <span v-if="!hasSearched">
                                    💡 <strong>Tip:</strong> Enter at least 3 characters in the search box to find
                                    companies
                                </span>
                                <span v-else-if="isLoading || isLoadingInternal">
                                    🔍 <strong>Searching:</strong> Please wait while we search for companies...
                                </span>
                                <span v-else-if="formattedCompanies.length === 0">
                                    💡 <strong>No Results:</strong> Try different search terms or check your search
                                    fields
                                </span>
                                <span v-else>
                                    💡 <strong>Tip:</strong> Double-click a row to select company |
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
    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.5;
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
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