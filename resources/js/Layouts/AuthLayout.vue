<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 flex items-center justify-center p-4">
        <div class="max-w-md w-full">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div class="text-left">
                        <h1 class="text-2xl font-bold text-gray-900">FO Cloud 2026</h1>
                        <p class="text-sm text-gray-600">Hotel Management System</p>
                    </div>
                </div>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                <slot />
            </div>

            <!-- Footer -->
            <div class="text-center mt-6">
                <p class="text-gray-500 text-sm">
                    © 2024 FO Cloud 2026. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</template>