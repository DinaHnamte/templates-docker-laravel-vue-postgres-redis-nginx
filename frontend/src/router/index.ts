import { createRouter, createWebHistory } from "vue-router";
import HomeView from "@/views/HomeView.vue";

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [{ path: "/", name: "home", component: HomeView }],
});

// Redirect logged-in users away from guest-only routes (login/register)
router.beforeEach((to, _from, next) => {
  const token = localStorage.getItem("token");
  if (to.meta.guest && token) {
    next({ name: "home" });
  } else {
    next();
  }
});

export default router;
