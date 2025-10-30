<template>
    <AuthenticatedLayout>
        <div class="max-w-2xl mx-auto mt-10 bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    ✍️ Buat Post Baru
                </h1>
                <Link
                    href="/posts"
                    class="flex items-center gap-1 text-gray-500 hover:text-gray-700 transition"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    <span>Kembali</span>
                </Link>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Judul -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Judul</label>
                    <input
                        v-model="form.title"
                        type="text"
                        placeholder="Masukkan judul postingan..."
                        class="border border-gray-300 focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 rounded-lg w-full p-3 text-gray-800 placeholder-gray-400 outline-none transition"
                    />
                    <p v-if="form.errors.title" class="text-red-500 text-sm mt-1">
                        {{ form.errors.title }}
                    </p>
                </div>

                <!-- Isi -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Isi</label>
                    <textarea
                        v-model="form.body"
                        rows="6"
                        placeholder="Tulis sesuatu yang menarik..."
                        class="border border-gray-300 focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 rounded-lg w-full p-3 text-gray-800 placeholder-gray-400 outline-none transition resize-none"
                    ></textarea>
                    <p v-if="form.errors.body" class="text-red-500 text-sm mt-1">
                        {{ form.errors.body }}
                    </p>
                </div>

                <!-- Tombol -->
                <div class="flex justify-end gap-3">
                    <Link
                        href="/posts"
                        class="px-5 py-2.5 rounded-lg text-gray-600 border border-gray-300 hover:bg-gray-100 transition-all duration-150"
                    >
                        Batal
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg transition-all duration-200 disabled:opacity-50 flex items-center gap-2"
                    >
                        <span v-if="form.processing" class="animate-pulse">⏳ Menyimpan...</span>
                        <span v-else>💾 Simpan</span>
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { useForm, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const form = useForm({
    title: "",
    body: "",
});

function submit() {
    form.post("/posts");
}
</script>
