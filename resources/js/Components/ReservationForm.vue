<script setup>
import { ref, inject, onMounted, watch, nextTick, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import GuestGridModal from './GuestGridModal.vue'
import CompanyGridModal from './CompanyGridModal.vue'
import Swal from 'sweetalert2'
import SelectField from '@/Components/SelectField.vue'
import axios from 'axios'

const tabSystem = inject('tabSystem')
const page = usePage()

// 🔹 Computed properties untuk auth data
const authData = computed(() => page.props.auth)
const properties = computed(() => authData.value?.property || [])
const currentICNO = computed(() => authData.value?.ICNO || '')
const currentHotelName = computed(() => authData.value?.hotelName || '')
const selectedProperty = ref(currentICNO.value)

// 🔹 State untuk Guest Modal
const showGuestModal = ref(false)
const guests = ref([])
const totalGuestCount = ref(0)
const isLoadingGuests = ref(false)

// 🔹 State untuk Company Modal
const showCompanyModal = ref(false)
const companies = ref([])
const totalCompaniesCount = ref(0)
const isLoadingCompanies = ref(false)

// 🔹 State untuk Property
const isChangingProperty = ref(false)

// 🔹 State untuk Segment, Sub Segment, dan Business Source
const segmentSubSegmentMap = ref({})
const segments = ref([])
const subSegments = ref([]) // Untuk sub-segment (dari tblsegmentsub)
const businessSources = ref([]) // Untuk business source (dari tblbusinesssource)
const isLoadingBusinessSources = ref(false)

// 🔹 State untuk default transaction
const defaultTrans = ref({})
const isLoadingDefaults = ref(false)

// 🔹 State untuk tracking loading progress
const isDataLoaded = ref(false)

const searchQuery = ref('')
const companySearchQuery = ref('')

// Generate unique instance ID
const instanceId = ref(`reservation-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`)

const formData = ref({
    property: '',
    arrival: new Date().toISOString().split('T')[0],
    nights: 1,
    departure: new Date(Date.now() + 86400000).toISOString().split('T')[0],
    folio: '', folioGroup: '', status: '', title: 'Mr', firstName: '', lastName: '',
    adult: 2, child: 0, infant: 0, vip: '', birthday: new Date().toISOString().split('T')[0],
    guestType: 1, city: '', address: '', identityType: 1, identityNumber: '000000',
    phone: '', email: '', nationality: null, bookingID: '', language: '', company: '',
    companyDisplay: '',
    segment: '', // 🔥 AKAN DIISI OTOMATIS DARI DEFAULT TRANS
    subSegment: '', source: '', origin: 'JKT', destination: 'JKT',
    creditLimit: '', voucherNo: '', isWaitingList: false, roomType: '', roomNumber: '',
    currency: 'IDR', rate: '', roomRate: '', lateCheckOut: '', lateCOEnabled: false,
    extraBed: false, extraBedAmount: '', companyRate: '', prePostingRate: '',
    cashierRemark: '', receptionRemark: '', outletRemark: '',
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

// Helper functions
function addDays(dateString, days) {
    const d = new Date(dateString)
    d.setDate(d.getDate() + days)
    return d.toISOString().split('T')[0]
}

function getNextDay(dateString) {
    return addDays(dateString, 1)
}

function calculateNights(arrivalDate, departureDate) {
    const arrival = new Date(arrivalDate)
    const departure = new Date(departureDate)
    arrival.setHours(12, 0, 0, 0)
    departure.setHours(12, 0, 0, 0)
    const diffTime = departure - arrival
    const diffDays = diffTime / (1000 * 60 * 60 * 24)
    return Math.max(1, Math.floor(diffDays))
}

function handleArrivalChange(selectedDates) {
    if (!selectedDates[0] || isInitializing.value) return
    const arrivalDate = selectedDates[0].toISOString().split('T')[0]
    formData.value.arrival = arrivalDate
    const nextDay = getNextDay(arrivalDate)
    if (departurePicker.value) {
        departurePicker.value.set('minDate', nextDay)
    }
    const nights = calculateNights(arrivalDate, formData.value.departure)
    formData.value.nights = nights
    nightsDisplay.value = nights
    if (nights < 1) {
        const newDeparture = getNextDay(arrivalDate)
        formData.value.departure = newDeparture
        formData.value.nights = 1
        nightsDisplay.value = 1
        if (departurePicker.value) {
            departurePicker.value.setDate(newDeparture)
        }
    }
}

function handleDepartureChange(selectedDates) {
    if (!selectedDates[0] || isInitializing.value) return
    const departureDate = selectedDates[0].toISOString().split('T')[0]
    const arrivalDate = formData.value.arrival
    const nights = calculateNights(arrivalDate, departureDate)
    formData.value.nights = nights
    nightsDisplay.value = nights
    formData.value.departure = departureDate
}

function changeNights(delta) {
    let nights = parseInt(formData.value.nights) || 1
    nights = Math.max(1, nights + delta)
    formData.value.nights = nights
    nightsDisplay.value = nights
    const newDeparture = addDays(formData.value.arrival, nights)
    formData.value.departure = newDeparture
    if (departurePicker.value) {
        departurePicker.value.setDate(newDeparture)
    }
}

function handleNightsBlur() {
    let nights = parseInt(nightsDisplay.value) || 0
    if (nights < 1) {
        nights = 1
        nightsDisplay.value = "1"
    }
    formData.value.nights = nights
    const newDeparture = addDays(formData.value.arrival, nights)
    formData.value.departure = newDeparture
    if (departurePicker.value) {
        departurePicker.value.setDate(newDeparture)
    }
}

function handleNightsInput(event) {
    const raw = event.target.value.replace(/\D/g, "")
    nightsDisplay.value = raw
}

const handleFirstNameEnter = (e) => {
    e.preventDefault()
    if (formData.value.firstName.trim().length >= 3) {
        searchQuery.value = formData.value.firstName.trim()
        showGuestModal.value = true
    }
}

const handleFirstNameTab = (e) => {
    if (formData.value.firstName.trim().length >= 3) {
        e.preventDefault()
        searchQuery.value = formData.value.firstName.trim()
        showGuestModal.value = true
    }
}

// Custom directive untuk flatpickr
const vFlatpickrInstance = {
    mounted(el, binding) {
        import('flatpickr').then(module => {
            const flatpickr = module.default
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
            if (binding.arg === 'arrival') {
                arrivalPicker.value = instance
            } else if (binding.arg === 'departure') {
                departurePicker.value = instance
            }
            nextTick(() => {
                if (arrivalPicker.value && departurePicker.value) {
                    setTimeout(() => {
                        isInitializing.value = false
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

watch(() => formData.value.nights, (newNights) => {
    nightsDisplay.value = newNights
})

// 🔹 WATCHERS - HANYA aktif setelah data terload
watch(() => formData.value.segment, async (newSegmentId, oldSegmentId) => {
    if (!isDataLoaded.value) return // 🔥 JANGAN proses jika data belum siap
    
    console.log('🔄 Segment changed from', oldSegmentId, 'to:', newSegmentId)

    if (newSegmentId) {
        // Update sub-segment options (sync)
        updateSubSegmentOptions(newSegmentId)
        
        // Update business source options (async) - tambah small delay
        await new Promise(resolve => setTimeout(resolve, 50))
        await updateBusinessSourceOptions(newSegmentId)
        
        // 🔥 APPLY DEFAULT SOURCE setelah business sources terload
        await applyDefaultSource()
        
        console.log('🎯 Final values after segment change:', {
            segment: newSegmentId,
            subSegment: formData.value.subSegment,
            source: formData.value.source
        })
    } else {
        subSegments.value = []
        businessSources.value = []
        formData.value.subSegment = null
        formData.value.source = null
    }
})

// 🔹 FUNCTIONS - DEFAULT TRANSACTION
const loadDefaultTrans = async () => {
    isLoadingDefaults.value = true
    try {
        const response = await axios.get('/api/default-trans')
        const data = response.data
        
        // Convert array ke object untuk mudah diakses
        const defaults = {}
        data.forEach(item => {
            defaults[item.colType] = item
        })
        
        defaultTrans.value = defaults
        console.log('✅ Default Transaction loaded:', defaults)
        
        // 🔥 APPLY DEFAULT VALUES KE formData
        applyDefaultValues(defaults)
        
    } catch (error) {
        console.error('❌ Error loading default transaction:', error)
    } finally {
        isLoadingDefaults.value = false
    }
}

const applyDefaultValues = (defaults) => {
    if (!defaults) return
    
    // Ambil nilai dari kolom "Reservation"
    if (defaults.Segment && defaults.Segment.Reservation) {
        formData.value.segment = parseInt(defaults.Segment.Reservation) || ''
        console.log('🎯 Applied default Segment:', formData.value.segment)
    }
    
    if (defaults.GuestType && defaults.GuestType.Reservation) {
        formData.value.guestType = parseInt(defaults.GuestType.Reservation) || 1
    }
    
    if (defaults.Country && defaults.Country.Reservation) {
        formData.value.nationality = parseInt(defaults.Country.Reservation) || null
    }
    
    if (defaults.DefPax && defaults.DefPax.Reservation) {
        formData.value.adult = parseInt(defaults.DefPax.Reservation) || 2
    }
    
    if (defaults.DefIDNo && defaults.DefIDNo.Reservation) {
        formData.value.identityNumber = defaults.DefIDNo.Reservation || '000000'
    }
    
    if (defaults.DefPhone && defaults.DefPhone.Reservation) {
        formData.value.phone = defaults.DefPhone.Reservation || '000000'
    }
    
    if (defaults.OriCity && defaults.OriCity.Reservation) {
        formData.value.origin = defaults.OriCity.Reservation || 'JKT'
    }
    
    if (defaults.DestCity && defaults.DestCity.Reservation) {
        formData.value.destination = defaults.DestCity.Reservation || 'JKT'
    }
    
    if (defaults.DefSource && defaults.DefSource.Reservation) {
        console.log('🔧 Default Source available:', defaults.DefSource.Reservation)
    }
    
    console.log('✅ Applied all default values for Reservation')
}

// 🔹 NEW FUNCTION untuk apply default source
const applyDefaultSource = async () => {
    if (!defaultTrans.value.DefSource || !defaultTrans.value.DefSource.Reservation) {
        return
    }
    
    const defaultSourceId = parseInt(defaultTrans.value.DefSource.Reservation)
    if (!defaultSourceId) return
    
    // Tunggu sampai business sources terload
    await new Promise(resolve => {
        const checkSources = () => {
            if (businessSources.value.length > 0) {
                resolve()
            } else {
                setTimeout(checkSources, 100)
            }
        }
        checkSources()
    })
    
    // Cari default source di business sources
    const defaultSource = businessSources.value.find(source => 
        source.busID === defaultSourceId
    )
    
    if (defaultSource) {
        formData.value.source = defaultSourceId
        console.log('✅ Applied default Business Source:', defaultSource.busRemark, 'ID:', defaultSourceId)
    } else {
        console.log('⚠️ Default source not found in available sources:', defaultSourceId)
    }
}

// 🔹 FUNCTIONS - SEGMENT & SUB SEGMENT
const loadSegmentSubSegmentMap = async () => {
    try {
        const response = await axios.get('/api/fields/segment-subsegment-map')
        segmentSubSegmentMap.value = response.data
        console.log('✅ Segment-SubSegment Map loaded:', segmentSubSegmentMap.value)
        return true
    } catch (error) {
        console.error('❌ Error loading segment-subsegment map:', error)
        segmentSubSegmentMap.value = {}
        return false
    }
}

const updateSubSegmentOptions = (segmentID) => {
    const subSegmentData = segmentSubSegmentMap.value[segmentID] || []

    console.log('🔄 Updating SUB-SEGMENT options for segment:', segmentID, 'data:', subSegmentData)

    subSegments.value = subSegmentData

    // Auto-select sub-segment pertama jika ada
    if (subSegmentData.length > 0) {
        formData.value.subSegment = subSegmentData[0].subID
        console.log('✅ Auto-selected SUB-SEGMENT:', subSegmentData[0].subName, 'ID:', subSegmentData[0].subID)
    } else {
        formData.value.subSegment = null
        console.log('⚠️ No sub-segments available for segment:', segmentID)
    }
}

const updateBusinessSourceOptions = async (segmentID) => {
    if (!segmentID) {
        businessSources.value = []
        formData.value.source = null
        return
    }

    isLoadingBusinessSources.value = true
    try {
        const response = await axios.get('/api/fields/business-sources', {
            params: { segment_id: segmentID }
        })

        businessSources.value = response.data
        console.log('✅ BUSINESS SOURCES loaded for segment', segmentID, ':', businessSources.value)

        // Auto-select business source pertama jika ada
        if (businessSources.value.length > 0) {
            formData.value.source = businessSources.value[0].busID
            console.log('✅ Auto-selected BUSINESS SOURCE:', businessSources.value[0].busRemark, 'ID:', businessSources.value[0].busID)
        } else {
            formData.value.source = null
            console.log('⚠️ No business sources available for segment:', segmentID)
        }
    } catch (error) {
        console.error('❌ Error loading business sources:', error)
        businessSources.value = []
        formData.value.source = null
    } finally {
        isLoadingBusinessSources.value = false
    }
}

const loadSegments = async () => {
    try {
        const response = await axios.get('/api/fields/segments')
        segments.value = response.data
        console.log('✅ Segments loaded:', segments.value)
        
        return true
    } catch (error) {
        console.error('❌ Error loading segments:', error)
        return false
    }
}

// 🔹 NEW FUNCTION - Initialize semua data dengan urutan yang benar
const initializeFormData = async () => {
    console.log('🚀 Starting form initialization...')
    
    // 1. Load default transaction values FIRST
    await loadDefaultTrans()
    
    // 2. Load segment-subsegment map dan segments secara PARALEL
    const [segmentMapLoaded, segmentsLoaded] = await Promise.all([
        loadSegmentSubSegmentMap(),
        loadSegments()
    ])
    
    if (segmentMapLoaded && segmentsLoaded) {
        console.log('✅ All segment data loaded successfully')
        
        // 🔥 JANGAN auto-select segment pertama jika sudah ada default value
        if (!formData.value.segment && segments.value.length > 0) {
            formData.value.segment = segments.value[0].segmentID
            console.log('⚠️ No default segment, using first segment:', formData.value.segment)
        } else {
            console.log('🎯 Using default segment:', formData.value.segment)
        }
        
        // 🔥 SEKARANG update sub-segment dan business source
        if (formData.value.segment) {
            console.log('🔄 Initializing sub-segment and business source for segment:', formData.value.segment)
            
            // Update sub-segment options
            updateSubSegmentOptions(formData.value.segment)
            
            // Update business source options
            await updateBusinessSourceOptions(formData.value.segment)
            
            // Apply default source
            await applyDefaultSource()
        }
        
        // Mark data sebagai ready
        isDataLoaded.value = true
        
        console.log('🎉 Form initialization COMPLETE!', {
            segment: formData.value.segment,
            subSegment: formData.value.subSegment,
            source: formData.value.source,
            segmentMap: Object.keys(segmentSubSegmentMap.value).length,
            segments: segments.value.length,
            subSegments: subSegments.value.length,
            businessSources: businessSources.value.length
        })
    } else {
        console.error('❌ Failed to load segment data')
    }
}

// 🔹 MOUNTED - Gunakan fungsi initialization yang baru
onMounted(async () => {
    await initializeFormData()
    nightsDisplay.value = formData.value.nights
})

const showMore = ref(false)

function closeReservation() {
    if (tabSystem && tabSystem.closeCurrentTab) {
        tabSystem.closeCurrentTab()
    }
}

function saveReservation() {
    console.log('=== RESERVATION FORM DATA ===')
    console.table({
        'Property': formData.value.property,
        'Arrival': formData.value.arrival,
        'Nights': formData.value.nights,
        'Departure': formData.value.departure,
        'Folio': formData.value.folio,
        'Folio Group': formData.value.folioGroup,
        'Status': formData.value.status,
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
        'Booking ID': formData.value.bookingID,
        'Language': formData.value.language,
        'Company': formData.value.company,
        'Company Display': formData.value.companyDisplay,
        'Segment': formData.value.segment,
        'Sub Segment': formData.value.subSegment,
        'Origin': formData.value.origin,
        'Destination': formData.value.destination,
        'Source': formData.value.source,
        'Credit Limit': formData.value.creditLimit,
        'Voucher No': formData.value.voucherNo,
        'Waiting List': formData.value.isWaitingList ? 'Yes' : 'No',
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
        'Cashier Remark': formData.value.cashierRemark,
        'Reception Remark': formData.value.receptionRemark,
        'Outlet Remark': formData.value.outletRemark
    })
    alert('Reservation data has been saved! Check console for details.')
}

// 🔹 GUEST FUNCTIONS (tetap sama)
const loadGuests = async (params = {}) => {
    if (params.searchValue && params.searchValue.trim().length < 3) {
        console.log('❌ Search query too short, skipping load:', params.searchValue)
        guests.value = []
        totalGuestCount.value = 0
        return
    }
    isLoadingGuests.value = true
    try {
        const response = await axios.get('/api/fields/guests', { params })
        guests.value = response.data.data || []
        totalGuestCount.value = response.data.totalCount || 0
        console.log('✅ Guests loaded:', {
            params: params,
            totalCount: totalGuestCount.value,
            guestsCount: guests.value.length
        })
        await new Promise(resolve => setTimeout(resolve, 300))
    } catch (error) {
        console.error('❌ Error loading guests:', error)
        guests.value = []
        totalGuestCount.value = 0
    } finally {
        isLoadingGuests.value = false
    }
}

async function openGuestModal() {
    searchQuery.value = formData.value.firstName.trim()
    console.log('🔹 Opening guest modal with search:', searchQuery.value)
    showGuestModal.value = true
    await nextTick()
    await new Promise(resolve => setTimeout(resolve, 100))
    console.log('🔹 Auto-loading with search:', searchQuery.value)
    loadGuests({
        searchValue: searchQuery.value,
        fields: 'GuestName,Phone,Email,TypeIDNumber,clientID',
        skip: 0,
        take: 20
    })
}

function closeGuestModal() {
    showGuestModal.value = false
}

function handleGuestSelected(guest) {
    console.table(guest)
    if (guest.guestName) {
        const names = guest.guestName.split(" ")
        formData.value.firstName = names[0]
        formData.value.lastName = names.slice(1).join(" ") || ""
    }
    formData.value.title = guest.title || 'Mr'
    formData.value.phone = guest.Phone || ''
    formData.value.email = guest.Email || ''
    formData.value.city = guest.City || ''
    formData.value.address = guest.Address || ''
    formData.value.nationality = guest.nationality || 'Indonesia'
    formData.value.identityType = guest.idType || 'KTP'
    formData.value.identityNumber = guest.idNumber || ''
    formData.value.birthday = guest.birthDate || new Date().toISOString().split('T')[0]
    formData.value.guestType = guest.guestType || 'Individual'
    formData.value.vip = guest.vip || ''
    formData.value.language = guest.Language || ''
    console.log('✅ Guest selected and populated:', guest)
    closeGuestModal()
}

function handleLoadGuests(params) {
    console.log('🔄 handleLoadGuests called with params:', params)
    if (params.searchValue && params.searchValue.trim().length < 3) {
        console.log('❌ Search query too short, skipping load:', params.searchValue)
        guests.value = []
        totalGuestCount.value = 0
        return
    }
    loadGuests(params)
}

// 🔹 COMPANY FUNCTIONS (tetap sama)
const loadCompanies = async (params = {}) => {
    if (params.searchValue && params.searchValue.trim().length < 3) {
        console.log('❌ Company search query too short, skipping load:', params.searchValue)
        companies.value = []
        totalCompaniesCount.value = 0
        return
    }
    isLoadingCompanies.value = true
    try {
        const requestParams = {
            skip: params.skip || 0,
            take: params.take || 20,
        }

        if (params.searchValue && params.searchValue.trim().length >= 3) {
            requestParams.searchValue = params.searchValue.trim()
        }

        if (params.searchFields) {
            requestParams.searchFields = params.searchFields
        } else if (params.fields) {
            requestParams.searchFields = params.fields
        }

        console.log('📤 Loading companies with params:', requestParams)

        const response = await axios.get('/api/fields/companies', {
            params: requestParams
        })

        companies.value = response.data.data || []
        totalCompaniesCount.value = response.data.totalCount || 0

        console.log('✅ Companies loaded:', {
            requestParams: requestParams,
            totalCount: totalCompaniesCount.value,
            companiesCount: companies.value.length,
            sampleData: companies.value.slice(0, 2)
        })

        await new Promise(resolve => setTimeout(resolve, 300))
    } catch (error) {
        console.error('❌ Error loading companies:', error)
        console.error('Error details:', {
            message: error.message,
            response: error.response?.data,
            status: error.response?.status
        })
        companies.value = []
        totalCompaniesCount.value = 0
    } finally {
        isLoadingCompanies.value = false
    }
}

async function openCompanyModal() {
    showCompanyModal.value = true
    loadCompanies({
        searchValue: '',
        fields: 'CompanyName,CompID,Address,CompPhone',
        skip: 0,
        take: 20
    })
}

function closeCompanyModal() {
    showCompanyModal.value = false
}

function handleCompanySelected(company) {
    console.table(company)

    if (company.creditFacility === 0 || company.creditFacility === "0") {
        Swal.fire({
            icon: 'error',
            title: '<span style="color: #e53e3e">⚠️ NO CREDIT FACILITY</span>',
            html: `
                <div style="text-align: left; margin-top: 1rem;">
                    <p style="margin-bottom: 0.5rem;"><strong>Company:</strong> ${company.CompName || 'N/A'}</p>
                    <p style="margin-bottom: 0.5rem;"><strong>Status:</strong> Credit Facility Not Approved</p>
                    <p style="margin-bottom: 0; color: #718096; font-size: 0.9rem;">
                        This company cannot make transactions on credit.
                    </p>
                </div>
            `,
            confirmButtonText: 'I Understand',
            confirmButtonColor: '#e53e3e',
            showCancelButton: false,
            customClass: {
                popup: 'sweet-alert-popup',
                title: 'sweet-alert-title'
            }
        })
    }

    formData.value.company = company.CompID || ''
    formData.value.companyDisplay = `${company.CompName || ''} -- ${company.cGroupName || ''}`

    console.log('✅ Company selected and populated:', company)
    closeCompanyModal()
}

function handleLoadCompanies(params) {
    console.log('🔄 handleLoadCompanies called with params:', params)
    if (params.searchValue && params.searchValue.trim().length < 3) {
        console.log('❌ Company search query too short, skipping load:', params.searchValue)
        companies.value = []
        totalCompaniesCount.value = 0
        return
    }
    loadCompanies(params)
}

async function handlePropertyChange() {
    if (!selectedProperty.value) return

    try {
        const response = await axios.post('/change-property', {
            ICNO: selectedProperty.value
        })

        if (response.data.success) {
            window.location.reload()
        } else {
            throw new Error(response.data.error || 'Failed to change property')
        }

    } catch (error) {
        console.error('❌ Error changing property:', error)
        selectedProperty.value = currentICNO.value

        Swal.fire({
            icon: 'error',
            title: 'Property Change Failed',
            text: error.response?.data?.error || error.message || 'Failed to change property. Please try again.',
            confirmButtonColor: '#3085d6',
        })
    }
}

// Watch untuk perubahan property
watch(selectedProperty, (newValue, oldValue) => {
    if (newValue && newValue !== oldValue && newValue !== currentICNO.value) {
        handlePropertyChange()
    }
})
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
                        <select v-model="selectedProperty"
                            class="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-1 focus:ring-indigo-500 text-sm"
                            :disabled="isChangingProperty">
                            <option v-for="property in properties" :key="property.ICNO" :value="property.ICNO">
                                {{ property.hotelName }}
                            </option>
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
                            <div class="col-span-2">
                                <label class="text-xs font-bold text-gray-600 mb-1 block">TITLE</label>
                                <select v-model="formData.title"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-xs">
                                    <option>Mr</option>
                                    <option>Mrs</option>
                                    <option>Miss</option>
                                </select>
                            </div>
                            <div class="col-span-4">
                                <label class="text-xs font-bold text-gray-600 mb-1 block">FIRST NAME <span
                                        class="text-red-500">*</span></label>
                                <input v-model="formData.firstName" @keydown.enter="handleFirstNameEnter"
                                    @keydown.tab="handleFirstNameTab"
                                    class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
                            </div>
                            <div class="col-span-6">
                                <label class="text-xs font-bold text-gray-600 mb-1 block">LAST NAME</label>
                                <div class="flex gap-1">
                                    <input v-model="formData.lastName"
                                        class="flex-1 px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
                                    <button @click="openGuestModal" type="button"
                                        class="px-3 bg-indigo-500 hover:bg-indigo-600 rounded text-white text-xs transition-colors flex items-center">
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
                            <div class="col-span-3">
                                <label class="text-xs font-bold text-gray-600 mb-1 block">VIP</label>
                                <SelectField v-model="formData.vip" placeholder="VIP List..." api="/api/fields/vip-list"
                                    :label="null" label-field="vipName" track-by="vipID" />
                            </div>

                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">GUEST TYPE</label>
                                <SelectField v-model="formData.guestType" placeholder="Guest Type..."
                                    api="/api/fields/guest-type-list" :label="null" label-field="guestTypeName"
                                    track-by="guestTypeID" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">BIRTHDAY</label>
                                <input type="text" v-model="formData.birthday" v-flatpickr="flatpickrOptions"
                                    class="w-full px-1 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-xs bg-white cursor-pointer"
                                    placeholder="Pilih tanggal" readonly />
                            </div>

                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">CITY <span
                                    class="text-red-500">*</span></label>
                            <input v-model="formData.city"
                                class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm" />
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
                                <SelectField v-model="formData.identityType" placeholder="Guest Type..."
                                    api="/api/fields/identity" :label="null" label-field="TypeName" track-by="TypeID" />
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
                            <SelectField v-model="formData.nationality" placeholder="Nationality..."
                                api="/api/fields/nationalities" :label="null" label-field="CountryName"
                                track-by="CountryID" />
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
                            <label class="text-xs font-bold text-gray-600 mb-1 block">🏢 COMPANY</label>
                            <div class="flex gap-1">
                                <input v-model="formData.companyDisplay"
                                    class="flex-1 px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 text-sm"
                                    placeholder="Search company..." disabled />
                                <button @click="openCompanyModal" type="button"
                                    class="px-3 bg-indigo-500 hover:bg-indigo-600 rounded text-white text-xs transition-colors flex items-center">
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
                                <SelectField v-model="formData.segment" placeholder="Select Segment..."
                                    :options="segments" :label="null" label-field="segmentName" track-by="segmentID" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">SUB-SEGMENT</label>
                                <SelectField v-model="formData.subSegment" placeholder="Select Sub Segment..."
                                    :options="subSegments" :label="null" label-field="subName" track-by="subID"
                                    :disabled="!formData.segment || subSegments.length === 0" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">ORIGIN</label>
                                <SelectField v-model="formData.origin" placeholder="Origin..." api="/api/fields/citys"
                                    :label="null" label-field="CityName" track-by="cityID" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-600 mb-1 block">DESTINATION</label>
                                <SelectField v-model="formData.destination" placeholder="Destination..."
                                    api="/api/fields/citys" :label="null" label-field="CityName" track-by="cityID" />
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-gray-600 mb-1 block">SOURCE</label>
                            <SelectField v-model="formData.source" placeholder="Select Source..."
                                :options="businessSources" :label="null" label-field="busRemark" track-by="busID"
                                :disabled="!formData.segment || businessSources.length === 0"
                                :loading="isLoadingBusinessSources" />
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

                        <div class="grid grid-cols-1 gap-1">
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
                    <button @click="saveReservation"
                        class="px-6 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-bold rounded-lg shadow text-sm transition">💾
                        SAVE</button>
                    <button
                        class="px-6 py-2 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-bold rounded-lg shadow text-sm transition"
                        @click="closeReservation">❌ CLOSE</button>
                </div>
            </div>

        </div>

        <!-- 🔹 GUEST GRID MODAL -->
        <GuestGridModal :show="showGuestModal" :guests="guests" :total-count="totalGuestCount"
            :initial-search-value="searchQuery" :is-loading="isLoadingGuests" @close="closeGuestModal"
            @select-guest="handleGuestSelected" @load-guests="handleLoadGuests" />

        <!-- 🔹 COMPANY GRID MODAL -->
        <CompanyGridModal :show="showCompanyModal" :companies="companies" :total-count="totalCompaniesCount"
            :initial-search-value="companySearchQuery" :is-loading="isLoadingCompanies" @close="closeCompanyModal"
            @select-company="handleCompanySelected" @load-companies="handleLoadCompanies" />
    </div>
</template>