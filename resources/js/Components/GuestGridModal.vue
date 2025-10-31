<!-- Components/GuestGridModal.vue -->
<script setup>
import { ref, onMounted, computed } from 'vue'
import { DxDataGrid, DxColumn, DxEditing, DxPaging, DxFilterRow, DxSearchPanel, DxSelection } from 'devextreme-vue/data-grid'
import AddGuestModal from './AddGuestModal.vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'select-guest'])

const guests = ref([])
const showAddGuestModal = ref(false)

// Load guest data
onMounted(async () => {
  guests.value = [
    { id: 1, title: 'Mr', firstName: 'John', lastName: 'Doe', phone: '+62812345678', email: 'john@email.com', city: 'Jakarta', nationality: 'Indonesia', idType: 'KTP', idNumber: '3201234567890123' },
    { id: 2, title: 'Mrs', firstName: 'Jane', lastName: 'Smith', phone: '+62823456789', email: 'jane@email.com', city: 'Surabaya', nationality: 'Indonesia', idType: 'KTP', idNumber: '3301234567890124' },
    { id: 3, title: 'Mr', firstName: 'Bob', lastName: 'Johnson', phone: '+62834567890', email: 'bob@email.com', city: 'Bandung', nationality: 'USA', idType: 'Passport', idNumber: 'US1234567' },
    { id: 4, title: 'Miss', firstName: 'Alice', lastName: 'Brown', phone: '+62845678901', email: 'alice@email.com', city: 'Medan', nationality: 'Indonesia', idType: 'KTP', idNumber: '1201234567890125' },
    { id: 5, title: 'Mr', firstName: 'Charlie', lastName: 'Wilson', phone: '+62856789012', email: 'charlie@email.com', city: 'Bali', nationality: 'Australia', idType: 'Passport', idNumber: 'AU9876543' }
  ]
})

// Z-index management untuk modal berlapis
const zIndex = computed(() => {
  return showAddGuestModal.value ? 'z-40' : 'z-50'
})

function closeModal() {
  emit('close')
}

function handleSelectGuest(e) {
  if (e.selectedRowsData && e.selectedRowsData.length > 0) {
    const selectedGuest = e.selectedRowsData[0]
    emit('select-guest', selectedGuest)
    closeModal()
  }
}

function openAddGuestModal() {
  showAddGuestModal.value = true
}

function closeAddGuestModal() {
  showAddGuestModal.value = false
}

function handleGuestAdded(newGuest) {
  // Generate ID baru
  const maxId = Math.max(...guests.value.map(g => g.id), 0)
  newGuest.id = maxId + 1
  
  // Tambahkan ke list
  guests.value.push(newGuest)
  
  // Close modal
  closeAddGuestModal()
  
  console.log('New guest added:', newGuest)
}

function onRowDblClick(e) {
  emit('select-guest', e.data)
  closeModal()
}
</script>

<template>
  <!-- Guest Grid Modal -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4"
        :class="zIndex"
        @click.self="closeModal"
      >
        <Transition
          enter-active-class="transition-all duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition-all duration-200"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="show"
            class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
              <div class="flex items-center gap-3">
                <span class="text-3xl">👥</span>
                <div>
                  <h2 class="text-2xl font-bold text-gray-800">Guest Browser</h2>
                  <p class="text-sm text-gray-600">Select or add a guest</p>
                </div>
              </div>
              <div class="flex gap-2">
                <button
                  @click="openAddGuestModal"
                  class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-bold rounded-lg shadow text-sm transition"
                >
                  ➕ Add Guest
                </button>
                <button
                  @click="closeModal"
                  class="px-4 py-2 bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-bold rounded-lg shadow text-sm transition"
                >
                  ❌ Close
                </button>
              </div>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-hidden p-6">
              <div class="h-full">
                <DxDataGrid
                  :data-source="guests"
                  key-expr="id"
                  :show-borders="true"
                  :allow-column-reordering="true"
                  :allow-column-resizing="true"
                  :hover-state-enabled="true"
                  @selection-changed="handleSelectGuest"
                  @row-dbl-click="onRowDblClick"
                  height="100%"
                >
                  <DxSelection mode="single" />
                  <DxPaging :page-size="10" />
                  <DxFilterRow :visible="true" />
                  <DxSearchPanel :visible="true" placeholder="Search guests..." />
                  
                  <DxColumn 
                    data-field="id" 
                    caption="ID"
                    :width="60"
                  />
                  <DxColumn 
                    data-field="title" 
                    caption="Title"
                    :width="80"
                  />
                  <DxColumn 
                    data-field="firstName" 
                    caption="First Name"
                    :width="150"
                  />
                  <DxColumn 
                    data-field="lastName" 
                    caption="Last Name"
                    :width="150"
                  />
                  <DxColumn 
                    data-field="phone" 
                    caption="Phone"
                    :width="140"
                  />
                  <DxColumn 
                    data-field="email" 
                    caption="Email"
                    :width="200"
                  />
                  <DxColumn 
                    data-field="city" 
                    caption="City"
                    :width="120"
                  />
                  <DxColumn 
                    data-field="nationality" 
                    caption="Nationality"
                    :width="120"
                  />
                  <DxColumn 
                    data-field="idType" 
                    caption="ID Type"
                    :width="100"
                  />
                  <DxColumn 
                    data-field="idNumber" 
                    caption="ID Number"
                    :width="150"
                  />
                </DxDataGrid>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-4 border-t border-gray-200 bg-gray-50">
              <p class="text-sm text-gray-600 text-center">
                💡 <strong>Tip:</strong> Double-click a row to select the guest
              </p>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>

    <!-- Add Guest Modal (Nested) -->
    <AddGuestModal 
      :show="showAddGuestModal" 
      @close="closeAddGuestModal"
      @save="handleGuestAdded"
    />
  </Teleport>
</template>

<style scoped>
/* Ensure smooth transitions */
.transition-opacity {
  transition-property: opacity;
}

.transition-all {
  transition-property: all;
}
</style>