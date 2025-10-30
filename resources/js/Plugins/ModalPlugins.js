// resources/js/Plugins/ModalPlugin.js
import { ref } from 'vue';

export default {
  install(app) {
    const modalShow = ref(false);
    const modalItems = ref([]);
    let modalCallback = () => {};
    const modalTitle = ref('');

    const openModal = (items, title, callback) => {
      modalItems.value = items;
      modalTitle.value = title;
      modalCallback = callback;
      modalShow.value = true;
    };

    const selectModalItem = (item) => {
      modalCallback(item);
      modalShow.value = false;
    };

    const closeModal = () => {
      modalShow.value = false;
    };

    app.provide('modalState', { modalShow, modalItems, modalTitle });
    app.provide('openModal', openModal);
    app.provide('selectModalItem', selectModalItem);
    app.provide('closeModal', closeModal);
  }
};
