<script setup lang="ts">
import { onMounted } from "vue";

onMounted(() => {
  console.log("App mounted");
});
</script>

<template>
  <router-view class="flex min-h-dvh bg-black" />
</template>
