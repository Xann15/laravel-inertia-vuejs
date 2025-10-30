<script setup>
import { ref, computed } from 'vue'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import NavLink from '@/Components/NavLink.vue'
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'
import { Link } from '@inertiajs/vue3'

// Import komponen ReservationForm
import ReservationForm from '@/Components/ReservationForm.vue'

const showingNavigationDropdown = ref(false)
const showSidebar = ref(false)

// Tab System
const tabs = ref([])
const activeTabId = ref(null)
const nextTabId = ref(1)

// Navigation Menu Structure
const navigationMenus = [
  {
    title: 'Front Office',
    icon: '🏨',
    items: [
      { title: 'Walk In', icon: '🚶', component: 'WalkIn', url: '/walkin' },
      { title: 'Registration Browser', icon: '📋', component: 'RegistrationBrowser', url: '/registration-browser' },
      { 
        title: 'Reservation', 
        icon: '📅', 
        submenu: [
          { title: 'New Reservation', icon: '➕', component: 'ReservationForm', url: '/reservation' },
          { title: 'Reservation Browser', icon: '🔍', component: 'ReservationBrowser', url: '/reservation-browser' },
        ]
      },
      { title: 'Group Reservation', icon: '👥', component: 'GroupReservation', url: '/group-reservation' },
      { title: 'House Folio', icon: '🏠', component: 'HouseFolio', url: '/house-folio' },
      { title: 'Cashier', icon: '💰', component: 'Cashier', url: '/cashier' },
      { title: 'Expected Arrival', icon: '✈️', component: 'ExpectedArrival', url: '/expected-arrival' },
      { title: 'Room Browser', icon: '🚪', component: 'RoomBrowser', url: '/room-browser' },
      { title: 'Room Chart', icon: '📊', component: 'RoomChart', url: '/room-chart' },
      { title: 'Room Inventory', icon: '📦', component: 'RoomInventory', url: '/room-inventory' },
    ]
  },
  {
    title: 'Housekeeping',
    icon: '🧹',
    items: [
      { title: 'Room Status', icon: '🔑', component: 'RoomStatus', url: '/room-status' },
      { title: 'Cleaning Schedule', icon: '📅', component: 'CleaningSchedule', url: '/cleaning-schedule' },
      { title: 'Maintenance', icon: '🔧', component: 'Maintenance', url: '/maintenance' },
    ]
  },
  {
    title: 'Sales & Marketing',
    icon: '📈',
    items: [
      { title: 'Leads', icon: '🎯', component: 'Leads', url: '/leads' },
      { title: 'Campaigns', icon: '📢', component: 'Campaigns', url: '/campaigns' },
      { title: 'Corporate Clients', icon: '🏢', component: 'CorporateClients', url: '/corporate-clients' },
    ]
  },
  {
    title: 'Venue',
    icon: '🎪',
    items: [
      { title: 'Venue Management', icon: '🏛️', component: 'VenueManagement', url: '/venue-management' },
      { title: 'Events', icon: '🎉', component: 'Events', url: '/events' },
      { title: 'Pricing', icon: '💵', component: 'Pricing', url: '/pricing' },
    ]
  }
]

// Komponen mapping untuk render dinamis
const componentMap = {
  ReservationForm: ReservationForm,
  // Komponen lain bisa ditambahkan di sini nanti
}

// Add new tab
function addTab(title, component, icon = '📄') {
  const existing = tabs.value.find(t => t.component === component)
  if (existing) {
    activeTabId.value = existing.id
    return
  }

  const newTab = {
    id: nextTabId.value++,
    title,
    component,
    icon,
    closeable: true
  }
  tabs.value.push(newTab)
  activeTabId.value = newTab.id
}

// Close tab
function closeTab(tabId) {
  const index = tabs.value.findIndex(t => t.id === tabId)
  if (index === -1) return
  
  tabs.value.splice(index, 1)
  
  if (activeTabId.value === tabId) {
    if (tabs.value.length > 0) {
      const newIndex = Math.max(0, index - 1)
      activeTabId.value = tabs.value[newIndex]?.id || null
    } else {
      activeTabId.value = null
    }
  }
}

// Switch active tab
function switchTab(tabId) {
  activeTabId.value = tabId
}

// Open menu item
function openMenuItem(item) {
  addTab(item.title, item.component, item.icon)
  showSidebar.value = false
}

// Check if there are any tabs
const hasTabs = computed(() => tabs.value.length > 0)

// Get active tab component untuk render dinamis
const activeTabComponent = computed(() => {
  const activeTab = tabs.value.find(tab => tab.id === activeTabId.value)
  return activeTab ? componentMap[activeTab.component] : null
})

defineExpose({ addTab })
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Top Navigation Bar -->
    <nav class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-40">
      <div class="mx-auto max-w-full px-4">
        <div class="flex h-16 items-center justify-between">
          <!-- Left: Logo & Menu Button -->
          <div class="flex items-center gap-4">
            <button
              @click="showSidebar = !showSidebar"
              class="p-2 rounded-lg hover:bg-gray-100 transition"
            >
              <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
            
            <Link :href="route('dashboard')" class="flex items-center gap-2">
              <ApplicationLogo class="h-8 w-auto" />
              <span class="text-xl font-bold text-gray-800">FO Cloud</span>
            </Link>
          </div>

          <!-- Center: Tab Bar (replaces "Welcome back") -->
          <div v-if="hasTabs" class="flex-1 mx-6 overflow-x-auto">
            <div class="flex items-center gap-1">
              <div
                v-for="tab in tabs"
                :key="tab.id"
                @click="switchTab(tab.id)"
                class="group flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all cursor-pointer whitespace-nowrap"
                :class="activeTabId === tab.id 
                  ? 'bg-indigo-600 text-white shadow-md' 
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
              >
                <span class="text-base">{{ tab.icon }}</span>
                <span>{{ tab.title }}</span>
                <button
                  @click.stop="closeTab(tab.id)"
                  class="p-0.5 rounded transition-colors"
                  :class="activeTabId === tab.id ? 'hover:bg-white/20 text-white' : 'hover:bg-gray-300 text-gray-500'"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Right: User Info & Hotel Details -->
          <div class="flex items-center gap-4">
            <!-- Hotel Info -->
            <div class="hidden lg:block text-right">
              <p class="text-sm font-bold text-red-600">{{ $page.props.hotelName || 'Hotel Name' }}</p>
              <p class="text-xs text-gray-500">{{ new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) }}</p>
            </div>

            <!-- User Dropdown -->
            <Dropdown align="right" width="48">
              <template #trigger>
                <button class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-100 transition">
                  <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-white font-semibold">
                    {{ $page.props.auth?.user?.name?.charAt(0) || 'U' }}
                  </div>
                  <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
              </template>
              <template #content>
                <div class="px-4 py-3 border-b">
                  <p class="text-sm font-medium text-gray-900">{{ $page.props.auth?.user?.name || 'Guest' }}</p>
                  <p class="text-xs text-gray-500">{{ $page.props.auth?.user?.email || '' }}</p>
                </div>
                <DropdownLink :href="route('profile.edit')">
                  <i class="bi bi-person mr-2"></i>Profile
                </DropdownLink>
                <DropdownLink :href="route('logout')" method="post" as="button">
                  <i class="bi bi-box-arrow-right mr-2"></i>Log Out
                </DropdownLink>
              </template>
            </Dropdown>
          </div>
        </div>
      </div>
    </nav>

    <!-- Sidebar Overlay -->
    <transition name="fade">
      <div
        v-if="showSidebar"
        @click="showSidebar = false"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm z-30"
      ></div>
    </transition>

    <!-- Sidebar Menu -->
    <transition name="slide">
      <div
        v-if="showSidebar"
        class="fixed left-0 top-0 bottom-0 w-80 bg-white shadow-2xl z-40 overflow-y-auto"
      >
        <!-- Sidebar Header -->
        <div class="p-4 border-b bg-gradient-to-r from-indigo-600 to-purple-600">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-white">Navigation Menu</h2>
            <button
              @click="showSidebar = false"
              class="p-2 rounded-lg hover:bg-white/20 text-white transition"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Search Box -->
        <div class="p-4 border-b bg-gray-50">
          <div class="relative">
            <input
              type="text"
              placeholder="Search menu..."
              class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <!-- Menu Categories -->
        <div class="p-4">
          <div
            v-for="category in navigationMenus"
            :key="category.title"
            class="mb-6"
          >
            <!-- Category Header -->
            <div class="flex items-center gap-2 mb-3 px-2">
              <span class="text-2xl">{{ category.icon }}</span>
              <h3 class="font-bold text-gray-800 text-lg">{{ category.title }}</h3>
            </div>

            <!-- Menu Items -->
            <div class="space-y-1">
              <template v-for="item in category.items" :key="item.title">
                <!-- Has Submenu -->
                <details v-if="item.submenu" class="group">
                  <summary class="flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-indigo-50 cursor-pointer transition">
                    <span class="text-xl">{{ item.icon }}</span>
                    <span class="flex-1 font-medium text-gray-700 group-hover:text-indigo-600">{{ item.title }}</span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </summary>
                  <div class="ml-8 mt-1 space-y-1">
                    <button
                      v-for="subitem in item.submenu"
                      :key="subitem.title"
                      @click="openMenuItem(subitem)"
                      class="w-full flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-gray-100 text-left transition"
                    >
                      <span class="text-base">{{ subitem.icon }}</span>
                      <span class="text-sm font-medium text-gray-600">{{ subitem.title }}</span>
                    </button>
                  </div>
                </details>

                <!-- No Submenu -->
                <button
                  v-else
                  @click="openMenuItem(item)"
                  class="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg hover:bg-indigo-50 transition group"
                >
                  <span class="text-xl">{{ item.icon }}</span>
                  <span class="font-medium text-gray-700 group-hover:text-indigo-600">{{ item.title }}</span>
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Main Content Area -->
    <main class="min-h-[calc(100vh-4rem)]">
      <!-- Show Tabs Content if tabs exist -->
      <div v-if="hasTabs">
        <div
          v-for="tab in tabs"
          :key="tab.id"
          v-show="activeTabId === tab.id"
          class="animate-fadeIn"
        >
          <!-- Render komponen dinamis untuk ReservationForm -->
          <component 
            :is="componentMap[tab.component]"
            v-if="componentMap[tab.component]"
          />
          
          <!-- Fallback untuk komponen yang belum dibuat -->
          <div v-else class="p-6">
            <div class="mx-auto max-w-7xl">
              <!-- Tab Content Card -->
              <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <!-- Tab Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <span class="text-3xl">{{ tab.icon }}</span>
                    <div>
                      <h1 class="text-2xl font-bold text-gray-800">{{ tab.title }}</h1>
                      <p class="text-sm text-gray-600">{{ tab.component }} Module</p>
                    </div>
                  </div>
                  <button
                    @click="closeTab(tab.id)"
                    class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                  >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>

                <!-- Tab Content Body -->
                <div class="p-8">
                  <!-- Sample Content Based on Component -->
                  <div v-if="tab.component === 'WalkIn'">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div class="space-y-4">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Guest Information</h3>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-2">Guest Name</label>
                          <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Enter guest name" />
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                          <input type="tel" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Enter phone" />
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                          <input type="email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" placeholder="Enter email" />
                        </div>
                      </div>
                      <div class="space-y-4">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Reservation Details</h3>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-2">Check-in Date</label>
                          <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-2">Check-out Date</label>
                          <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-2">Room Type</label>
                          <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                            <option>Deluxe Room</option>
                            <option>Suite</option>
                            <option>Standard</option>
                          </select>
                        </div>
                      </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                      <button class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-semibold">
                        💾 Save Reservation
                      </button>
                      <button class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                        🔄 Reset
                      </button>
                    </div>
                  </div>

                  <!-- Default Content for other components -->
                  <div v-else class="text-center py-16">
                    <div class="inline-flex items-center justify-center w-24 h-24 bg-indigo-100 rounded-full mb-6">
                      <span class="text-5xl">{{ tab.icon }}</span>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">{{ tab.title }}</h2>
                    <p class="text-gray-600 mb-8">Module: {{ tab.component }}</p>
                    <div class="max-w-2xl mx-auto bg-gradient-to-br from-gray-50 to-gray-100 p-12 rounded-xl border-2 border-gray-200">
                      <p class="text-gray-600 text-lg">Content for this module will be displayed here.</p>
                      <p class="text-gray-500 text-sm mt-2">Start building your {{ tab.component }} interface!</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Show Default Dashboard Content if no tabs -->
      <div v-else>
        <slot />
      </div>
    </main>
  </div>
</template>

<style scoped>
/* Scrollbar Styling */
.overflow-x-auto::-webkit-scrollbar {
  height: 4px;
}
.overflow-x-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
}
.overflow-x-auto::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 10px;
}
.overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background: #555;
}

/* Animations */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.animate-fadeIn {
  animation: fadeIn 0.3s ease-out;
}

/* Sidebar Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(-100%);
}

/* Details/Summary styling */
details summary::-webkit-details-marker {
  display: none;
}
details summary {
  list-style: none;
}
</style>