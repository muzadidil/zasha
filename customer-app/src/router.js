import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';

const routes = [
  { path: '/', redirect: '/orders' },
  { path: '/login', name: 'login', component: () => import('./views/Login.vue') },
  { path: '/register', name: 'register', component: () => import('./views/Register.vue') },
  { path: '/orders', name: 'orders.index', component: () => import('./views/OrderList.vue'), meta: { auth: true } },
  { path: '/orders/new', name: 'orders.create', component: () => import('./views/OrderCreate.vue'), meta: { auth: true } },
  { path: '/orders/:id', name: 'orders.show', component: () => import('./views/OrderDetail.vue'), meta: { auth: true } },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to) => {
  const auth = useAuthStore();
  if (to.meta.auth && !auth.token) return { name: 'login' };
  if ((to.name === 'login' || to.name === 'register') && auth.token) return { name: 'orders.index' };
  return true;
});

export default router;
