<template>
    <AuthenticatedLayout>
        <div class="max-w-5xl mx-auto p-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Posts</h1>
                <Link
                    href="/posts/create"
                    class="inline-flex items-center gap-2 bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4v16m8-8H4" />
                    </svg>
                    Buat Post
                </Link>
            </div>

            <!-- Posts List -->
            <div v-if="posts.data.length" class="space-y-5">
                <div
                    v-for="post in posts.data"
                    :key="post.id"
                    class="p-5 bg-white rounded-2xl shadow-sm hover:shadow-md transition"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 hover:text-blue-600 transition">
                                <Link :href="`/posts/${post.id}`">{{ post.title }}</Link>
                            </h2>
                            <p class="text-sm text-gray-500">
                                oleh <span class="font-medium text-gray-700">{{ post.user.name }}</span>
                            </p>
                        </div>

                        <!-- Tombol Delete via Modal -->
                        <button
                            v-if="canDelete(post)"
                            @click="openDelete(post)"
                            class="text-red-600 hover:underline"
                        >
                            Hapus
                        </button>
                    </div>

                    <p class="mt-3 text-gray-600 leading-relaxed">
                        {{ post.body.substring(0, 150) }}...
                    </p>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center text-gray-500 py-12">
                <p class="text-lg">Belum ada post nih 😅</p>
                <Link
                    href="/posts/create"
                    class="mt-3 inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition"
                >
                    Tulis Post Pertama
                </Link>
            </div>

            <!-- Pagination -->
            <div class="mt-8 flex justify-center gap-3">
                <button
                    v-if="posts.prev_page_url"
                    @click.prevent="visit(posts.prev_page_url)"
                    class="px-4 py-2 rounded-lg border text-gray-700 hover:bg-gray-50 transition"
                >
                    ‹ Prev
                </button>

                <button
                    v-if="posts.next_page_url"
                    @click.prevent="visit(posts.next_page_url)"
                    class="px-4 py-2 rounded-lg border text-gray-700 hover:bg-gray-50 transition"
                >
                    Next ›
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { inject } from 'vue'

// Props
const props = defineProps({
    posts: Object
})

// ambil user login
const page = usePage()
const user = page.props?.auth?.user || null

// modal global
const openModal = inject('openModal')

// Check kalau user pemilik post
const canDelete = (post) => user && user.id === post.user_id

// open delete modal
const openDelete = (post) => {
    openModal(
        [{ id: post.id, name: post.title }],
        'Konfirmasi Hapus Post',
        () => deletePost(post)
    )
}

// delete function
const deletePost = (post) => {
    if (!post?.id) return
    router.delete(`/posts/${post.id}`, {
        onSuccess: () => console.log('Post deleted'),
        onError: (errors) => console.error(errors)
    })
}

// pagination
const visit = (url) => router.visit(url)
</script>
