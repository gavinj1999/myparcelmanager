<!-- resources/js/pages/Reports/Index.vue -->
<script lang="ts" setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import moment from 'moment';
import Chart from 'chart.js/auto';
import ChartDataLabels from 'chartjs-plugin-datalabels';

// Register ChartDataLabels plugin with Chart.js
Chart.register(ChartDataLabels);

const props = defineProps({
  activities: { type: Object, default: () => ({ data: [] }) },
  rounds: { type: Array, default: () => [] },
  parcelTypes: { type: Array, default: () => [] },
  datePeriods: { type: Array, default: () => [] },
  date_periods: { type: Array, default: () => [] },
});

// Safe date periods
const safeDatePeriods = computed(() => {
  const periods = props.datePeriods?.length ? props.datePeriods : props.date_periods;
  if (!periods || !Array.isArray(periods)) return [];
  return periods.filter(period => period && typeof period === 'object' && 'id' in period);
});

// Determine the current date period
const currentDatePeriod = computed(() => {
  const today = moment();
  return safeDatePeriods.value.find(period =>
    today.isBetween(moment(period.start_date), moment(period.end_date), undefined, '[]')
  ) || null;
});

// Calculate earnings by round for the current period
const earningsByRound = computed(() => {
  if (!currentDatePeriod.value) return [];

  const activitiesInPeriod = (props.activities?.data || []).filter(activity => {
    const activityDate = moment(activity.activity_date);
    return activityDate.isBetween(
      moment(currentDatePeriod.value.start_date),
      moment(currentDatePeriod.value.end_date),
      undefined,
      '[]'
    );
  });

  const earningsMap: { [key: number]: { roundName: string, totalEarnings: number } } = {};
  let totalEarnings = 0;

  activitiesInPeriod.forEach(activity => {
    const roundId = activity.parcel_type?.round_id ?? 0;
    const round = props.rounds.find(r => r.id === roundId);
    const roundName = round ? round.name : `Unknown Round (ID: ${roundId})`;

    const earnings = activity.quantity * (activity.parcel_type?.rate || 0);
    if (!earningsMap[roundId]) {
      earningsMap[roundId] = { roundName, totalEarnings: 0 };
    }
    earningsMap[roundId].totalEarnings += earnings;
    totalEarnings += earnings;
  });

  const earningsArray = Object.values(earningsMap).map(item => ({
    ...item,
    totalEarnings: `£${item.totalEarnings.toFixed(2)}`,
  }));

  return { earnings: earningsArray, total: `£${totalEarnings.toFixed(2)}` };
});

// Data for the parcel bar chart (consolidated by parcel_type.name, only rates > 0, sorted by quantity)
const parcelBarData = computed(() => {
  if (!currentDatePeriod.value) return { labels: [], datasets: [] };

  const activitiesInPeriod = (props.activities?.data || []).filter(activity => {
    const activityDate = moment(activity.activity_date);
    return activityDate.isBetween(
      moment(currentDatePeriod.value.start_date),
      moment(currentDatePeriod.value.end_date),
      undefined,
      '[]'
    );
  });

  // Consolidate quantities by parcel_type.name, only for parcel types with rate > 0
  const parcelTypeQuantities: { [key: string]: number } = {};
  activitiesInPeriod.forEach(activity => {
    const parcelTypeName = activity.parcel_type?.name ?? 'Unknown';
    const rate = activity.parcel_type?.rate ?? 0;

    // Only include parcel types with a rate greater than 0
    if (rate > 0) {
      parcelTypeQuantities[parcelTypeName] = (parcelTypeQuantities[parcelTypeName] || 0) + activity.quantity;
    }
  });

  // Sort parcel types by quantity in descending order
  const sortedEntries = Object.entries(parcelTypeQuantities).sort((a, b) => b[1] - a[1]);
  const labels = sortedEntries.map(([name]) => name);
  const data = sortedEntries.map(([, quantity]) => quantity);

  return {
    labels,
    datasets: [
      {
        label: 'Quantity',
        data,
        backgroundColor: [
          '#FF6384',
          '#36A2EB',
          '#FFCE56',
          '#4BC0C0',
          '#9966FF',
          '#FF9F40',
        ],
        borderColor: [
          '#FF6384',
          '#36A2EB',
          '#FFCE56',
          '#4BC0C0',
          '#9966FF',
          '#FF9F40',
        ],
        borderWidth: 1,
      },
    ],
  };
});

// Data for the pay bar chart (side by side by round, all dates in period, exclude Sundays unless activity)
const payBarChartData = computed(() => {
  if (!currentDatePeriod.value) return { labels: [], datasets: [] };

  const activitiesInPeriod = (props.activities?.data || []).filter(activity => {
    const activityDate = moment(activity.activity_date);
    return activityDate.isBetween(
      moment(currentDatePeriod.value.start_date),
      moment(currentDatePeriod.value.end_date),
      undefined,
      '[]'
    );
  });

  // Collect all dates with activity
  const datesWithActivity = new Set<string>();
  activitiesInPeriod.forEach(activity => {
    const dateStr = moment(activity.activity_date).format('DD/MM/YYYY');
    datesWithActivity.add(dateStr);
  });

  // Generate all dates in the period, excluding Sundays unless they have activity
  const startDate = moment(currentDatePeriod.value.start_date);
  const endDate = moment(currentDatePeriod.value.end_date);
  const labels: string[] = [];
  let currentDate = startDate.clone();

  while (currentDate.isSameOrBefore(endDate, 'day')) {
    const dateStr = currentDate.format('DD/MM/YYYY');
    const isSunday = currentDate.day() === 0; // 0 = Sunday in moment.js

    // Include the date if it's not a Sunday, or if it's a Sunday with activity
    if (!isSunday || datesWithActivity.has(dateStr)) {
      labels.push(dateStr);
    }
    currentDate.add(1, 'day');
  }

  // Collect earnings by round and date
  const earningsByRoundAndDate: { [key: number]: { [key: string]: number } } = {};
  activitiesInPeriod.forEach(activity => {
    const roundId = activity.parcel_type?.round_id ?? 0;
    const dateStr = moment(activity.activity_date).format('DD/MM/YYYY');
    const earnings = activity.quantity * (activity.parcel_type?.rate || 0);

    if (!earningsByRoundAndDate[roundId]) {
      earningsByRoundAndDate[roundId] = {};
    }
    earningsByRoundAndDate[roundId][dateStr] = (earningsByRoundAndDate[roundId][dateStr] || 0) + earnings;
  });

  // Create datasets for each round (side by side)
  const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
  const datasets = Object.keys(earningsByRoundAndDate).map((roundId, index) => {
    const round = props.rounds.find(r => r.id === Number(roundId));
    const label = round ? round.name : `Unknown Round (ID: ${roundId})`;
    const data = labels.map(date => earningsByRoundAndDate[roundId][date] || 0);

    return {
      label,
      data,
      backgroundColor: colors[index % colors.length],
      borderColor: colors[index % colors.length],
      borderWidth: 1,
      responsive: true // Reduced bar thickness to allow more space
    };
  });

  return {
    labels,
    datasets,
  };
});

// Chart references
const parcelBarChart = ref<Chart | null>(null);
const payBarChart = ref<Chart | null>(null);

// Initialize charts
onMounted(() => {
  // Parcel Bar Chart
  const parcelBarCtx = document.getElementById('parcel-bar-chart') as HTMLCanvasElement;
  if (parcelBarCtx) {
    parcelBarChart.value = new Chart(parcelBarCtx, {
      type: 'bar',
      data: parcelBarData.value,
      options: {
        indexAxis: 'y',
        responsive: true,
        scales: {
          x: {
            title: {
              display: true,
              text: 'Quantity',
              color: '#e5e7eb',
            },
            ticks: {
              color: '#e5e7eb',
            },
            beginAtZero: true,
          },
          y: {
            title: {
              display: true,
              text: 'Parcel Type',
              color: '#e5e7eb',
            },
            ticks: {
              color: '#e5e7eb',
            },
          },
        },
        plugins: {
          legend: {
            display: false,
          },
          title: {
            display: true,
            text: 'Parcel Types in Current Period (Rate > 0)',
            color: '#e5e7eb',
          },
          tooltip: {
            enabled: true,
            callbacks: {
              label: function (context) {
                const label = context.label || '';
                const value = context.parsed.x || 0;
                return `${label}: ${value}`;
              },
            },
          },
        },
      },
    });
  }

  // Pay Bar Chart (horizontal bars, switched x-y axes)
  const payBarCtx = document.getElementById('pay-bar-chart') as HTMLCanvasElement;
  if (payBarCtx) {
    payBarChart.value = new Chart(payBarCtx, {
      type: 'bar',
      data: payBarChartData.value,
      options: {
        indexAxis: 'y', // Horizontal bars
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: {
            title: {
              display: true,
              text: 'Total Earnings (£)',
              color: '#e5e7eb',
            },
            ticks: {
              color: '#e5e7eb',
            },
            beginAtZero: true,
          },
          y: {
            title: {
              display: true,
              text: 'Date',
              color: '#e5e7eb',
            },
            ticks: {
              color: '#e5e7eb',
              callback: function (value) {
                return payBarChartData.value.labels[value] || '';
              },
            },
            grid: {
              display: false, // Optional: Remove grid lines for cleaner look
            },
          },
        },
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: '#e5e7eb',
            },
          },
          title: {
            display: true,
            text: 'Daily Earnings by Round in Current Period',
            color: '#e5e7eb',
          },
          tooltip: {
            enabled: true,
            callbacks: {
              label: function (context) {
                const label = context.dataset.label || '';
                const value = context.parsed.x || 0;
                return `${label}: £${value.toFixed(2)}`;
              },
            },
          },
          datalabels: {
            display: true,
            color: '#e5e7eb',
            align: 'center',
            anchor: 'center',
            rotation: 0, // Remove rotation (set to 0 degrees)
            formatter: (value, context) => {
              return value > 0 ? `£${value.toFixed(2)}` : '';
            },
            font: {
              size: 12,
              weight: 'bold',
            },
          },
        },
        // Adjust spacing between dates (y-axis categories)
        layout: {
          padding: {
            left: 10,
            right: 10,
            top: 10,
            bottom: 10,
          },
        },
        // Control bar spacing
        scales: {
          y: {
            // Existing y-axis config
            categoryPercentage: 0.6, // Reduce the overall space taken by each category (date)
            barPercentage: 0.4, // Reduce the space taken by bars within each category
          },
        },
      },
    });
  }
});

// Update charts when data changes
watch(parcelBarData, (newData) => {
  if (parcelBarChart.value) {
    parcelBarChart.value.data = newData;
    parcelBarChart.value.update();
  }
});

watch(payBarChartData, (newData) => {
  if (payBarChart.value) {
    payBarChart.value.data = newData;
    payBarChart.value.update();
  }
});

// Cleanup on unmount
onUnmounted(() => {
  if (parcelBarChart.value) {
    parcelBarChart.value.destroy();
  }
  if (payBarChart.value) {
    payBarChart.value.destroy();
  }
});
</script>

<template>
  <AppLayout>
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
      <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        <!-- Current Period (1/3 width) -->
        <div
          id="current-period"
          class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-4 text-gray-100 md:col-span-1"
        >
          <h2 class="text-lg font-semibold mb-2">Current Period</h2>
          <p v-if="currentDatePeriod">
            {{ currentDatePeriod.name || `${moment(currentDatePeriod.start_date).format('DD/MM/YYYY')} - ${moment(currentDatePeriod.end_date).format('DD/MM/YYYY')}` }}
          </p>
          <p v-else class="text-gray-400">No current period found</p>
          <!-- Earnings in Current Period -->
          <div class="mt-4">
            <h3 class="text-md font-semibold mb-2">Earnings in Current Period</h3>
            <div v-if="earningsByRound.earnings.length">
              <div v-for="earning in earningsByRound.earnings" :key="earning.roundName" class="mb-1">
                <span>{{ earning.roundName }}: </span>
                <span>{{ earning.totalEarnings }}</span>
              </div>
              <div class="mt-2 font-semibold">
                Total: {{ earningsByRound.total }}
              </div>
            </div>
            <p v-else class="text-gray-400">No earnings in current period</p>
          </div>
        </div>

        <!-- Parcel Bar Chart (2/3 width) -->
        <div
          id="parcel-bar"
          class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-4 md:col-span-2"
        >
          <canvas id="parcel-bar-chart"></canvas>
        </div>
      </div>

      <!-- Pay Bar Chart (full width) -->
      <div
        id="pay-barchart"
        class="relative w-full h-[800px] rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-4"
      >
        <canvas id="pay-bar-chart"></canvas>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
#pay-barchart canvas {
  width: 100% !important;
  height: 100% !important;
}
</style>
