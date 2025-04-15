<!-- resources/js/components/ActivitySummary.vue -->
<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import moment from 'moment';

const props = defineProps({
  activities: { type: Object, default: () => ({ data: [] }) },
  rounds: { type: Array, default: () => [] },
  safeDatePeriods: { type: Array, default: () => [] },
  parcelTypes: { type: Array, default: () => [] },
});

const emit = defineEmits(['openAddModal', 'openDetailsModal']);

// Filter states
const selectedDate = ref<string | null>(null);
const selectedRoundId = ref<number | null>(null);
const selectedPeriodIds = ref<string[]>([]);
const recordsPerPage = ref(10); // Default number of records to display
const currentPage = ref(1); // Pagination state
const showPeriodDropdown = ref(false);

// Sorting state
const sortKey = ref('date'); // Default sort by date
const sortOrder = ref(-1); // -1 for descending, 1 for ascending

// Calculate total value for an activity
const calculateTotalValue = (activity) => {
  if (!activity.parcel_type || !activity.parcel_type.rate) return 0;
  return activity.quantity * activity.parcel_type.rate;
};

// Handle "All Periods" checkbox
const handleAllPeriodsChange = () => {
  if (selectedPeriodIds.value.includes('all')) {
    selectedPeriodIds.value = ['all'];
  } else if (props.safeDatePeriods.length && selectedPeriodIds.value.length === props.safeDatePeriods.length) {
    selectedPeriodIds.value = ['all'];
  } else {
    selectedPeriodIds.value = [];
  }
};

// Handle individual period checkbox
const handlePeriodChange = () => {
  if (selectedPeriodIds.value.includes('all')) {
    selectedPeriodIds.value = selectedPeriodIds.value.filter(id => id !== 'all');
  }
  if (props.safeDatePeriods.length && selectedPeriodIds.value.length === props.safeDatePeriods.length) {
    selectedPeriodIds.value = ['all'];
  }
};

// Filter activities by selected periods, date, and round, then group by date and round
const filteredActivitySummary = computed(() => {
  const includeAllPeriods = selectedPeriodIds.value.length === 0 || selectedPeriodIds.value.includes('all');
  const periods = includeAllPeriods
    ? props.safeDatePeriods
    : props.safeDatePeriods.filter(period => selectedPeriodIds.value.includes(String(period.id)));

  // Group activities by date and round
  const summaryByDateAndRound: { [key: string]: { date: string, roundId: number, roundName: string, totalQuantity: number, totalValue: number } } = {};

  const activities = props.activities?.data || [];
  activities.forEach(activity => {
    const date = moment(activity.activity_date);
    const dateStr = activity.activity_date;
    const roundId = activity.parcel_type?.round_id ?? 0;
    const round = props.rounds.find(r => r.id === roundId);
    const roundName = round ? round.name : `Unknown Round (ID: ${roundId})`;

    // Find the period this activity belongs to
    const activityPeriod = props.safeDatePeriods.find(period =>
      date.isBetween(moment(period.start_date), moment(period.end_date), undefined, '[]')
    );

    // Skip if the activity's period is not in the selected periods
    if (!includeAllPeriods && (!activityPeriod || !periods.some(p => p.id === activityPeriod.id))) {
      return;
    }

    // Apply date filter
    if (selectedDate.value && dateStr !== selectedDate.value) {
      return;
    }

    // Apply round filter
    if (selectedRoundId.value !== null && roundId !== selectedRoundId.value) {
      return;
    }

    const key = `${dateStr}-${roundId}`;
    if (!summaryByDateAndRound[key]) {
      summaryByDateAndRound[key] = {
        date: dateStr,
        roundId: roundId,
        roundName,
        totalQuantity: 0,
        totalValue: 0,
      };
    }

    // Fixed: Correctly assign totalQuantity and totalValue
    summaryByDateAndRound[key].totalQuantity += activity.quantity; // Sum of quantities
    summaryByDateAndRound[key].totalValue += calculateTotalValue(activity); // Sum of monetary values
  });

  // Convert to array and sort
  let summaryArray = Object.values(summaryByDateAndRound).map(item => ({
    ...item,
    totalValue: `£${item.totalValue.toFixed(2)}`,
  }));

  // Apply sorting
  summaryArray.sort((a, b) => {
    let aValue, bValue;
    switch (sortKey.value) {
      case 'date':
        aValue = moment(a.date);
        bValue = moment(b.date);
        return sortOrder.value * aValue.diff(bValue);
      case 'roundName':
        aValue = a.roundName.toLowerCase();
        bValue = b.roundName.toLowerCase();
        return sortOrder.value * aValue.localeCompare(bValue);
      case 'totalQuantity':
        aValue = a.totalQuantity;
        bValue = b.totalQuantity;
        return sortOrder.value * (aValue - bValue);
      case 'totalValue':
        aValue = parseFloat(a.totalValue.replace('£', ''));
        bValue = parseFloat(b.totalValue.replace('£', ''));
        return sortOrder.value * (aValue - bValue);
      default:
        return 0;
    }
  });

  return summaryArray;
});

// Pagination logic
const totalRecords = computed(() => filteredActivitySummary.value.length);
const paginatedActivitySummary = computed(() => {
  const start = (currentPage.value - 1) * recordsPerPage.value;
  const end = start + recordsPerPage.value;
  return filteredActivitySummary.value.slice(start, end);
});

const totalPages = computed(() => Math.ceil(totalRecords.value / recordsPerPage.value));

// Reset current page when records per page changes
watch(recordsPerPage, () => {
  currentPage.value = 1; // Reset to first page when changing records per page
});

// Reset current page if filters reduce the number of records
watch(totalRecords, (newTotal) => {
  if ((currentPage.value - 1) * recordsPerPage.value >= newTotal) {
    currentPage.value = Math.max(1, Math.ceil(newTotal / recordsPerPage.value));
  }
});

function activity_date(info) {
  return moment(info).format('DD/MM/YYYY');
}

// Handle column sorting
const sortBy = (key: string) => {
  if (sortKey.value === key) {
    sortOrder.value *= -1; // Toggle sort order
  } else {
    sortKey.value = key;
    sortOrder.value = -1; // Default to descending
  }
  currentPage.value = 1; // Reset to first page when sorting
};

// Pagination navigation
const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
};
</script>

<template>
  <div>
    <!-- Filter Row -->
    <div class="mb-4 flex flex-wrap gap-4 items-center">
      <!-- Date Picker -->
      <div>
        <label class="block text-sm font-medium text-gray-100 mb-1">Date</label>
        <input
          v-model="selectedDate"
          type="date"
          class="border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
        />
      </div>
      <!-- Round Picker -->
      <div>
        <label class="block text-sm font-medium text-gray-100 mb-1">Round</label>
        <select
          v-model="selectedRoundId"
          class="border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
        >
          <option :value="null">All Rounds</option>
          <option v-for="round in rounds" :key="round.id" :value="round.id">
            {{ round.name }}
          </option>
        </select>
      </div>
      <!-- Period Filter Dropdown -->
      <div class="relative">
        <label class="block text-sm font-medium text-gray-100 mb-1">Periods</label>
        <button
          @click="showPeriodDropdown = !showPeriodDropdown"
          class="border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 w-64 text-left"
          :disabled="!safeDatePeriods.length"
        >
          {{ selectedPeriodIds.length === 0 || selectedPeriodIds.includes('all') ? 'All Periods' : `${selectedPeriodIds.length} Period(s) Selected` }}
          <span class="absolute right-2 top-1/2 transform -translate-y-1/2">▼</span>
        </button>
        <div
          v-if="showPeriodDropdown && safeDatePeriods.length"
          class="absolute z-10 mt-1 w-64 bg-gray-800 border rounded shadow-lg max-h-60 overflow-y-auto"
        >
          <label class="block p-2 hover:bg-gray-700">
            <input
              type="checkbox"
              value="all"
              v-model="selectedPeriodIds"
              @change="handleAllPeriodsChange"
              class="mr-2"
            />
            All Periods
          </label>
          <label v-for="period in safeDatePeriods" :key="period.id" class="block p-2 hover:bg-gray-700">
            <input
              type="checkbox"
              :value="String(period.id)"
              v-model="selectedPeriodIds"
              @change="handlePeriodChange"
              class="mr-2"
            />
            {{ period.name || 'Unknown Period' }}
          </label>
        </div>
        <div v-if="showPeriodDropdown && !safeDatePeriods.length" class="absolute z-10 mt-1 w-64 bg-gray-800 border rounded shadow-lg p-2 text-gray-400">
          No periods available
        </div>
      </div>
      <!-- Records Per Page -->
      <div>
        <label class="block text-sm font-medium text-gray-100 mb-1">Records Per Page</label>
        <select
          v-model="recordsPerPage"
          class="border rounded p-2 bg-gray-900 text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
        >
          <option value="10">10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
      <!-- Add Activity Button -->
      <div class="ml-auto">
        <button @click="$emit('openAddModal')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition duration-200">
          Add Activity
        </button>
      </div>
    </div>

    <!-- Activity Summary Table -->
    <div class="overflow-x-auto">
      <table class="w-full border bg-gray-900 rounded-lg">
        <thead>
          <tr class="bg-gray-800 text-gray-100">
            <th class="p-3 text-left cursor-pointer hover:bg-gray-700" @click="sortBy('date')">
              Date
              <span v-if="sortKey === 'date'" class="ml-1">{{ sortOrder === 1 ? '↑' : '↓' }}</span>
            </th>
            <th class="p-3 text-left cursor-pointer hover:bg-gray-700" @click="sortBy('roundName')">
              Round
              <span v-if="sortKey === 'roundName'" class="ml-1">{{ sortOrder === 1 ? '↑' : '↓' }}</span>
            </th>
            <th class="p-3 text-left cursor-pointer hover:bg-gray-700" @click="sortBy('totalQuantity')">
              Total Quantity
              <span v-if="sortKey === 'totalQuantity'" class="ml-1">{{ sortOrder === 1 ? '↑' : '↓' }}</span>
            </th>
            <th class="p-3 text-left cursor-pointer hover:bg-gray-700" @click="sortBy('totalValue')">
              Total Value (£)
              <span v-if="sortKey === 'totalValue'" class="ml-1">{{ sortOrder === 1 ? '↑' : '↓' }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="summary in paginatedActivitySummary" :key="`${summary.date}-${summary.roundId}`" class="border-t hover:bg-gray-800">
            <td class="p-3 cursor-pointer text-blue-400 hover:underline" @click="$emit('openDetailsModal', summary.date, summary.roundId)">
              {{ activity_date(summary.date) }}
            </td>
            <td class="p-3">{{ summary.roundName }}</td>
            <td class="p-3">{{ summary.totalQuantity }}</td>
            <td class="p-3">{{ summary.totalValue }}</td>
          </tr>
          <tr v-if="!filteredActivitySummary.length" class="border-t">
            <td colspan="4" class="p-3 text-gray-400 text-center">No activities for selected filters</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination Controls -->
    <div class="mt-4 flex justify-between items-center">
      <div class="text-gray-400">
        Showing {{ (currentPage - 1) * recordsPerPage + 1 }} to {{ Math.min(currentPage * recordsPerPage, totalRecords) }} of {{ totalRecords }} records
      </div>
      <nav class="flex space-x-2">
        <button
          @click="goToPage(currentPage - 1)"
          :disabled="currentPage === 1"
          class="px-3 py-1 rounded bg-gray-700 text-gray-200 disabled:bg-gray-500 disabled:cursor-not-allowed"
        >
          Previous
        </button>
        <button
          v-for="page in totalPages"
          :key="page"
          @click="goToPage(page)"
          class="px-3 py-1 rounded"
          :class="currentPage === page ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-200'"
        >
          {{ page }}
        </button>
        <button
          @click="goToPage(currentPage + 1)"
          :disabled="currentPage === totalPages"
          class="px-3 py-1 rounded bg-gray-700 text-gray-200 disabled:bg-gray-500 disabled:cursor-not-allowed"
        >
          Next
        </button>
      </nav>
    </div>
  </div>
</template>
