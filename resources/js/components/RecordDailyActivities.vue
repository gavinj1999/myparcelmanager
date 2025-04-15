<!-- resources/js/components/RecordDailyActivities.vue -->
<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import moment from 'moment';

const props = defineProps({
  rounds: { type: Array, default: () => [] },
});

const emit = defineEmits(['openPasteManifestModal', 'submitDefaultForm']);

// Default form state with dynamic today's date
const defaultForm = ref({
  activity_date: moment().format('YYYY-MM-DD'),
  round_id: null,
  quantities: [],
});

// Loading state
const isSubmitting = ref(false);

// Initialize quantities when a round is selected
const selectedRound = computed(() => {
  return props.rounds.find(round => round.id === Number(defaultForm.value.round_id)) || null;
});

const updateQuantities = () => {
  if (selectedRound.value) {
    defaultForm.value.quantities = selectedRound.value.parcel_types.map(parcelType => ({
      parcel_type_id: parcelType.id,
      quantity: 0,
    }));
  } else {
    defaultForm.value.quantities = [];
  }
};

// Set default round to the first option if rounds exist
if (props.rounds.length) {
  defaultForm.value.round_id = props.rounds[0].id;
  updateQuantities();
}

// Watch for changes in round_id to update quantities
watch(() => defaultForm.value.round_id, () => {
  updateQuantities();
});

// Submit the default form
const submitDefaultForm = async () => {
  if (isSubmitting.value) return;
  isSubmitting.value = true;

  try {
    const payload = {
      activity_date: defaultForm.value.activity_date,
      round_id: defaultForm.value.round_id,
      quantities: defaultForm.value.quantities,
    };
    router.post('/activities/bulk', payload, {
      onSuccess: () => {
        updateQuantities();
        router.reload({ preserveState: true, preserveScroll: true });
      },
    });
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div class="w-full px-4">
    <div class="p-6 bg-gray-800 rounded-xl shadow-lg">
      <h2 class="text-xl font-semibold text-gray-100 mb-4">Record Daily Activities</h2>
      <div v-if="!rounds.length" class="mb-4 p-4 bg-gray-800 text-gray-100 rounded-lg">
        No rounds available. Please create a round first.
      </div>
      <form v-else @submit.prevent="submitDefaultForm">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-100 mb-1">Date</label>
          <input
            v-model="defaultForm.activity_date"
            type="date"
            class="w-full border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
            required
          />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-100 mb-1">Round</label>
          <select
            v-model="defaultForm.round_id"
            class="w-full border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
            required
          >
            <option value="" disabled>Select a round</option>
            <option v-for="round in rounds" :key="round.id" :value="round.id">
              {{ round.name }}
            </option>
          </select>
        </div>
        <div v-if="selectedRound" class="mb-4">
          <h3 class="text-sm font-medium text-gray-100 mb-2">Parcel Types (Round {{ selectedRound.name }})</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div
              v-for="(quantity, index) in defaultForm.quantities"
              :key="quantity.parcel_type_id"
              class="flex items-center"
            >
              <label class="w-2/3 text-sm text-gray-100 truncate" :title="selectedRound.parcel_types[index].name">
                {{ selectedRound.parcel_types[index].name }}
              </label>
              <input
                v-model.number="defaultForm.quantities[index].quantity"
                type="number"
                min="0"
                class="w-1/3 border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                placeholder="0"
              />
            </div>
          </div>
        </div>
        <div class="flex justify-end space-x-3">
          <button
            type="button"
            @click="$emit('openPasteManifestModal')"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition duration-200"
          >
            Open Manifest HTML Input
          </button>
          <button
            type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition duration-200 disabled:bg-gray-500 disabled:cursor-not-allowed"
            :disabled="!defaultForm.round_id || isSubmitting"
          >
            {{ isSubmitting ? 'Saving...' : 'Record Activities' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
