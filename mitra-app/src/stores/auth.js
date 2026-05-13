import { defineStore } from 'pinia';
import api from '../api';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('zasha_mitra_token'),
  }),

  actions: {
    setToken(token) {
      this.token = token;
      if (token) localStorage.setItem('zasha_mitra_token', token);
      else localStorage.removeItem('zasha_mitra_token');
    },

    async login(phone, password) {
      const { data } = await api.post('/auth/login', { phone, password, device_name: 'mitra-web' });
      this.setToken(data.data.token);
      this.user = data.data.user;
    },

    async register(payload) {
      await api.post('/auth/register', { ...payload, role: 'partner' });
      await this.login(payload.phone, payload.password);
    },

    async fetchMe() {
      if (!this.token) return;
      try {
        const { data } = await api.get('/auth/me');
        this.user = data.data;
      } catch {
        this.setToken(null);
        this.user = null;
      }
    },

    async logout() {
      try { await api.post('/auth/logout'); } catch (_) {}
      this.setToken(null);
      this.user = null;
    },
  },
});
