<!-- Components/ReservationForm.vue -->
<script setup>
import { ref, inject, onMounted, watch, nextTick } from 'vue'

const tabSystem = inject('tabSystem')

const formData = ref({
    property: '',
    arrival: new Date().toISOString().split('T')[0],
    nights: 1,
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

// Ref untuk input nights dan flatpickr instances
const nightsDisplay = ref(1)
const arrivalPicker = ref(null)
const departurePicker = ref(null)
const isInitializing = ref(true)

// Flatpickr options
const flatpickrOptions = {
    locale: 'id',
    dateFormat: 'Y-m-d',
    altFormat: 'd-M-Y',
    altInput: true,
    allowInput: false,
    minDate: new Date().toISOString().split('T')[0],
}

const departureOptions = ref({
    locale: 'id',
    dateFormat: 'Y-m-d',
    altFormat: 'd-M-Y',
    altInput: true,
    allowInput: false,
    minDate: getNextDay(formData.value.arrival),
})

// Helper: tambah hari dari tanggal tertentu
function addDays(dateString, days) {
    const d = new Date(dateString)
    d.setDate(d.getDate() + days)
    return d.toISOString().split('T')[0]
}

// Helper: tambah 1 hari dari tanggal tertentu
function getNextDay(dateString) {
    return addDays(dateString, 1)
}

// 🔹 Hitung selisih hari dengan cara yang lebih akurat
function calculateNights(arrivalDate, departureDate) {
    const arrival = new Date(arrivalDate)
    const departure = new Date(departureDate)
    arrival.setHours(12, 0, 0, 0)
    departure.setHours(12, 0, 0, 0)
    const diffTime = departure - arrival
    const diffDays = diffTime / (1000 * 60 * 60 * 24)
    return Math.max(1, Math.floor(diffDays))
}

// 🔹 Ketika Arrival berubah - UPDATE NIGHTS BERDASARKAN DEPARTURE (DEPARTURE TETAP)
function handleArrivalChange(selectedDates) {
    if (!selectedDates[0] || isInitializing.value) return

    const arrivalDate = selectedDates[0].toISOString().split('T')[0]
    formData.value.arrival = arrivalDate

    // Update minDate untuk departure picker
    const nextDay = getNextDay(arrivalDate)
    if (departurePicker.value) {
        departurePicker.value.set('minDate', nextDay)
    }

    // Hitung nights berdasarkan selisih arrival baru dan departure yang tetap
    const nights = calculateNights(arrivalDate, formData.value.departure)

    // Update nights
    formData.value.nights = nights
    nightsDisplay.value = nights

    // Jika arrival baru >= departure yang ada, set departure ke next day dari arrival
    if (nights < 1) {
        const newDeparture = getNextDay(arrivalDate)
        formData.value.departure = newDeparture
        formData.value.nights = 1
        nightsDisplay.value = 1

        if (departurePicker.value) {
            departurePicker.value.setDate(newDeparture)
        }
    }

    console.log('Arrival changed:', {
        arrival: arrivalDate,
        departure: formData.value.departure,
        nights: formData.value.nights
    })
}

// 🔹 Ketika Departure berubah - UPDATE NIGHTS BERDASARKAN SELISIH
function handleDepartureChange(selectedDates) {
    if (!selectedDates[0] || isInitializing.value) return

    const departureDate = selectedDates[0].toISOString().split('T')[0]
    const arrivalDate = formData.value.arrival

    // Hitung nights berdasarkan selisih
    const nights = calculateNights(arrivalDate, departureDate)

    // Update nights
    formData.value.nights = nights
    nightsDisplay.value = nights
    formData.value.departure = departureDate

    console.log('Departure changed:', {
        arrival: arrivalDate,
        departure: departureDate,
        nights: nights
    })
}

// 🔹 Tombol + / - untuk nights - UPDATE DEPARTURE BERDASARKAN NIGHTS
function changeNights(delta) {
    let nights = parseInt(formData.value.nights) || 1
    nights = Math.max(1, nights + delta)
    formData.value.nights = nights
    nightsDisplay.value = nights

    // Update departure berdasarkan nights baru
    const newDeparture = addDays(formData.value.arrival, nights)
    formData.value.departure = newDeparture

    // Update departure picker
    if (departurePicker.value) {
        departurePicker.value.setDate(newDeparture)
    }

    console.log('Nights changed:', {
        arrival: formData.value.arrival,
        nights: nights,
        departure: newDeparture
    })
}

// 🔹 Saat user selesai mengetik manual di nights - UPDATE DEPARTURE
function handleNightsBlur() {
    let nights = parseInt(nightsDisplay.value) || 0

    if (nights < 1) {
        nights = 1
        nightsDisplay.value = "1"
    }

    formData.value.nights = nights

    // Update departure berdasarkan nights baru
    const newDeparture = addDays(formData.value.arrival, nights)
    formData.value.departure = newDeparture

    // Update departure picker
    if (departurePicker.value) {
        departurePicker.value.setDate(newDeparture)
    }

    console.log('Nights manual input:', {
        arrival: formData.value.arrival,
        nights: nights,
        departure: newDeparture
    })
}

// 🔹 Filter input hanya angka
function handleNightsInput(event) {
    const raw = event.target.value.replace(/\D/g, "")
    nightsDisplay.value = raw
}

// Custom directive untuk flatpickr dengan instance reference
const vFlatpickrInstance = {
    mounted(el, binding) {
        import('flatpickr').then(module => {
            const flatpickr = module.default
            
            // Buat instance flatpickr
            const instance = flatpickr(el, {
                ...binding.value,
                onChange: function (selectedDates) {
                    if (binding.arg === 'arrival') {
                        handleArrivalChange(selectedDates)
                    } else if (binding.arg === 'departure') {
                        handleDepartureChange(selectedDates)
                    }
                }
            })

            // Simpan instance berdasarkan arg setelah flatpickr siap
            if (binding.arg === 'arrival') {
                arrivalPicker.value = instance
            } else if (binding.arg === 'departure') {
                departurePicker.value = instance
            }

            // Setelah semua flatpickr siap, nonaktifkan initializing
            nextTick(() => {
                if (arrivalPicker.value && departurePicker.value) {
                    // Tunggu sedikit untuk memastikan flatpickr benar-benar siap
                    setTimeout(() => {
                        isInitializing.value = false
                        console.log('All flatpickr instances ready - initializing disabled')
                    }, 100)
                }
            })
        })
    },
    unmounted(el) {
        if (el._flatpickr) {
            el._flatpickr.destroy()
        }
    }
}

// Watch untuk sinkronisasi nightsDisplay dengan formData.nights
watch(() => formData.value.nights, (newNights) => {
    nightsDisplay.value = newNights
})

// Inisialisasi
onMounted(() => {
    // Set initial nights display saja
    nightsDisplay.value = formData.value.nights
    
    console.log('Component mounted with initial values:', {
        arrival: formData.value.arrival,
        departure: formData.value.departure,
        nights: formData.value.nights
    })
})

const showMore = ref(false)

function closeWalkin() {
    if (tabSystem && tabSystem.closeCurrentTab) {
        tabSystem.closeCurrentTab()
    }
}

// Fungsi saveWalkin (tetap sama)
function saveWalkin() {
    console.log('=== RESERVATION FORM DATA ===')
    console.table({
        // Property & Dates
        'Property': formData.value.property,
        'Arrival': formData.value.arrival,
        'Nights': formData.value.nights,
        'Departure': formData.value.departure,
        'Folio': formData.value.folio,
        'Folio Group': formData.value.folioGroup,
        'Status': formData.value.status,

        // Guest Profile
        'Title': formData.value.title,
        'First Name': formData.value.firstName,
        'Last Name': formData.value.lastName,
        'Adult': formData.value.adult,
        'Child': formData.value.child,
        'Infant': formData.value.infant,
        'VIP': formData.value.vip,
        'Birthday': formData.value.birthday,
        'Guest Type': formData.value.guestType,
        'City': formData.value.city,
        'Address': formData.value.address,
        'ID Type': formData.value.identityType,
        'ID Number': formData.value.identityNumber,
        'Phone': formData.value.phone,
        'Email': formData.value.email,
        'Nationality': formData.value.nationality,

        // Additional Info
        'Booking ID': formData.value.bookingID,
        'Language': formData.value.language,
        'Company': formData.value.company,
        'Segment': formData.value.segment,
        'Sub Segment': formData.value.subSegment,
        'Origin': formData.value.origin,
        'Destination': formData.value.destination,
        'Source': formData.value.source,
        'Credit Limit': formData.value.creditLimit,
        'Voucher No': formData.value.voucherNo,
        'Waiting List': formData.value.isWaitingList ? 'Yes' : 'No',

        // Room & Rate
        'Room Type': formData.value.roomType,
        'Room Number': formData.value.roomNumber,
        'Currency': formData.value.currency,
        'Rate': formData.value.rate,
        'Room Rate': formData.value.roomRate,
        'Late Checkout': formData.value.lateCheckOut,
        'Late CO Enabled': formData.value.lateCOEnabled ? 'Yes' : 'No',
        'Extra Bed': formData.value.extraBed ? 'Yes' : 'No',
        'Extra Bed Amount': formData.value.extraBedAmount,
        'Company Rate': formData.value.companyRate,
        'Pre-Posting Rate': formData.value.prePostingRate,

        // Remarks
        'Cashier Remark': formData.value.cashierRemark,
        'Reception Remark': formData.value.receptionRemark,
        'Outlet Remark': formData.value.outletRemark
    })

    console.log('=== FULL FORM DATA OBJECT ===')
    console.log(JSON.parse(JSON.stringify(formData.value)))

    alert('Reservation data has been saved! Check console for details.')
}
</script>


<template>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 p-4">
        <div class="max-w-7xl mx-auto space-y-4">

            <!-- Header Compact -->
            <div class="flex items-center justify-between bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">📋</span>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">Reservation Form</h1>
                        <p class="text-sm text-gray-600">New Guest Registration</p>
                    </div>
                </div>
                <div class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white font-bold rounded-lg text-sm">
                    🆕 NEW GUEST
                </div>
            </div>

            <!-- Top Info Card - Compact -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-xs font-bold text-gray-600 mb-1">🏨 PROPERTY</label>
                        <select v-model="formData.property"
                            class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-1 focus:ring-indigo-500 text-sm">
                            <option>Select Property</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">📅 ARRIVAL</label>
                        <input type="text" v-model="formData.arrival" v-flatpickr-instance:arrival="flatpickrOptions"
                            class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-1 focus:ring-indigo-500 text-sm bg-white cursor-pointer"
                            placeholder="Pilih tanggal" readonly />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">🌙 NIGHTS</label>
                        <div class="flex gap-1">
                            <button @click="changeNights(-1)"
                                class="px-2 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-xs font-bold transition">−</button>
                            <input type="text" v-model="nightsDisplay" @input="handleNightsInput"
                                @blur="handleNightsBlur"
                                class="w-12 text-center px-1 py-2 border border-gray-300 rounded-lg bg-white text-sm font-bold" />
                            <button @click="changeNights(1)"
                                class="px-2 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-xs font-bold transition">+</button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">📅 DEPARTURE</label>
                        <input type="text" v-model="formData.departure"
                            v-flatpickr-instance:departure="departureOptions"
                            class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-1 focus:ring-indigo-500 text-sm bg-white cursor-pointer"
                            placeholder="Pilih tanggal" readonly />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">📄 FOLIO</label>
                        <input type="text" v-model="formData.folio" readonly placeholder="Auto"
                            class="w-full px-2 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">📁 FOLIO GROUP</label>
                        <input type="text" v-model="formData.folioGroup"
                            class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-1 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">✅ STATUS</label>
                        <select v-model="formData.status"
                            class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-1 focus:ring-indigo-500 text-sm">
                            <option>Pending</option>
                            <option>Confirmed</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Main Form - Compact -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

                <!-- Section Headers Compact -->
                <div class="grid grid-cols-1 lg:grid-cols-12 bg-gradient-to-r from-indigo-50 to-purple-50 border-b">
                    <div class="lg:col-span-5 p-3 border-r">
                        <h2 class="text-sm font-bold text-gray-800 flex items-center gap-2"><span
                                class="text-lg">👤</span> PROFILE</h2>
                    </div>
                    <div class="lg:col-span-4 p-3 border-r">
                        <h2 class="text-sm font-bold text-gray-800 flex items-center gap-2"><span
                                class="text-lg">ℹ️</span> INFO</h2>
                    </div>
                    <div class="lg:col-span-3 p-3">
                        <h2 class="text-sm font-bold text-gray-800 flex items-center gap-2"><span
                                class="text-lg">🏠</span> ROOM & RATE</h2>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12">

                    <!-- Column 1: Profile - Compact -->
                    <div class="lg:col-span-5 p-4 border-r space-y-3">
                        <div class="grid grid-cols-12 gap-2">
                            <div class="col-span-3">
                                <label class="text-xs font-bold text-gray-600 mb-1 block">TITLE</label>
                                <select v-model="formData.title"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-xs">
                                    <option>Mr</option>
                                    <option>Mrs</option>
                                    <option>Miss</option>
                                </select>
                            </div>
                            <div class="col-span-5">
                                <label class="text-xs font-bold text-gray-600 mb-1 block">FIRST NAME <span
                                        class="text-red-500">*</span></label>
                                <input v-model="formData.firstName"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
                            </div>
                            <div class="col-span-4">
                                <label class="text-xs font-bold text-gray-600 mb-1 block">LAST NAME</label>
                                <div class="flex gap-1">
                                    <input v-model="formData.lastName"
                                        class="flex-1 px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
                                    <button class="px-2 bg-indigo-100 hover:bg-indigo-200 rounded text-xs">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-6 gap-2">
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">ADULT</label>
                                <input type="number" v-model.number="formData.adult"
                                    class="w-full px-1 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-center text-sm font-bold" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">CHILD</label>
                                <input type="number" v-model.number="formData.child"
                                    class="w-full px-1 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-center text-sm font-bold" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">INFANT</label>
                                <input type="number" v-model.number="formData.infant"
                                    class="w-full px-1 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-center text-sm font-bold" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">VIP</label>
                                <select v-model="formData.vip"
                                    class="w-full px-1 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-xs">
                                    <option value="">-</option>
                                    <option>Gold</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="text-xs font-bold text-gray-600 mb-1 block">BIRTHDAY</label>
                                <input type="text" v-model="formData.birthday" v-flatpickr="flatpickrOptions"
                                    class="w-full px-1 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-xs bg-white cursor-pointer"
                                    placeholder="Pilih tanggal" readonly />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">GUEST TYPE</label>
                                <select v-model="formData.guestType"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm">
                                    <option>Individual</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">CITY <span
                                        class="text-red-500">*</span></label>
                                <input v-model="formData.city"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">ADDRESS</label>
                            <input v-model="formData.address"
                                class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">ID TYPE <span
                                        class="text-red-500">*</span></label>
                                <select v-model="formData.identityType"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm">
                                    <option>KTP</option>
                                    <option>Passport</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">ID NUMBER <span
                                        class="text-red-500">*</span></label>
                                <input v-model="formData.identityNumber"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">PHONE <span
                                        class="text-red-500">*</span></label>
                                <input type="tel" v-model="formData.phone"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">EMAIL</label>
                                <input type="email" v-model="formData.email"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">NATIONALITY</label>
                            <select v-model="formData.nationality"
                                class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm">
                                <option>Indonesia</option>
                                <option>USA</option>
                            </select>
                        </div>
                    </div>

                    <!-- Column 2: Additional Info - Compact -->
                    <div class="lg:col-span-4 p-4 border-r space-y-3">
                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">BOOKING ID</label>
                            <input v-model="formData.bookingID"
                                class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">LANGUAGE</label>
                            <input v-model="formData.language"
                                class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">COMPANY</label>
                            <div class="flex gap-1">
                                <input v-model="formData.company" readonly
                                    class="flex-1 px-2 py-1 border border-gray-300 rounded bg-gray-50 text-sm" />
                                <button class="px-2 bg-indigo-100 hover:bg-indigo-200 rounded text-xs">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">SEGMENT <span
                                        class="text-red-500">*</span></label>
                                <select v-model="formData.segment"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm">
                                    <option>Walk In</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">SUB-SEGMENT</label>
                                <select v-model="formData.subSegment"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm">
                                    <option>-</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">ORIGIN</label>
                                <select v-model="formData.origin"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm">
                                    <option>Jakarta</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">DESTINATION</label>
                                <select v-model="formData.destination"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm">
                                    <option>Jakarta</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">SOURCE</label>
                            <select v-model="formData.source"
                                class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm">
                                <option>Select</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">CREDIT LIMIT</label>
                            <input v-model="formData.creditLimit"
                                class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">VOUCHER</label>
                            <input v-model="formData.voucherNo"
                                class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
                        </div>
                        <button
                            class="w-full py-2 bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 text-white font-bold rounded-lg shadow text-sm transition">📝
                            NOTES</button>
                    </div>

                    <!-- Column 3: Room & Rate - Compact -->
                    <div class="lg:col-span-3 p-4 space-y-3">
                        <div class="flex items-center p-2 bg-amber-50 border border-amber-300 rounded-lg">
                            <input type="checkbox" v-model="formData.isWaitingList"
                                class="w-4 h-4 text-indigo-600 rounded" />
                            <label class="ml-2 text-xs font-bold text-amber-700">⏳ WAITING LIST</label>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">ROOM TYPE</label>
                            <input v-model="formData.roomType" readonly
                                class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-50 text-sm" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">ROOM NUMBER</label>
                            <div class="flex gap-1">
                                <input v-model="formData.roomNumber" readonly
                                    class="flex-1 px-2 py-1 border border-gray-300 rounded bg-gray-50 text-sm" />
                                <button class="px-2 bg-indigo-100 hover:bg-indigo-200 rounded text-xs">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-1">
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">CURR</label>
                                <select v-model="formData.currency"
                                    class="w-full px-1 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-xs">
                                    <option>IDR</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="text-xs font-bold text-gray-600 mb-1 block">RATE</label>
                                <div class="flex gap-1">
                                    <input v-model="formData.rate" readonly
                                        class="flex-1 px-1 py-1 border border-gray-300 rounded bg-gray-50 text-xs" />
                                    <button class="px-1 bg-indigo-100 hover:bg-indigo-200 rounded text-xs">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-1">
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">ROOM RATE</label>
                                <input v-model="formData.roomRate" readonly
                                    class="w-full px-1 py-1 border border-gray-300 rounded bg-gray-50 text-xs" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">LATE C/O</label>
                                <div class="flex gap-1">
                                    <input v-model="formData.lateCheckOut" :disabled="!formData.lateCOEnabled"
                                        class="flex-1 px-1 py-1 border border-gray-300 rounded text-xs" />
                                    <input type="checkbox" v-model="formData.lateCOEnabled"
                                        class="w-4 h-4 text-indigo-600 rounded" />
                                </div>
                            </div>
                        </div>

                        <div class="p-2 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <input type="checkbox" v-model="formData.extraBed"
                                        class="w-4 h-4 text-indigo-600 rounded" />
                                    <label class="ml-2 text-xs font-bold text-blue-700">🛏️ EXTRA BED</label>
                                </div>
                            </div>
                            <input v-if="formData.extraBed" v-model="formData.extraBedAmount"
                                class="w-full px-2 py-1 border border-blue-300 rounded mt-2 text-sm" />
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">COMPANY RATE</label>
                            <input v-model="formData.companyRate" readonly
                                class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-50 text-sm" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">PRE-POST RATE</label>
                            <input v-model="formData.prePostingRate" readonly
                                class="w-full px-2 py-1 border border-gray-300 rounded bg-gray-50 text-sm" />
                        </div>
                        <button
                            class="w-full py-2 bg-gradient-to-r from-gray-300 to-gray-400 hover:from-gray-400 hover:to-gray-500 text-gray-800 font-bold rounded-lg shadow text-sm transition">👥
                            MULTI ROOM</button>
                    </div>
                </div>

                <!-- Remarks Compact -->
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-4 border-t">
                    <h3 class="text-sm font-bold text-gray-800 mb-3">💬 REMARKS</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1 text-center">CASHIER</label>
                            <textarea v-model="formData.cashierRemark" rows="2"
                                class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 resize-none text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1 text-center">RECEPTION</label>
                            <textarea v-model="formData.receptionRemark" rows="2"
                                class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 resize-none text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1 text-center">OUTLET</label>
                            <textarea v-model="formData.outletRemark" rows="2"
                                class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 resize-none text-sm"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Bar Compact -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3 flex items-center justify-between">
                <div class="relative">
                    <button @click="showMore = !showMore"
                        class="px-4 py-2 bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white font-bold rounded-lg shadow text-sm transition flex items-center gap-1">
                        ⚙️ MORE FUNCTIONS
                        <svg class="w-3 h-3" :class="showMore ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>
                <div class="flex gap-2">
                    <button @click="saveWalkin"
                        class="px-6 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-bold rounded-lg shadow text-sm transition">💾
                        SAVE</button>
                    <button
                        class="px-6 py-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-bold rounded-lg shadow text-sm transition"
                        @click="closeWalkin">❌ CLOSE</button>
                </div>
            </div>

        </div>
    </div>
</template>