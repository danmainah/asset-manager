<script setup>
defineProps({
  symbol: {
    type: String,
    required: true,
  },
  orderbook: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false
  }
})

function formatPrice(value) {
  if (!value) return '0.00'
  return parseFloat(value).toFixed(8)
}

function formatAmount(value) {
  if (!value) return '0.00'
  return parseFloat(value).toFixed(8)
}
</script>

<template>
  <div class="orderbook-display bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">Orderbook</h2>

    <div class="grid grid-cols-2 gap-6">
      <!-- Buy Orders -->
      <div>
        <h3 class="text-sm font-semibold text-green-600 uppercase mb-3">Buy Orders</h3>
        <div v-if="loading" class="flex justify-center py-4">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-green-600"></div>
        </div>
        <div v-else-if="orderbook.buy_orders && orderbook.buy_orders.length > 0" class="space-y-2">
          <div
            v-for="(order, index) in orderbook.buy_orders"
            :key="`buy-${index}`"
            class="flex justify-between items-center p-2 bg-green-50 rounded text-sm"
          >
            <div class="flex items-center gap-2">
              <span class="font-bold text-gray-700 w-10">{{ order.symbol }}</span>
              <span class="font-medium text-gray-900">{{ formatPrice(order.price) }}</span>
            </div>
            <span class="text-gray-600">{{ formatAmount(order.amount) }}</span>
          </div>
        </div>
        <div v-else class="text-gray-500 text-sm text-center py-4">
          No buy orders
        </div>
      </div>

      <!-- Sell Orders -->
      <div>
        <h3 class="text-sm font-semibold text-red-600 uppercase mb-3">Sell Orders</h3>
        <div v-if="loading" class="flex justify-center py-4">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-red-600"></div>
        </div>
        <div v-else-if="orderbook.sell_orders && orderbook.sell_orders.length > 0" class="space-y-2">
          <div
            v-for="(order, index) in orderbook.sell_orders"
            :key="`sell-${index}`"
            class="flex justify-between items-center p-2 bg-red-50 rounded text-sm"
          >
            <div class="flex items-center gap-2">
              <span class="font-bold text-gray-700 w-10">{{ order.symbol }}</span>
              <span class="font-medium text-gray-900">{{ formatPrice(order.price) }}</span>
            </div>
            <span class="text-gray-600">{{ formatAmount(order.amount) }}</span>
          </div>
        </div>
        <div v-else class="text-gray-500 text-sm text-center py-4">
          No sell orders
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.orderbook-display {
  transition: all 0.3s ease;
}
</style>
