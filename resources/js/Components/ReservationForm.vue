<!-- Components/ReservationForm.vue -->
<script setup>
import { ref } from 'vue'

const formData = ref({
  property: '', arrival: new Date().toISOString().split('T')[0], nights: 1,
  departure: new Date(Date.now() + 86400000).toISOString().split('T')[0],
  folio: '', folioGroup: '', status: '', title: 'Mr', firstName: '', lastName: '',
  adult: 2, child: 0, infant: 0, vip: '', birthday: new Date().toISOString().split('T')[0],
  guestType: '', city: '', address: '', identityType: '', identityNumber: '000000',
  phone: '', email: '', nationality: '', bookingID: '', language: '', company: '',
  segment: '', subSegment: '', origin: 'JKT', destination: 'JKT', source: '',
  creditLimit: '', voucherNo: '', isWaitingList: false, roomType: '', roomNumber: '',
  currency: 'IDR', rate: '', roomRate: '', lateCheckOut: '', lateCOEnabled: false,
  extraBed: false, extraBedAmount: '', companyRate: '', prePostingRate: '',
  cashierRemark: '', receptionRemark: '', outletRemark: ''
})

function updateNights(inc) {
  const n = Math.max(1, formData.value.nights + inc)
  const arr = new Date(formData.value.arrival)
  const dep = new Date(arr.getTime() + n * 86400000)
  formData.value.nights = n
  formData.value.departure = dep.toISOString().split('T')[0]
}

const showMore = ref(false)
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 p-6">
    <div class="max-w-[1600px] mx-auto space-y-6">
      
      <!-- Header -->
      <div class="flex items-center justify-between">
        <h1 class="text-4xl font-bold text-gray-800 flex items-center gap-3">
          <span class="text-5xl">📋</span>
          Reservation Form
        </h1>
        <div class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white font-bold rounded-xl shadow-lg">
          🆕 NEW GUEST
        </div>
      </div>

      <!-- Top Info Card -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4">
          <div><label class="block text-xs font-bold text-gray-600 mb-2">🏨 PROPERTY</label><select v-model="formData.property" class="w-full px-3 py-2.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"><option>Select Property</option></select></div>
          <div><label class="block text-xs font-bold text-gray-600 mb-2">📅 ARRIVAL</label><input type="date" v-model="formData.arrival" class="w-full px-3 py-2.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" /></div>
          <div><label class="block text-xs font-bold text-gray-600 mb-2">🌙 NIGHTS</label><div class="flex gap-1"><button @click="updateNights(-1)" class="px-3 py-2.5 bg-gradient-to-r from-gray-200 to-gray-300 hover:from-gray-300 hover:to-gray-400 rounded-xl font-bold transition shadow-sm">−</button><input type="number" v-model.number="formData.nights" readonly class="w-16 text-center px-3 py-2.5 border-2 border-gray-300 rounded-xl bg-gray-50 font-bold" /><button @click="updateNights(1)" class="px-3 py-2.5 bg-gradient-to-r from-gray-200 to-gray-300 hover:from-gray-300 hover:to-gray-400 rounded-xl font-bold transition shadow-sm">+</button></div></div>
          <div><label class="block text-xs font-bold text-gray-600 mb-2">📅 DEPARTURE</label><input type="date" v-model="formData.departure" class="w-full px-3 py-2.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" /></div>
          <div><label class="block text-xs font-bold text-gray-600 mb-2">📄 FOLIO</label><input type="text" v-model="formData.folio" readonly placeholder="Auto" class="w-full px-3 py-2.5 border-2 border-gray-300 rounded-xl bg-gray-50" /></div>
          <div><label class="block text-xs font-bold text-gray-600 mb-2">📁 FOLIO GROUP</label><input type="text" v-model="formData.folioGroup" class="w-full px-3 py-2.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition" /></div>
          <div><label class="block text-xs font-bold text-gray-600 mb-2">✅ STATUS</label><select v-model="formData.status" class="w-full px-3 py-2.5 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"><option>Pending</option><option>Confirmed</option></select></div>
        </div>
      </div>

      <!-- Main Form -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
        
        <!-- Section Headers -->
        <div class="grid grid-cols-1 lg:grid-cols-12 bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 border-b-2">
          <div class="lg:col-span-5 p-4 border-r-2"><h2 class="text-xl font-bold text-gray-800 flex items-center gap-2"><span class="text-2xl">👤</span> PROFILE OPTIONS</h2></div>
          <div class="lg:col-span-4 p-4 border-r-2"><h2 class="text-xl font-bold text-gray-800 flex items-center gap-2"><span class="text-2xl">ℹ️</span> ADDITIONAL INFO</h2></div>
          <div class="lg:col-span-3 p-4"><h2 class="text-xl font-bold text-gray-800 flex items-center gap-2"><span class="text-2xl">🏠</span> ROOM & RATE</h2></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12">
          
          <!-- Column 1: Profile -->
          <div class="lg:col-span-5 p-6 border-r-2 space-y-4">
            <div class="grid grid-cols-12 gap-3">
              <div class="col-span-3"><label class="text-xs font-bold text-gray-600 mb-1 block">TITLE</label><select v-model="formData.title" class="w-full px-2 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm"><option>Mr</option><option>Mrs</option><option>Miss</option></select></div>
              <div class="col-span-5"><label class="text-xs font-bold text-gray-600 mb-1 block">FIRST NAME <span class="text-red-500">*</span></label><input v-model="formData.firstName" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" /></div>
              <div class="col-span-4"><label class="text-xs font-bold text-gray-600 mb-1 block">LAST NAME</label><div class="flex gap-1"><input v-model="formData.lastName" class="flex-1 px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" /><button class="px-2 bg-indigo-100 hover:bg-indigo-200 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg></button></div></div>
            </div>
            
            <div class="grid grid-cols-6 gap-3">
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">ADULT</label><input type="number" v-model.number="formData.adult" class="w-full px-2 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-center font-bold" /></div>
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">CHILD</label><input type="number" v-model.number="formData.child" class="w-full px-2 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-center font-bold" /></div>
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">INFANT</label><input type="number" v-model.number="formData.infant" class="w-full px-2 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-center font-bold" /></div>
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">VIP</label><select v-model="formData.vip" class="w-full px-2 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm"><option value="">-</option><option>Gold</option></select></div>
              <div class="col-span-2"><label class="text-xs font-bold text-gray-600 mb-1 block">BIRTHDAY</label><input type="date" v-model="formData.birthday" class="w-full px-2 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm" /></div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">GUEST TYPE</label><select v-model="formData.guestType" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"><option>Individual</option></select></div>
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">CITY <span class="text-red-500">*</span></label><input v-model="formData.city" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" /></div>
            </div>

            <div><label class="text-xs font-bold text-gray-600 mb-1 block">ADDRESS</label><input v-model="formData.address" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" /></div>

            <div class="grid grid-cols-2 gap-3">
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">IDENTITY TYPE <span class="text-red-500">*</span></label><select v-model="formData.identityType" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"><option>KTP</option><option>Passport</option></select></div>
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">IDENTITY NUMBER <span class="text-red-500">*</span></label><input v-model="formData.identityNumber" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" /></div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">PHONE <span class="text-red-500">*</span></label><input type="tel" v-model="formData.phone" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" /></div>
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">EMAIL</label><input type="email" v-model="formData.email" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" /></div>
            </div>

            <div><label class="text-xs font-bold text-gray-600 mb-1 block">NATIONALITY</label><select v-model="formData.nationality" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"><option>Indonesia</option><option>USA</option></select></div>
          </div>

          <!-- Column 2: Additional Info -->
          <div class="lg:col-span-4 p-6 border-r-2 space-y-4">
            <div><label class="text-xs font-bold text-gray-600 mb-1 block">BOOKING ID</label><input v-model="formData.bookingID" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" /></div>
            <div><label class="text-xs font-bold text-gray-600 mb-1 block">LANGUAGE</label><input v-model="formData.language" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" /></div>
            <div><label class="text-xs font-bold text-gray-600 mb-1 block">COMPANY</label><div class="flex gap-2"><input v-model="formData.company" readonly class="flex-1 px-3 py-2 border-2 border-gray-300 rounded-lg bg-gray-50" /><button class="px-3 bg-indigo-100 hover:bg-indigo-200 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg></button></div></div>
            
            <div class="grid grid-cols-2 gap-3">
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">SEGMENT <span class="text-red-500">*</span></label><select v-model="formData.segment" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"><option>Walk In</option></select></div>
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">SUB-SEGMENT</label><select v-model="formData.subSegment" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"><option>-</option></select></div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">ORIGIN</label><select v-model="formData.origin" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"><option>Jakarta</option></select></div>
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">DESTINATION</label><select v-model="formData.destination" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"><option>Jakarta</option></select></div>
            </div>

            <div><label class="text-xs font-bold text-gray-600 mb-1 block">SOURCE</label><select v-model="formData.source" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"><option>Select</option></select></div>
            <div><label class="text-xs font-bold text-gray-600 mb-1 block">CREDIT LIMIT</label><input v-model="formData.creditLimit" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" /></div>
            <div><label class="text-xs font-bold text-gray-600 mb-1 block">VOUCHER</label><input v-model="formData.voucherNo" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" /></div>
            <button class="w-full py-3 bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-white font-bold rounded-xl shadow-md transition">📝 NOTES</button>
          </div>

          <!-- Column 3: Room & Rate -->
          <div class="lg:col-span-3 p-6 space-y-4">
            <div class="flex items-center p-3 bg-amber-50 border-2 border-amber-300 rounded-xl"><input type="checkbox" v-model="formData.isWaitingList" class="w-5 h-5 text-indigo-600 rounded" /><label class="ml-2 text-sm font-bold text-amber-700">⏳ WAITING LIST</label></div>
            
            <div><label class="text-xs font-bold text-gray-600 mb-1 block">ROOM TYPE</label><input v-model="formData.roomType" readonly class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg bg-gray-50" /></div>
            <div><label class="text-xs font-bold text-gray-600 mb-1 block">ROOM NUMBER</label><div class="flex gap-2"><input v-model="formData.roomNumber" readonly class="flex-1 px-3 py-2 border-2 border-gray-300 rounded-lg bg-gray-50" /><button class="px-3 bg-indigo-100 hover:bg-indigo-200 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg></button></div></div>
            
            <div class="grid grid-cols-3 gap-2">
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">CURR</label><select v-model="formData.currency" class="w-full px-2 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm"><option>IDR</option></select></div>
              <div class="col-span-2"><label class="text-xs font-bold text-gray-600 mb-1 block">RATE</label><div class="flex gap-1"><input v-model="formData.rate" readonly class="flex-1 px-2 py-2 border-2 border-gray-300 rounded-lg bg-gray-50 text-sm" /><button class="px-2 bg-indigo-100 hover:bg-indigo-200 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg></button></div></div>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">ROOM RATE</label><input v-model="formData.roomRate" readonly class="w-full px-2 py-2 border-2 border-gray-300 rounded-lg bg-gray-50 text-sm" /></div>
              <div><label class="text-xs font-bold text-gray-600 mb-1 block">LATE C/O</label><div class="flex gap-1"><input v-model="formData.lateCheckOut" :disabled="!formData.lateCOEnabled" class="flex-1 px-2 py-2 border-2 border-gray-300 rounded-lg text-sm" /><input type="checkbox" v-model="formData.lateCOEnabled" class="w-5 h-5 text-indigo-600 rounded" /></div></div>
            </div>

            <div class="p-3 bg-blue-50 border-2 border-blue-200 rounded-xl"><div class="flex items-center justify-between"><div class="flex items-center"><input type="checkbox" v-model="formData.extraBed" class="w-5 h-5 text-indigo-600 rounded" /><label class="ml-2 text-sm font-bold text-blue-700">🛏️ EXTRA BED</label></div></div><input v-if="formData.extraBed" v-model="formData.extraBedAmount" class="w-full px-3 py-2 border-2 border-blue-300 rounded-lg mt-2" /></div>

            <div><label class="text-xs font-bold text-gray-600 mb-1 block">COMPANY RATE</label><input v-model="formData.companyRate" readonly class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg bg-gray-50" /></div>
            <div><label class="text-xs font-bold text-gray-600 mb-1 block">PRE-POST RATE</label><input v-model="formData.prePostingRate" readonly class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg bg-gray-50" /></div>
            <button class="w-full py-3 bg-gradient-to-r from-gray-300 to-gray-400 hover:from-gray-400 hover:to-gray-500 text-gray-800 font-bold rounded-xl shadow-md transition">👥 MULTI ROOM</button>
          </div>
        </div>

        <!-- Remarks -->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-6 border-t-2">
          <h3 class="text-lg font-bold text-gray-800 mb-4">💬 REMARKS</h3>
          <div class="grid grid-cols-3 gap-4">
            <div><label class="block text-xs font-bold text-gray-600 mb-2 text-center">CASHIER</label><textarea v-model="formData.cashierRemark" rows="4" class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 resize-none"></textarea></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-2 text-center">RECEPTION</label><textarea v-model="formData.receptionRemark" rows="4" class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 resize-none"></textarea></div>
            <div><label class="block text-xs font-bold text-gray-600 mb-2 text-center">OUTLET</label><textarea v-model="formData.outletRemark" rows="4" class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 resize-none"></textarea></div>
          </div>
        </div>
      </div>

      <!-- Action Bar -->
      <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-4 flex items-center justify-between">
        <div class="relative"><button @click="showMore = !showMore" class="px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white font-bold rounded-xl shadow-lg transition flex items-center gap-2">⚙️ MORE FUNCTIONS <svg class="w-4 h-4" :class="showMore ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button></div>
        <div class="flex gap-3">
          <button class="px-8 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-bold rounded-xl shadow-lg transition">💾 SAVE</button>
          <button class="px-8 py-3 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-bold rounded-xl shadow-lg transition">❌ CLOSE</button>
        </div>
      </div>

    </div>
  </div>
</template>