<template>
    <AuthLayout>
        <form @submit.prevent="submit" class="space-y-6">
            <!-- Header -->
            <div class="text-center">
                <h1 class="text-2xl font-bold text-gray-900">Welcome back</h1>
                <p class="text-gray-600 mt-2">Sign in to your account</p>
            </div>

            <!-- Username -->
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                    Username
                </label>
                <input
                    id="username"
                    type="text"
                    v-model="form.username"
                    :class="[
                        'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition',
                        errors.username ? 'border-red-500 bg-red-50' : 'border-gray-300'
                    ]"
                    placeholder="Enter your username"
                    :disabled="processing"
                />
                <div v-if="errors.username" class="text-red-500 text-sm mt-1 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    {{ errors.username }}
                </div>
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    Password
                </label>
                <input
                    id="password"
                    type="password"
                    v-model="form.password"
                    :class="[
                        'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition',
                        errors.password ? 'border-red-500 bg-red-50' : 'border-gray-300'
                    ]"
                    placeholder="Enter your password"
                    :disabled="processing"
                />
                <div v-if="errors.password" class="text-red-500 text-sm mt-1 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    {{ errors.password }}
                </div>
            </div>

            <!-- Error Message -->
            <div v-if="errors.login" class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center gap-2 text-red-700">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm font-medium">{{ errors.login }}</span>
                </div>
            </div>

            <!-- Submit Button -->
            <button
                type="submit"
                :disabled="processing"
                :class="[
                    'w-full py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200',
                    processing 
                        ? 'bg-gray-400 cursor-not-allowed' 
                        : 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 transform hover:-translate-y-0.5'
                ]"
            >
                <div class="flex items-center justify-center">
                    <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ processing ? 'Signing in...' : 'Sign in' }}</span>
                </div>
            </button>

            <!-- Demo Credentials (Optional) -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="text-sm font-medium text-blue-800 mb-2">Demo Credentials</h3>
                <div class="text-xs text-blue-700 space-y-1">
                    <p><span class="font-medium">Username:</span> demo_user</p>
                    <p><span class="font-medium">Password:</span> demo_password</p>
                </div>
            </div>
        </form>
    </AuthLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'

const processing = ref(false)

const form = useForm({
    username: '',
    password: '',
})

const props = defineProps({
    errors: Object,
})

const submit = () => {
    processing.value = true
    form.post(route('login'), {
        onFinish: () => {
            processing.value = false
        },
    })
}
</script>