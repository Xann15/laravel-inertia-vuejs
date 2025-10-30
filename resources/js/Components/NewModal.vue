<template>
  <dialog ref="dialog" v-show="show" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50" @click="close"></div>
    <div class="bg-white p-6 rounded-lg z-10 max-w-lg w-full">
      <h2 class="text-lg font-bold mb-4">{{ title }}</h2>
      <ul>
        <li
          v-for="item in items"
          :key="item.id"
          class="cursor-pointer p-2 hover:bg-gray-100"
          @dblclick="selectItem(item)"
        >
          {{ item.name }}
        </li>
      </ul>
      <button class="mt-4 px-4 py-2 bg-gray-200 rounded" @click="close">Close</button>
    </div>
  </dialog>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  show: Boolean,
  items: Array,
  title: String
});
const emit = defineEmits(['close', 'select']);

const dialog = ref();

watch(() => props.show, val => {
  if (val) dialog.value?.showModal?.();
  else dialog.value?.close?.();
});

const close = () => emit('close');

const selectItem = (item) => {
  emit('select', item);
  close();
};
</script>
