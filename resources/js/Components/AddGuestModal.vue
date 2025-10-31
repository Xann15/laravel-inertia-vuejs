<!-- Components/AddGuestModal.vue -->
<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'save'])

const formData = ref({
  title: 'Mr',
  firstName: '',
  lastName: '',
  phone: '',
  email: '',
  city: '',
  address: '',
  nationality: 'Indonesia',
  idType: 'KTP',
  idNumber: '',
  birthday: new Date().toISOString().split('T')[0],
  guestType: 'Individual',
  vip: ''
})

// Reset form when modal is opened
watch(() => props.show, (newVal) => {
  if (newVal) {
    resetForm()
  }
})

function resetForm() {
  formData.value = {
    title: 'Mr',
    firstName: '',
    lastName: '',
    phone: '',
    email: '',
    city: '',
    address: '',
    nationality: 'Indonesia',
    idType: 'KTP',
    idNumber: '',
    birthday: new Date().toISOString().split('T')[0],
    guestType: 'Individual',
    vip: ''
  }
}

function closeModal() {
  emit('close')
}

function saveGuest() {
  // Validasi sederhana
  if (!formData.value.firstName) {
    alert('First Name is required!')
    return
  }
  
  if (!formData.value.phone) {
    alert('Phone is required!')
    return
  }
  
  if (!formData.value.city) {
    alert('City is required!')
    return
  }
  
  if (!formData.value.idNumber) {
    alert('ID Number is required!')
    return
  }
  
  // Emit data ke parent
  emit('save', { ...formData.value })
}
</script>

<template>
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
        class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 z-50"
        @click.self="closeModal"
      >
        <Transition
          enter-active-class="transition-all duration-200"
          enter-from-class="opacity-0 scale-95 translate-y-4"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition-all duration-200"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-95 translate-y-4"
        >
          <div
            v-if="show"
            class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col"
            @click.stop
          >
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50">
              <div class="flex items-center gap-3">
                <span class="text-3xl">➕</span>
                <div>
                  <h2 class="text-2xl font-bold text-gray-800">Add New Guest</h2>
                  <p class="text-sm text-gray-600">Fill in guest information</p>
                </div>
              </div>
              <button
                @click="closeModal"
                class="p-2 hover:bg-gray-100 rounded-lg transition"
              >
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto p-6">
              <div class="space-y-4">
                
                <!-- Name Section -->
                <div class="grid grid-cols-12 gap-3">
                  <div class="col-span-3">
                    <label class="block text-xs font-bold text-gray-600 mb-1">TITLE <span class="text-red-500">*</span></label>
                    <select v-model="formData.title"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                      <option>Mr</option>
                      <option>Mrs</option>
                      <option>Miss</option>
                      <option>Ms</option>
                    </select>
                  </div>
                  <div class="col-span-5">
                    <label class="block text-xs font-bold text-gray-600 mb-1">FIRST NAME <span class="text-red-500">*</span></label>
                    <input v-model="formData.firstName"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm"
                      placeholder="Enter first name" />
                  </div>
                  <div class="col-span-4">
                    <label class="block text-xs font-bold text-gray-600 mb-1">LAST NAME</label>
                    <input v-model="formData.lastName"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm"
                      placeholder="Enter last name" />
                  </div>
                </div>

                <!-- Contact Section -->
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">PHONE <span class="text-red-500">*</span></label>
                    <input type="tel" v-model="formData.phone"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm"
                      placeholder="+62812345678" />
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">EMAIL</label>
                    <input type="email" v-model="formData.email"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm"
                      placeholder="guest@email.com" />
                  </div>
                </div>

                <!-- Location Section -->
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">CITY <span class="text-red-500">*</span></label>
                    <input v-model="formData.city"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm"
                      placeholder="Enter city" />
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">NATIONALITY</label>
                    <select v-model="formData.nationality"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                      <option>Indonesia</option>
                      <option>USA</option>
                      <option>Australia</option>
                      <option>Singapore</option>
                      <option>Malaysia</option>
                    </select>
                  </div>
                </div>

                <!-- Address -->
                <div>
                  <label class="block text-xs font-bold text-gray-600 mb-1">ADDRESS</label>
                  <textarea v-model="formData.address" rows="2"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm resize-none"
                    placeholder="Enter full address"></textarea>
                </div>

                <!-- Identity Section -->
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">ID TYPE <span class="text-red-500">*</span></label>
                    <select v-model="formData.idType"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                      <option>KTP</option>
                      <option>Passport</option>
                      <option>SIM</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">ID NUMBER <span class="text-red-500">*</span></label>
                    <input v-model="formData.idNumber"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm"
                      placeholder="Enter ID number" />
                  </div>
                </div>

                <!-- Additional Info -->
                <div class="grid grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">BIRTHDAY</label>
                    <input type="date" v-model="formData.birthday"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm" />
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">GUEST TYPE</label>
                    <select v-model="formData.guestType"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                      <option>Individual</option>
                      <option>Corporate</option>
                      <option>Group</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">VIP STATUS</label>
                    <select v-model="formData.vip"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                      <option value="">None</option>
                      <option>Gold</option>
                      <option>Silver</option>
                      <option>Platinum</option>
                    </select>
                  </div>
                </div>

              </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-6 border-t border-gray-200 bg-gray-50">
              <div class="flex justify-end gap-3">
                <button
                  @click="closeModal"
                  class="px-6 py-2 bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-bold rounded-lg shadow text-sm transition"
                >
                  ❌ Cancel
                </button>
                <button
                  @click="saveGuest"
                  class="px-6 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-bold rounded-lg shadow text-sm transition"
                >
                  💾 Save Guest
                </button>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>