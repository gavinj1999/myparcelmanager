<!-- resources\js\components\ImageUpload.vue -->
<template>
    <div class="image-upload">
      <h3>Upload Image for Activity</h3>
      <div class="form-group">
        <label for="activityDate">Select Activity Date</label>
        <input
          type="date"
          id="activityDate"
          v-model="activityDate"
          class="form-control"
          required
        />
      </div>
      <div class="form-group">
        <label for="imageUpload">Choose Image</label>
        <input
          type="file"
          id="imageUpload"
          accept="image/*"
          ref="fileInput"
          @change="handleFileChange"
          class="form-control"
          required
        />
      </div>
      <div v-if="previewUrl" class="image-preview">
        <img :src="previewUrl" alt="Image Preview" style="max-width: 200px;" />
      </div>
      <button
        @click="uploadImage"
        :disabled="!file || !activityDate || isUploading"
        class="btn btn-primary"
      >
        {{ isUploading ? 'Uploading...' : 'Upload' }}
      </button>
      <p v-if="error" class="text-danger">{{ error }}</p>
      <p v-if="success" class="text-success">{{ success }}</p>
    </div>
  </template>

  <script>
  import { ref } from 'vue';
  import axios from 'axios';

  export default {
    name: 'ImageUpload',
    setup() {
      // Reactive state
      const activityDate = ref('');
      const file = ref(null);
      const previewUrl = ref(null);
      const isUploading = ref(false);
      const error = ref('');
      const success = ref('');
      const fileInput = ref(null);

      // Handle file selection and preview
      const handleFileChange = (event) => {
        const selectedFile = event.target.files[0];
        if (selectedFile && selectedFile.type.startsWith('image/')) {
          file.value = selectedFile;
          previewUrl.value = URL.createObjectURL(selectedFile);
          error.value = '';
        } else {
          error.value = 'Please select a valid image file.';
          file.value = null;
          previewUrl.value = null;
        }
      };

      // Handle image upload
      const uploadImage = async () => {
        if (!file.value || !activityDate.value) {
          error.value = 'Please select an image and activity date.';
          return;
        }

        isUploading.value = true;
        error.value = '';
        success.value = '';

        const formData = new FormData();
        formData.append('image', file.value);
        formData.append('activity_date', activityDate.value);

        try {
          const response = await axios.post('/api/activity-images', formData, {
            headers: {
              'Content-Type': 'multipart/form-data',
            },
          });
          success.value = 'Image uploaded successfully!';
          resetForm();
        } catch (err) {
          console.error('Upload error:', err.response || err); // Debug log
          error.value = err.response?.data?.message || 'Failed to upload image.';
        } finally {
          isUploading.value = false;
        }
      };

      // Reset form after successful upload
      const resetForm = () => {
        file.value = null;
        activityDate.value = '';
        previewUrl.value = null;
        if (fileInput.value) {
          fileInput.value.value = null; // Clear file input
        }
      };

      return {
        activityDate,
        file,
        previewUrl,
        isUploading,
        error,
        success,
        fileInput,
        handleFileChange,
        uploadImage,
      };
    },
  };
  </script>

  <style scoped>
  .image-upload {
    max-width: 500px;
    margin: 20px auto;
  }
  .form-group {
    margin-bottom: 15px;
  }
  .form-control {
    width: 100%;
    padding: 8px;
  }
  .image-preview {
    margin: 15px 0;
  }
  .btn-primary {
    padding: 10px 20px;
  }
  .text-danger {
    color: red;
  }
  .text-success {
    color: green;
  }
  </style>
