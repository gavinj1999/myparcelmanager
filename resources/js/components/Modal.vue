<!-- resources/js/components/Modal.vue -->
<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const isVisible = ref(false);

// Sync visibility with the show prop
watch(
  () => props.show,
  (newVal) => {
    isVisible.value = newVal;
  },
  { immediate: true }
);

// Close the modal
const close = () => {
  isVisible.value = false;
  emit('close');
};

// Close the modal when clicking the overlay (optional)
const closeOnOverlayClick = (event: MouseEvent) => {
  if (event.target === event.currentTarget) {
    close();
  }
};
</script>

<template>
  <div v-if="isVisible" class="modal-overlay" @click="closeOnOverlayClick">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-4">
        <h2>{{ title }}</h2>
        <button @click="close" class="text-gray-400 hover:text-gray-200">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      <slot />
    </div>
  </div>
</template>

<style scoped>
/* Style the modal overlay */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.8); /* Dark semi-transparent overlay */
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 50;
}

/* Style the modal content */
.modal-content {
  background-color: #1f2937; /* Tailwind's gray-800 */
  color: #f3f4f6; /* Tailwind's gray-100 */
  border-radius: 0.5rem; /* Tailwind's rounded-lg */
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3); /* Subtle shadow for depth */
  width: 100%;
  max-width: 600px; /* Adjust as needed */
  max-height: 80vh;
  overflow-y: auto;
  padding: 1rem; /* Tailwind's p-4 */
}

/* Style the modal title */
.modal-content h2 {
  color: #f3f4f6; /* Tailwind's gray-100 */
  font-size: 1.25rem; /* Tailwind's text-xl */
  font-weight: 600; /* Tailwind's font-semibold */
}

/* Ensure all text inside the modal is light */
.modal-content p,
.modal-content label,
.modal-content input,
.modal-content select,
.modal-content textarea {
  color: #f3f4f6; /* Tailwind's gray-100 */
}

/* Style inputs and selects to match the dark theme */
.modal-content input,
.modal-content select,
.modal-content textarea {
  background-color: #111827; /* Tailwind's gray-900 */
  border-color: #374151; /* Tailwind's gray-700 */
  color: #d1d5db; /* Tailwind's gray-300 */
}
.modal-content input:focus,
.modal-content select:focus,
.modal-content textarea:focus {
  border-color: #3b82f6; /* Tailwind's blue-500 */
  outline: none;
  ring: 2px solid #3b82f6; /* Tailwind's ring-2 and ring-blue-500 */
}

/* Style nested tables in the modal (e.g., Activity Details) */
.modal-content table {
  background-color: #111827; /* Tailwind's gray-900 */
  color: #f3f4f6; /* Tailwind's gray-100 */
}
.modal-content table thead {
  background-color: #1f2937; /* Tailwind's gray-800 */
}
.modal-content table tbody tr:hover {
  background-color: #1f2937; /* Tailwind's gray-800 */
}
</style>
