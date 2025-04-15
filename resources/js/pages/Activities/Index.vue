<!-- resources/js/pages/Activities/Index.vue -->
<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Modal from '@/components/Modal.vue';
import ActivitySummary from '@/components/ActivitySummary.vue';
import RecordDailyActivities from '@/components/RecordDailyActivities.vue'; // Import the new component
import AppLayout from '@/Layouts/AppLayout.vue';
import moment from 'moment';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Activity',
    href: '/activities',
  },
];

const props = defineProps({
  activities: { type: Object, default: () => ({ data: [] }) },
  parcelTypes: { type: Array, default: () => [] },
  rounds: { type: Array, default: () => [] },
  datePeriods: { type: Array, default: () => [] },
  date_periods: { type: Array, default: () => [] },
});

// Debug: Log all props to verify what's being passed
onMounted(() => {
  console.log('All props on mount:', props);
  console.log('datePeriods on mount:', props.datePeriods);
  console.log('date_periods on mount:', props.date_periods);
  console.log('Activities:', props.activities);
  console.log('Rounds:', props.rounds);
});

// Loading state to ensure props are ready
const isLoading = ref(true);
onMounted(() => {
  if (props.datePeriods?.length || props.date_periods?.length) {
    isLoading.value = false;
  } else {
    const unwatch = watch(
      () => [props.datePeriods, props.date_periods],
      ([newDatePeriods, newDatePeriodsSnake]) => {
        if (newDatePeriods?.length || newDatePeriodsSnake?.length) {
          isLoading.value = false;
          unwatch();
        }
      }
    );
    setTimeout(() => {
      isLoading.value = false;
    }, 2000);
  }
});

// Access flash messages from Inertia (server-side)
const flash = computed(() => usePage().props.flash);
const showFlash = ref(false);

// Local flash message state (client-side)
const localFlash = ref(null);
const showLocalFlash = ref(false);

// HTML parsing state
const htmlInput = ref('');
const showUnknownModal = ref(false);
const unknownParcelTypes = ref([]);
const parsedQuantities = ref({});

// Modal state for Paste Manifest HTML
const showPasteManifestModal = ref(false);

// Loading states
const isParsing = ref(false);

// Watch for server-side flash messages
watch(() => flash.value, (newFlash) => {
  if (newFlash?.success) {
    showFlash.value = true;
    setTimeout(() => {
      showFlash.value = false;
    }, 5000);
  }
});

// Watch for local flash messages
watch(localFlash, (newFlash) => {
  if (newFlash) {
    showLocalFlash.value = true;
    setTimeout(() => {
      showLocalFlash.value = false;
      localFlash.value = null;
    }, 5000);
  }
});

// Parse HTML and check for unknown parcel types
const parseHtml = async () => {
  if (isParsing.value) return;
  isParsing.value = true;

  try {
    const roundId = props.rounds.find(round => round.id === Number(htmlInput.value.round_id))?.id;
    if (!roundId) {
      localFlash.value = { error: 'Please select a round before parsing HTML' };
      return;
    }

    const selectedRound = props.rounds.find(round => round.id === roundId);
    if (!selectedRound || !htmlInput.value) {
      return;
    }

    const parser = new DOMParser();
    const doc = parser.parseFromString(htmlInput.value, 'text/html');
    const rows = doc.querySelectorAll('table.manifest-summary-inner tbody tr');

    const nameMapping = {
      'Parcels': 'Parcel',
      'Heavy/Large': 'Heavy / Large',
      'Manifested collections': 'Manifested Collections',
    };

    const extractedData = {};
    const unknownTypes = [];
    rows.forEach(row => {
      const type = row.querySelector('td:nth-child(1)')?.textContent.trim();
      const manifested = parseInt(row.querySelector('td:nth-child(2)')?.textContent.trim(), 10);

      if (!type || ['TOTAL', 'Next day', 'POD-Signature'].includes(type) || isNaN(manifested)) return;

      const dbName = nameMapping[type] || type;
      extractedData[dbName] = manifested;

      const exists = selectedRound.parcel_types.some(pt => pt.name === dbName);
      if (!exists) {
        unknownTypes.push({ name: dbName, quantity: manifested, max_weight: 0, max_length: 0, rate: 0 });
      }
    });

    if (unknownTypes.length > 0) {
      unknownParcelTypes.value = unknownTypes;
      parsedQuantities.value = extractedData;
      showUnknownModal.value = true;
    } else {
      updateQuantitiesWithParsedData(extractedData);
      submitDefaultForm();
    }
  } finally {
    isParsing.value = false;
    showPasteManifestModal.value = false;
  }
};

// Update quantities after parsing
const updateQuantitiesWithParsedData = (extractedData) => {
  const roundId = props.rounds.find(round => round.id === Number(htmlInput.value.round_id))?.id;
  const selectedRound = props.rounds.find(round => round.id === roundId);
  if (!selectedRound) return;

  const quantities = selectedRound.parcel_types.map(parcelType => {
    const quantityValue = extractedData[parcelType.name] || 0;
    return {
      parcel_type_id: parcelType.id,
      quantity: quantityValue,
    };
  });
  emit('submitDefaultForm', { round_id: roundId, quantities });
  htmlInput.value = '';
};

// Handle unknown parcel types
const createUnknownParcelTypes = () => {
  router.post('/parcel-types/bulk', {
    round_id: props.rounds.find(round => round.id === Number(htmlInput.value.round_id))?.id,
    parcel_types: unknownParcelTypes.value,
  }, {
    onSuccess: () => {
      router.reload({ only: ['rounds'], onSuccess: () => {
        updateQuantitiesWithParsedData(parsedQuantities.value);
        showUnknownModal.value = false;
        unknownParcelTypes.value = [];
        parsedQuantities.value = {};
        submitDefaultForm();
      }});
    },
  });
};

const ignoreUnknownParcelTypes = () => {
  const filteredData = { ...parsedQuantities.value };
  unknownParcelTypes.value.forEach(type => {
    delete filteredData[type.name];
  });
  updateQuantitiesWithParsedData(filteredData);
  showUnknownModal.value = false;
  unknownParcelTypes.value = [];
  parsedQuantities.value = {};
  submitDefaultForm();
};

// Modal form state (for add/edit/delete)
const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const form = ref({
  id: null,
  parcel_type_id: null,
  activity_date: '',
  quantity: '',
});

// State for Activity Details Modal
const showDetailsModal = ref(false);
const selectedDate = ref<string | null>(null);
const selectedRoundId = ref<number | null>(null);
const activitiesForDateAndRound = ref([]);

const resetForm = () => {
  form.value = { id: null, parcel_type_id: null, activity_date: '', quantity: '' };
};

const openAddModal = () => {
  resetForm();
  showAddModal.value = true;
};

const openEditModal = (activity) => {
  form.value = {
    id: activity.id,
    parcel_type_id: activity.parcel_type?.id ?? null,
    activity_date: activity.activity_date,
    quantity: activity.quantity,
  };
  showEditModal.value = true;
};

const openDeleteModal = (activity) => {
  form.value = { id: activity.id };
  showDeleteModal.value = true;
};

const openDetailsModal = async (date: string, roundId: number) => {
  selectedDate.value = date;
  selectedRoundId.value = roundId;

  // Parse the date assuming it's in YYYY-MM-DD format (as stored in summary.date)
  const formattedDate = moment(date, 'YYYY-MM-DD').format('YYYY-MM-DD');
  if (!moment(formattedDate, 'YYYY-MM-DD').isValid()) {
    console.error('Invalid date format:', date);
    activitiesForDateAndRound.value = [];
    showDetailsModal.value = true;
    return;
  }

  console.log('Fetching activities for date:', formattedDate, 'and roundId:', roundId);

  try {
    const response = await fetch(`/activities/details?date=${formattedDate}&round_id=${roundId}`);
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }
    const data = await response.json();
    console.log('API response:', data);

    activitiesForDateAndRound.value = Array.isArray(data) ? data : [];
    if (activitiesForDateAndRound.value.length === 0) {
      console.log('No activities found for this date and round.');
    }
    showDetailsModal.value = true;
  } catch (error) {
    console.error('Error fetching activities for modal:', error);
    activitiesForDateAndRound.value = [];
    showDetailsModal.value = true;
  }
};

const submitForm = () => {
  if (form.value.id) {
    router.put(`/activities/${form.value.id}`, form.value, {
      onSuccess: () => {
        showEditModal.value = false;
        resetForm();
      },
    });
  } else {
    router.post('/activities', form.value, {
      onSuccess: () => {
        showAddModal.value = false;
        resetForm();
      },
    });
  }
};

const deleteActivity = () => {
  router.delete(`/activities/${form.value.id}`, {
    onSuccess: () => {
      showDeleteModal.value = false;
      resetForm();
    },
  });
};

// Period filter for Activity Summary (checkbox dropdown)
const safeDatePeriods = computed(() => {
  const periods = props.datePeriods?.length ? props.datePeriods : props.date_periods;
  if (!periods || !Array.isArray(periods)) return [];
  return periods.filter(period => period && typeof period === 'object' && 'id' in period);
});

// Compute monetary value per date period
const sortKey = ref('period');
const sortOrder = ref(1); // 1 for ascending, -1 for descending

// Compute monetary value per date period
const monetaryValuePerPeriod = computed(() => {
  const periods = safeDatePeriods.value
    .map(period => {
      const activitiesInPeriod = (props.activities?.data || []).filter(activity => {
        const activityDate = moment(activity.activity_date);
        return activityDate.isBetween(moment(period.start_date), moment(period.end_date), undefined, '[]');
      });

      const totalValue = activitiesInPeriod.reduce((sum, activity) => {
        if (!activity.parcel_type || !activity.parcel_type.rate) return sum;
        return sum + (activity.quantity * activity.parcel_type.rate);
      }, 0);

      return {
        period: period.name || `${moment(period.start_date).format('DD/MM/YYYY')} - ${moment(period.end_date).format('DD/MM/YYYY')}`,
        totalValue: `£${totalValue.toFixed(2)}`,
        activitiesInPeriod, // Store activities for filtering
      };
    })
    .filter(period => period.activitiesInPeriod.length > 0); // Only include periods with activities

  return periods;
});

const sortedMonetaryValuePerPeriod = computed(() => {
  return [...monetaryValuePerPeriod.value].sort((a, b) => {
    const aValue = sortKey.value === 'totalValue' ? a[sortKey.value].replace('£', '') : a[sortKey.value];
    const bValue = sortKey.value === 'totalValue' ? b[sortKey.value].replace('£', '') : b[sortKey.value];
    return sortOrder.value * (aValue.localeCompare ? aValue.localeCompare(bValue) : aValue - bValue);
  });
});

const sortBy = (key) => {
  if (sortKey.value === key) {
    sortOrder.value *= -1;
  } else {
    sortKey.value = key;
    sortOrder.value = 1;
  }
};
</script>

<template>
  <Head title="Activities" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-6" style="max-width: 1600px;">
      <h1 class="text-2xl font-bold mb-6 text-gray-100">Activities</h1>

      <!-- Loading State -->
      <div v-if="isLoading" class="text-gray-400 text-center mb-4">
        Loading periods...
      </div>

      <div v-else>
        <!-- Server-side Flash Message -->
        <div v-if="showFlash && flash?.success" class="mb-6 p-4 bg-green-700 text-white rounded-lg flex items-center animate-fade-in">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          <span>{{ flash.success }}</span>
          <button @click="showFlash = false" class="ml-auto text-white hover:text-gray-200">×</button>
        </div>

        <!-- Local Flash Message -->
        <div v-if="showLocalFlash && localFlash?.error" class="mb-6 p-4 bg-red-700 text-white rounded-lg flex items-center animate-fade-in">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
          <span>{{ localFlash.error }}</span>
          <button @click="showLocalFlash = false; localFlash = null" class="ml-auto text-white hover:text-gray-200">×</button>
        </div>

        <!-- Side-by-Side Container (Now Full Width for Record Daily Activities) -->
        <div class="flex flex-wrap -mx-4 mb-8">
          <!-- Record Daily Activities -->
          <RecordDailyActivities
            :rounds="props.rounds"
            @open-paste-manifest-modal="showPasteManifestModal = true"
          />
        </div>

        <!-- Monetary Value per Date Period Table -->
        <h2 class="text-lg font-semibold text-gray-100 mb-4">Monetary Value per Date Period</h2>
        <div class="overflow-x-auto mb-8">
          <table class="w-full border bg-gray-900 rounded-lg">
            <thead>
              <tr class="bg-gray-800 text-gray-100">
                <th class="p-3 text-left cursor-pointer hover:bg-gray-700" @click="sortBy('period')">Date Period</th>
                <th class="p-3 text-left cursor-pointer hover:bg-gray-700" @click="sortBy('totalValue')">Total Value (£)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="period in sortedMonetaryValuePerPeriod" :key="period.period" class="border-t hover:bg-gray-800">
                <td class="p-3">{{ period.period }}</td>
                <td class="p-3">{{ period.totalValue }}</td>
              </tr>
              <tr v-if="!monetaryValuePerPeriod.length" class="border-t">
                <td colspan="2" class="p-3 text-gray-400 text-center">No data available</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Activity Summary Component -->
        <ActivitySummary
          :activities="props.activities"
          :rounds="props.rounds"
          :safe-date-periods="safeDatePeriods"
          :parcel-types="props.parcelTypes"
          @open-add-modal="openAddModal"
          @open-details-modal="openDetailsModal"
        />

        <!-- Modal for Paste Manifest HTML -->
        <Modal :show="showPasteManifestModal" title="Paste Manifest HTML" @close="showPasteManifestModal = false">
          <div class="p-6">
            <textarea
              v-model="htmlInput"
              placeholder="Paste the HTML table here..."
              class="w-full h-32 border rounded p-2 bg-gray-900 text-gray-200 placeholder-gray-400 mb-4 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
            ></textarea>
            <div class="flex justify-end space-x-3 relative group">
              <button
                @click="showPasteManifestModal = false"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition duration-200"
              >
                Cancel
              </button>
              <button
                @click="parseHtml"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition duration-200 disabled:bg-gray-500 disabled:cursor-not-allowed"
                :disabled="!htmlInput || !props.rounds.length || isParsing"
              >
                {{ isParsing ? 'Parsing...' : 'Parse HTML' }}
              </button>
              <span
                v-if="!htmlInput || !props.rounds.length"
                class="absolute bottom-full mb-2 hidden group-hover:block px-2 py-1 text-sm text-gray-100 bg-gray-700 rounded"
              >
                Select a round and enter HTML to enable
              </span>
            </div>
          </div>
        </Modal>

        <!-- Modal for Unknown Parcel Types -->
        <Modal :show="showUnknownModal" title="Unknown Parcel Types Detected" @close="showUnknownModal = false">
          <div class="p-6">
            <p class="text-gray-100 mb-6">The following parcel types were found in the HTML but do not exist in the selected round. Please choose to create them or ignore them.</p>
            <div v-for="(type, index) in unknownParcelTypes" :key="type.name" class="mb-6 p-4 bg-gray-800 rounded-lg">
              <h4 class="text-gray-100 font-semibold mb-2">{{ type.name }} (Quantity: {{ type.quantity }})</h4>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-100 mb-1">Max Weight (kg)</label>
                  <input
                    v-model.number="unknownParcelTypes[index].max_weight"
                    type="number"
                    step="0.01"
                    class="w-full border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                    required
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-100 mb-1">Max Length (cm)</label>
                  <input
                    v-model.number="unknownParcelTypes[index].max_length"
                    type="number"
                    step="0.01"
                    class="w-full border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                    required
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-100 mb-1">Rate (£)</label>
                  <input
                    v-model.number="unknownParcelTypes[index].rate"
                    type="number"
                    step="0.01"
                    class="w-full border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                    required
                  />
                </div>
              </div>
            </div>
            <div class="flex justify-end space-x-3">
              <button
                @click="showUnknownModal = false"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition duration-200"
              >
                Cancel
              </button>
              <button
                @click="ignoreUnknownParcelTypes"
                class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded transition duration-200"
              >
                Ignore
              </button>
              <button
                @click="createUnknownParcelTypes"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition duration-200"
              >
                Create
              </button>
            </div>
          </div>
        </Modal>

        <!-- Add Modal -->
        <Modal :show="showAddModal" title="Add Activity" @close="showAddModal = false">
          <div class="p-6">
            <form @submit.prevent="submitForm">
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-100 mb-1">Parcel Type</label>
                <select
                  v-model="form.parcel_type_id"
                  class="w-full border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                  required
                >
                  <option value="" disabled>Select a parcel type</option>
                  <option v-for="type in parcelTypes" :key="type.id" :value="type.id">
                    {{ type.name }} ({{ type.round?.name ?? 'No Round' }})
                  </option>
                </select>
              </div>
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-100 mb-1">Date</label>
                <input
                  v-model="form.activity_date"
                  type="date"
                  class="w-full border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                  required
                />
              </div>
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-100 mb-1">Quantity</label>
                <input
                  v-model="form.quantity"
                  type="number"
                  min="0"
                  class="w-full border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                  required
                />
              </div>
              <div class="flex justify-end space-x-3">
                <button
                  @click="showAddModal = false"
                  class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition duration-200"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition duration-200"
                >
                  Save
                </button>
              </div>
            </form>
          </div>
        </Modal>

        <!-- Edit Modal -->
        <Modal :show="showEditModal" title="Edit Activity" @close="showEditModal = false">
          <div class="p-6">
            <form @submit.prevent="submitForm">
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-100 mb-1">Parcel Type</label>
                <select
                  v-model="form.parcel_type_id"
                  class="w-full border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                  required
                >
                  <option value="" disabled>Select a parcel type</option>
                  <option v-for="type in parcelTypes" :key="type.id" :value="type.id">
                    {{ type.name }} ({{ type.round?.name ?? 'No Round' }})
                  </option>
                </select>
              </div>
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-100 mb-1">Date</label>
                <input
                  v-model="form.activity_date"
                  type="date"
                  class="w-full border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                  required
                />
              </div>
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-100 mb-1">Quantity</label>
                <input
                  v-model="form.quantity"
                  type="number"
                  min="0"
                  class="w-full border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                  required
                />
              </div>
              <div class="flex justify-end space-x-3">
                <button
                  @click="showEditModal = false"
                  class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition duration-200"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition duration-200"
                >
                  Update
                </button>
              </div>
            </form>
          </div>
        </Modal>

        <!-- Delete Modal -->
        <Modal :show="showDeleteModal" title="Delete Activity" @close="showDeleteModal = false">
          <div class="p-6">
            <p class="text-gray-100 mb-6">Are you sure you want to delete this activity?</p>
            <div class="flex justify-end space-x-3">
              <button
                @click="showDeleteModal = false"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition duration-200"
              >
                Cancel
              </button>
              <button
                @click="deleteActivity"
                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded transition duration-200"
              >
                Delete
              </button>
            </div>
          </div>
        </Modal>

        <!-- Activity Details Modal -->
        <Modal :show="showDetailsModal" :title="`Activities for ${selectedDate ? moment(selectedDate.value).format('DD/MM/YYYY') : ''}`" @close="showDetailsModal = false">
          <div class="p-6">
            <div v-if="activitiesForDateAndRound.length">
              <div class="overflow-x-auto">
                <table class="w-full border bg-gray-900 rounded-lg">
                  <thead>
                    <tr class="bg-gray-800 text-gray-100">
                      <th class="p-3 text-left">Parcel Type</th>
                      <th class="p-3 text-left">Quantity</th>
                      <th class="p-3 text-left">Value (£)</th>
                      <th class="p-3 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="activity in activitiesForDateAndRound" :key="activity.id" class="border-t hover:bg-gray-800">
                      <td class="p-3">{{ activity.parcel_type?.name || 'Unknown' }}</td>
                      <td class="p-3">{{ activity.quantity }}</td>
                      <td class="p-3">{{ `£${(activity.quantity * (activity.parcel_type?.rate || 0)).toFixed(2)}` }}</td>
                      <td class="p-3 flex space-x-2">
                        <button
                          @click="openEditModal(activity)"
                          class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded transition duration-200"
                        >
                          Edit
                        </button>
                        <button
                          @click="openDeleteModal(activity)"
                          class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded transition duration-200"
                        >
                          Delete
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div v-else class="text-gray-400 text-center">
              No activities found for this date and round.
            </div>
            <div class="flex justify-end mt-6">
              <button
                @click="showDetailsModal = false"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition duration-200"
              >
                Close
              </button>
            </div>
          </div>
        </Modal>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fadeIn 0.3s ease-in;
}
</style>
