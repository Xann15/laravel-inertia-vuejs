<!-- Components/ReservationGrid.vue -->
<script setup>
import { ref, onMounted, inject } from 'vue'
import { DxDataGrid, DxColumn, DxEditing, DxPaging, DxLookup, DxFilterRow, DxSearchPanel } from 'devextreme-vue/data-grid'

const tabSystem = inject('tabSystem')

const reservations = ref([])

// Load data (contoh)
onMounted(async () => {
  // Ganti dengan API call ke backend Laravel
  reservations.value = [
    { id: 1, guestName: 'John Doe', room: '101', arrival: '2025-10-30', departure: '2025-10-31', status: 'Confirmed', adults: 2, children: 0 },
    { id: 2, guestName: 'Jane Smith', room: '102', arrival: '2025-10-31', departure: '2025-11-02', status: 'Pending', adults: 1, children: 1 },
    { id: 3, guestName: 'Bob Johnson', room: '201', arrival: '2025-11-01', departure: '2025-11-03', status: 'Confirmed', adults: 2, children: 0 },
    { id: 4, guestName: 'Alice Brown', room: '103', arrival: '2025-11-02', departure: '2025-11-04', status: 'Checked-in', adults: 2, children: 2 },
    { id: 5, guestName: 'Charlie Wilson', room: '202', arrival: '2025-11-03', departure: '2025-11-05', status: 'Cancelled', adults: 1, children: 0 }
  ]
})

const statuses = [
  { id: 'Pending', name: 'Pending' },
  { id: 'Confirmed', name: 'Confirmed' },
  { id: 'Checked-in', name: 'Checked-in' },
  { id: 'Checked-out', name: 'Checked-out' },
  { id: 'Cancelled', name: 'Cancelled' }
]

function onSaving(e) {
  e.cancel = true
  console.log('Data would be saved:', e.changes)
  
  // Di sini Anda bisa implementasi save ke backend
  // e.promise = yourSaveFunction(e.changes)
}

function onInitNewRow(e) {
  e.data.status = 'Pending'
  e.data.adults = 1
  e.data.children = 0
  e.data.arrival = new Date().toISOString().split('T')[0]
  e.data.departure = new Date(Date.now() + 86400000).toISOString().split('T')[0]
}

function closeGrid() {
  if (tabSystem && tabSystem.closeCurrentTab) {
    tabSystem.closeCurrentTab()
  }
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 p-4">
    <div class="max-w-7xl mx-auto space-y-4">
      
      <!-- Header Compact -->
      <div class="flex items-center justify-between bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex items-center gap-3">
          <span class="text-3xl">📊</span>
          <div>
            <h1 class="text-xl font-bold text-gray-800">Reservation Browser</h1>
            <p class="text-sm text-gray-600">Manage and view all reservations</p>
          </div>
        </div>
        <div class="flex gap-2">
          <button class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-bold rounded-lg shadow text-sm transition">
            🔄 Refresh
          </button>
          <button @click="closeGrid" class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-bold rounded-lg shadow text-sm transition">
            ❌ Close
          </button>
        </div>
      </div>

      <!-- DataGrid Section -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4">
          <DxDataGrid
            :data-source="reservations"
            key-expr="id"
            :show-borders="true"
            :allow-column-reordering="true"
            :allow-column-resizing="true"
            @saving="onSaving"
            @init-new-row="onInitNewRow"
          >
            <DxEditing
              mode="row"
              :allow-updating="true"
              :allow-adding="true"
              :allow-deleting="true"
              :confirm-delete="false"
            />
            
            <DxPaging :page-size="10" />
            <DxFilterRow :visible="true" />
            <DxSearchPanel :visible="true" placeholder="Search reservations..." />
            
            <DxColumn 
              data-field="id" 
              caption="ID"
              :width="60"
              :allow-editing="false"
            />
            <DxColumn 
              data-field="guestName" 
              caption="Guest Name"
              :width="180"
            />
            <DxColumn 
              data-field="room" 
              caption="Room"
              :width="80"
            />
            <DxColumn 
              data-field="arrival" 
              caption="Arrival"
              data-type="date"
              :width="120"
              format="dd/MM/yyyy"
            />
            <DxColumn 
              data-field="departure" 
              caption="Departure"
              data-type="date"
              :width="120"
              format="dd/MM/yyyy"
            />
            <DxColumn 
              data-field="adults" 
              caption="Adults"
              :width="80"
              data-type="number"
            />
            <DxColumn 
              data-field="children" 
              caption="Children"
              :width="80"
              data-type="number"
            />
            <DxColumn 
              data-field="status" 
              caption="Status"
              :width="120"
            >
              <DxLookup
                :data-source="statuses"
                value-expr="id"
                display-expr="name"
              />
            </DxColumn>
            <DxColumn 
              :width="100"
              type="buttons"
              caption="Actions"
            />
          </DxDataGrid>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
          <div class="text-2xl font-bold text-blue-600">{{ reservations.length }}</div>
          <div class="text-sm text-gray-600">Total Reservations</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
          <div class="text-2xl font-bold text-green-600">{{ reservations.filter(r => r.status === 'Confirmed').length }}</div>
          <div class="text-sm text-gray-600">Confirmed</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
          <div class="text-2xl font-bold text-yellow-600">{{ reservations.filter(r => r.status === 'Pending').length }}</div>
          <div class="text-sm text-gray-600">Pending</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
          <div class="text-2xl font-bold text-red-600">{{ reservations.filter(r => r.status === 'Cancelled').length }}</div>
          <div class="text-sm text-gray-600">Cancelled</div>
        </div>
      </div>

    </div>
  </div>
</template>