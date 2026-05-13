import { defineStore } from 'pinia';
import api from '../api';
import { createEcho, getEcho } from '../echo';

export const useOrderStore = defineStore('order', {
  state: () => ({
    currentOrder: null,
    activeRadiusKm: null,
    currentStepIndex: null,
    partnerLocation: null,
    expiresAt: null,
    channel: null,
  }),

  actions: {
    setOrder(order) {
      this.currentOrder = order;
      this.activeRadiusKm = order?.active_radius_km ?? null;
      this.currentStepIndex = order?.current_step_index ?? null;
      this.expiresAt = order?.expires_at ?? null;
    },

    async load(orderId) {
      const { data } = await api.get(`/customer/orders/${orderId}`);
      this.setOrder(data.data);
      return this.currentOrder;
    },

    async cancel(orderId) {
      const { data } = await api.delete(`/customer/orders/${orderId}/cancel`);
      this.setOrder(data.data);
      return this.currentOrder;
    },

    async increasePrice(orderId) {
      const { data } = await api.patch(`/customer/orders/${orderId}/increase-price`);
      this.setOrder(data.data);
      return this.currentOrder;
    },

    subscribe(orderId, token) {
      this.unsubscribe();
      const echo = getEcho() ?? createEcho(token);
      this.channel = echo.channel(`order.${orderId}`);
      this.channel.listen('.OrderRadiusExpanded', (e) => {
        this.activeRadiusKm = e.active_radius_km;
        this.currentStepIndex = e.current_step_index;
      });
      this.channel.listen('.OrderClaimed', () => this.load(orderId));
      this.channel.listen('.OrderCancelled', () => this.load(orderId));
      this.channel.listen('.OrderExpired', () => this.load(orderId));
      this.channel.listen('.OrderStatusChanged', () => this.load(orderId));
      this.channel.listen('.OrderPriceUpdated', (e) => {
        if (this.currentOrder) this.currentOrder.current_price = e.current_price;
      });
    },

    unsubscribe() {
      if (this.channel) {
        this.channel.stopListening('.OrderRadiusExpanded');
        this.channel.stopListening('.OrderClaimed');
        this.channel.stopListening('.OrderCancelled');
        this.channel.stopListening('.OrderExpired');
        this.channel.stopListening('.OrderStatusChanged');
        this.channel.stopListening('.OrderPriceUpdated');
        this.channel = null;
      }
    },

    reset() {
      this.unsubscribe();
      this.currentOrder = null;
      this.activeRadiusKm = null;
      this.currentStepIndex = null;
      this.partnerLocation = null;
      this.expiresAt = null;
    },
  },
});
