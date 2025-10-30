// resources/js/directives/flatpickr.js
import flatpickr from "flatpickr";
import { Indonesian } from "flatpickr/dist/l10n/id.js";

export default {
  mounted(el, binding) {
    const options = binding.value || {};
    
    // Set default options
    const defaultOptions = {
      locale: Indonesian,
      dateFormat: "Y-m-d",
      allowInput: true,
      clickOpens: true,
      ...options
    };

    // Initialize flatpickr
    const fp = flatpickr(el, defaultOptions);

    // Store flatpickr instance on element
    el._flatpickr = fp;
  },
  unmounted(el) {
    // Destroy flatpickr instance when component unmounts
    if (el._flatpickr) {
      el._flatpickr.destroy();
      el._flatpickr = null;
    }
  }
};