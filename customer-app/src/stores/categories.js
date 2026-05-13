import { defineStore } from 'pinia';
import api from '../api';

export const useCategoriesStore = defineStore('categories', {
  state: () => ({ items: [] }),

  actions: {
    async load() {
      if (this.items.length) return;
      const { data } = await api.get('/service-categories');
      this.items = data.data;
    },
    bySlug(slug) {
      return this.items.find((c) => c.slug === slug) ?? null;
    },
  },
});
