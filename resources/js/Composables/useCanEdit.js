// resources/js/Composables/useCanEdit.js
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export default function useCanEdit(resourceId) {
  const page = usePage();

  const user = computed(() => {
    try {
      return page.props?.auth?.user || null;
    } catch (e) {
      console.error('Error accessing user:', e);
      return null;
    }
  });

  const canEditOrDelete = computed(() => {
    try {
      if (!user.value || !resourceId) return false;
      return user.value.id === resourceId;
    } catch (e) {
      console.error('Error computing canEditOrDelete:', e);
      return false;
    }
  });

  return { user, canEditOrDelete };
}