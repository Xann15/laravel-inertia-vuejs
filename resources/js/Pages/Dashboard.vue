<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const layoutRef = ref(null)

// Quick action menu items
const quickMenus = [
  {
    title: 'User Profile',
    icon: '👤',
    component: 'UserProfile',
    description: 'Manage your profile settings',
    color: 'indigo'
  },
  {
    title: 'Analytics',
    icon: '📊',
    component: 'Analytics',
    description: 'View your statistics',
    color: 'blue'
  },
  {
    title: 'Settings',
    icon: '⚙️',
    component: 'Settings',
    description: 'Configure your preferences',
    color: 'gray'
  },
  {
    title: 'Reports',
    icon: '📈',
    component: 'Reports',
    description: 'Generate and view reports',
    color: 'green'
  },
  {
    title: 'Messages',
    icon: '💬',
    component: 'Messages',
    description: 'Check your messages',
    color: 'purple'
  },
  {
    title: 'Notifications',
    icon: '🔔',
    component: 'Notifications',
    description: 'View all notifications',
    color: 'red'
  },
  {
    title: 'Calendar',
    icon: '📅',
    component: 'Calendar',
    description: 'Schedule and events',
    color: 'yellow'
  },
  {
    title: 'Tasks',
    icon: '✅',
    component: 'Tasks',
    description: 'Manage your tasks',
    color: 'teal'
  },
  {
    title: 'Files',
    icon: '📁',
    component: 'Files',
    description: 'Browse your files',
    color: 'orange'
  }
]

// Open tab via layout ref
function openTab(menu) {
  if (layoutRef.value && layoutRef.value.addTab) {
    layoutRef.value.addTab(menu.title, menu.component, menu.icon)
  }
}

// Get color classes
function getColorClasses(color) {
  const colors = {
    indigo: 'bg-indigo-50 hover:bg-indigo-100 border-indigo-200 text-indigo-700',
    blue: 'bg-blue-50 hover:bg-blue-100 border-blue-200 text-blue-700',
    gray: 'bg-gray-50 hover:bg-gray-100 border-gray-200 text-gray-700',
    green: 'bg-green-50 hover:bg-green-100 border-green-200 text-green-700',
    purple: 'bg-purple-50 hover:bg-purple-100 border-purple-200 text-purple-700',
    red: 'bg-red-50 hover:bg-red-100 border-red-200 text-red-700',
    yellow: 'bg-yellow-50 hover:bg-yellow-100 border-yellow-200 text-yellow-700',
    teal: 'bg-teal-50 hover:bg-teal-100 border-teal-200 text-teal-700',
    orange: 'bg-orange-50 hover:bg-orange-100 border-orange-200 text-orange-700',
  }
  return colors[color] || colors.gray
}

// Recent activity data
const recentActivities = [
  { action: 'Created new post', time: '2 minutes ago', icon: '📝', color: 'blue' },
  { action: 'Updated profile', time: '1 hour ago', icon: '👤', color: 'indigo' },
  { action: 'Uploaded files', time: '3 hours ago', icon: '📁', color: 'green' },
  { action: 'Sent message', time: '5 hours ago', icon: '💬', color: 'purple' },
]

// Stats data
const stats = [
  { label: 'Total Posts', value: '24', icon: '📝', color: 'blue', trend: '+12%' },
  { label: 'Views', value: '3.2K', icon: '👁️', color: 'green', trend: '+23%' },
  { label: 'Followers', value: '892', icon: '👥', color: 'purple', trend: '+5%' },
  { label: 'Engagement', value: '67%', icon: '💬', color: 'orange', trend: '+8%' },
]
</script>

<template>
  <Head title="Dashboard" />

  <AuthenticatedLayout ref="layoutRef">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          Dashboard
        </h2>
        <div class="flex items-center gap-2">
          <span class="text-sm text-gray-600">Welcome back,</span>
          <span class="text-sm font-semibold text-gray-800">
            {{ $page.props.auth?.user?.name ?? 'Guest' }}! 👋
          </span>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div
            v-for="stat in stats"
            :key="stat.label"
            class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-all"
          >
            <div class="flex items-center justify-between mb-4">
              <span class="text-3xl">{{ stat.icon }}</span>
              <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full">
                {{ stat.trend }}
              </span>
            </div>
            <p class="text-sm text-gray-600 mb-1">{{ stat.label }}</p>
            <p class="text-3xl font-bold text-gray-800">{{ stat.value }}</p>
          </div>
        </div>

        <!-- Quick Access Menu -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          <div class="px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
              <span class="text-2xl">🚀</span>
              Quick Access Menu
            </h3>
            <p class="text-sm text-gray-600 mt-1">Click on any menu to open it in a new tab</p>
          </div>

          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <button
                v-for="menu in quickMenus"
                :key="menu.title"
                @click="openTab(menu)"
                class="group relative overflow-hidden rounded-xl border-2 p-6 transition-all hover:shadow-lg hover:scale-105 text-left"
                :class="getColorClasses(menu.color)"
              >
                <!-- Icon -->
                <div class="flex items-start justify-between mb-3">
                  <span class="text-4xl group-hover:scale-110 transition-transform">
                    {{ menu.icon }}
                  </span>
                  <svg 
                    class="w-5 h-5 opacity-0 group-hover:opacity-100 transition-opacity" 
                    fill="none" 
                    stroke="currentColor" 
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </div>

                <!-- Content -->
                <h4 class="text-lg font-bold mb-2">{{ menu.title }}</h4>
                <p class="text-sm opacity-75">{{ menu.description }}</p>

                <!-- Hover effect overlay -->
                <div class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
              </button>
            </div>
          </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          <!-- Recent Activity -->
          <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
              <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <span class="text-xl">📊</span>
                Recent Activity
              </h3>
            </div>
            <div class="p-6">
              <div class="space-y-4">
                <div
                  v-for="(activity, index) in recentActivities"
                  :key="index"
                  class="flex items-center gap-4 p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors"
                >
                  <div class="flex-shrink-0 w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-sm">
                    <span class="text-xl">{{ activity.icon }}</span>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">
                      {{ activity.action }}
                    </p>
                    <p class="text-xs text-gray-500">{{ activity.time }}</p>
                  </div>
                  <button class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick Tips -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200">
              <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <span class="text-xl">💡</span>
                Quick Tips
              </h3>
            </div>
            <div class="p-6 space-y-4">
              <div class="p-4 bg-blue-50 rounded-lg border border-blue-200">
                <p class="text-sm text-blue-800 font-medium mb-1">Tab System</p>
                <p class="text-xs text-blue-600">Click any menu above to open it in a tab. You can open multiple tabs!</p>
              </div>
              <div class="p-4 bg-green-50 rounded-lg border border-green-200">
                <p class="text-sm text-green-800 font-medium mb-1">Close Tabs</p>
                <p class="text-xs text-green-600">Click the X button on any tab to close it.</p>
              </div>
              <div class="p-4 bg-purple-50 rounded-lg border border-purple-200">
                <p class="text-sm text-purple-800 font-medium mb-1">Switch Tabs</p>
                <p class="text-xs text-purple-600">Click on tab title to switch between open tabs.</p>
              </div>
            </div>
          </div>

        </div>

        <!-- Welcome Message (Original) -->
        <div class="bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 rounded-xl shadow-lg overflow-hidden">
          <div class="p-8 text-white">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-2xl font-bold mb-2">You're logged in! 🎉</h3>
                <p class="text-indigo-100">Welcome to your dashboard. Start by opening a menu above.</p>
              </div>
              <div class="text-6xl opacity-20">
                🚀
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
/* Custom animations */
@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.space-y-6 > * {
  animation: slideIn 0.5s ease-out;
  animation-fill-mode: both;
}

.space-y-6 > *:nth-child(1) { animation-delay: 0.1s; }
.space-y-6 > *:nth-child(2) { animation-delay: 0.2s; }
.space-y-6 > *:nth-child(3) { animation-delay: 0.3s; }
.space-y-6 > *:nth-child(4) { animation-delay: 0.4s; }
</style>