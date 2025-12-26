<template>
  <div class="w-full h-full flex flex-col">
    <nav class="flex flex-col w-full">
      <div
        class="text-white flex flex-row p-4 w-full justify-between items-center">
        <div class="">HIVEFORM</div>
        <ul class="flex flex-row items-center justify-center gap-4">
          <li
            v-for="link in links"
            class="hover:text-amber-400 transition-colors duration-100">
            <RouterLink :to="link.link">{{ link.name }}</RouterLink>
          </li>
        </ul>
        <div class="hover:text-amber-400 transition-colors duration-100">
          LOGIN
        </div>
        <div>
          <input type="search" class="p-2 px-6 caret-amber-400 focus" />
        </div>
      </div>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from "vue-router";

const links = [
  { name: "Shop", link: "/" },
  { name: "Categories", link: "/" },
  { name: "About Us", link: "/" },
  { name: "Contact Us", link: "/" },
];
</script>
