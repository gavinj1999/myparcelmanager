<!-- resources\js\pages\EndOFDay\Index.vue -->
<template>
    <div class="end-of-day">
      <h1>End of Day Report</h1>
      <ImageUpload />
    </div>
  </template>

  <script>
  import ImageUpload from '@/components/ImageUpload.vue';

  export default {
    name: 'EndOfDayIndex',
    components: {
      ImageUpload,
    },
  };
  </script>

  <style scoped>
  .end-of-day {
    padding: 20px;
  }
  </style>
