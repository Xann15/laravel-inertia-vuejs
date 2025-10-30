<template>
  <AuthenticatedLayout>
    <div class="max-w-3xl mx-auto mt-10">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">✏️ Edit Post</h1>
        <p class="text-gray-600">Perbarui postingan Anda</p>
      </div>

      <!-- Form Card -->
      <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-100">
        <form @submit.prevent="submitForm">
          <!-- Title -->
          <div class="mb-6">
            <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">
              Judul <span class="text-red-500">*</span>
            </label>
            <input
              id="title"
              type="text"
              v-model="form.title"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
              :class="{ 'border-red-500': form.errors.title }"
              placeholder="Masukkan judul post..."
              required
            />
            <p v-if="form.errors.title" class="mt-2 text-sm text-red-600">
              {{ form.errors.title }}
            </p>
          </div>

          <!-- Body -->
          <div class="mb-6">
            <label for="body" class="block text-sm font-semibold text-gray-700 mb-2">
              Konten <span class="text-red-500">*</span>
            </label>
            <textarea
              id="body"
              v-model="form.body"
              rows="10"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition resize-none"
              :class="{ 'border-red-500': form.errors.body }"
              placeholder="Tulis konten post Anda di sini..."
              required
            ></textarea>
            <p v-if="form.errors.body" class="mt-2 text-sm text-red-600">
              {{ form.errors.body }}
            </p>
          </div>

          <!-- Buttons -->
          <div class="flex items-center justify-between gap-4 pt-4 border-t border-gray-200">
            <Link
              :href="route('posts.show', post.id)"
              class="px-6 py-3 text-gray-600 hover:text-gray-800 font-medium transition"
            >
              ← Batal
            </Link>

            <div class="flex gap-3">
              <!-- Preview Button -->
              <button
                type="button"
                @click="showPreview = !showPreview"
                class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium transition"
              >
                {{ showPreview ? '✏️ Edit' : '👁️ Preview' }}
              </button>

              <!-- Submit Button -->
              <button
                type="submit"
                :disabled="form.processing"
                class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-lg hover:from-indigo-700 hover:to-indigo-800 font-semibold transition shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="form.processing">
                  <i class="bi bi-hourglass-split animate-spin"></i> Menyimpan...
                </span>
                <span v-else>
                  💾 Simpan Perubahan
                </span>
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- Preview Card -->
      <transition name="fade">
        <div v-if="showPreview" class="mt-6 bg-white rounded-2xl shadow-lg p-8 border border-indigo-200">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-indigo-600">👁️ Preview</h2>
            <button
              @click="showPreview = false"
              class="text-gray-400 hover:text-gray-600 transition"
            >
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
          
          <div class="border-t border-gray-200 pt-4">
            <h3 class="text-2xl font-bold text-gray-800 mb-3">{{ form.title || 'Judul kosong' }}</h3>
            <p class="text-sm text-gray-500 mb-4">
              Oleh <span class="font-semibold">{{ $page.props.auth.user.name }}</span> • 
              {{ new Date().toLocaleDateString('id-ID') }}
            </p>
            <div class="text-gray-700 leading-relaxed whitespace-pre-line">
              {{ form.body || 'Konten kosong' }}
            </div>
          </div>
        </div>
      </transition>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Link, useForm, router } from "@inertiajs/vue3";
import { ref } from "vue";

const props = defineProps({
  post: {
    type: Object,
    required: true
  }
});

const showPreview = ref(false);

// Initialize form with existing post data
const form = useForm({
  title: props.post.title,
  body: props.post.body,
});

function submitForm() {
  form.put(route('posts.update', props.post.id), {
    preserveScroll: true,
    onSuccess: () => {
      console.log('Post updated successfully');
    },
    onError: (errors) => {
      console.error('Error updating post:', errors);
    }
  });
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
.animate-spin {
  animation: spin 1s linear infinite;
}
</style>