<template>
  <AuthenticatedLayout>
    <div class="max-w-3xl mx-auto mt-10 bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ post.title }}</h1>

        <!-- Tombol edit/hapus hanya muncul kalau pemilik -->
        <div class="flex gap-2" v-if="canEditOrDelete">
          <Link
            :href="`/posts/${post.id}/edit`"
            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition"
          >
            ✏️ Edit
          </Link>

          <button
            @click="openDeleteModal()"
            class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition"
          >
            🗑️ Hapus
          </button>
        </div>
      </div>

      <!-- Info Penulis -->
      <p class="text-sm text-gray-500 mb-4">
        Oleh <span class="font-semibold">{{ post.user?.name || 'Unknown' }}</span> •
        {{ formattedDate }}
      </p>

      <!-- Isi Post -->
      <div class="text-gray-800 leading-relaxed whitespace-pre-line">
        {{ post.body }}
      </div>

      <!-- Tombol Kembali -->
      <div class="mt-8">
        <Link
          href="/posts"
          class="inline-flex items-center gap-2 text-gray-600 hover:text-indigo-600 transition"
        >
          ← Kembali ke daftar
        </Link>
      </div>
    </div>

    <!-- Debug Info -->
    <div class="max-w-3xl mx-auto mt-4 p-4 bg-gray-100 rounded-lg text-xs font-mono">
      <p><strong>Logged in user:</strong> {{ user?.id ?? 'Guest' }}</p>
      <p><strong>Can Edit/Delete:</strong> {{ canEditOrDelete }}</p>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { ref, computed, inject } from "vue";

// Props
const props = defineProps({
  post: { type: Object, required: true }
});

// modal global
const openModal = inject('openModal');

// ambil user login
const page = usePage();
const user = computed(() => page.props?.auth?.user || null);

// reusable ownership check
const canEditOrDelete = computed(() => {
  if (!user.value || !props.post?.user_id) return false;
  return user.value.id === props.post.user_id;
});

// format tanggal
const formattedDate = computed(() => {
  if (!props.post?.created_at) return 'Unknown date';
  return new Date(props.post.created_at).toLocaleDateString("id-ID");
});

// open modal delete via global modal
function openDeleteModal() {
  openModal(
    [{ id: props.post.id, name: 'Hapus Postingan' }],
    'Konfirmasi Hapus Post',
    () => deletePost()
  );
}

// delete logic
function deletePost() {
  if (!props.post?.id) return;
  router.delete(`/posts/${props.post.id}`, {
    onSuccess: () => console.log('Post deleted'),
    onError: (errors) => console.error(errors)
  });
}

</script>
