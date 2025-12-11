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
    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ symbol }} Orderbook</h2>

    <div class="grid grid-cols-2 gap-6">
      <!-- Buy Orders -->
      <div>
        <h3 class="text-sm font-semibold text-green-600 uppercase mb-3">Buy Orders</h3>
        <div v-if="orderbook.buy && orderbook.buy.length > 0" class="space-y-2">
          <div
            v-for="(order, index) in orderbook.buy"
            :key="`buy-${index}`"
            class="flex justify-between items-center p-2 bg-green-50 rounded text-sm"
          >
            <span class="font-medium text-gray-900">{{ formatPrice(order.price) }}</span>
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
        <div v-if="orderbook.sell && orderbook.sell.length > 0" class="space-y-2">
          <div
            v-for="(order, index) in orderbook.sell"
            :key="`sell-${index}`"
            class="flex justify-between items-center p-2 bg-red-50 rounded text-sm"
          >
            <span class="font-medium text-gray-900">{{ formatPrice(order.price) }}</span>
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
