<script setup>
import { ref } from 'vue'
import { orderAPI } from '../services/api'

const props = defineProps({
  orders: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits(['order-cancelled'])

const loading = ref(false)
const error = ref(null)

const statusColors = {
  open: 'bg-blue-100 text-blue-800',
  filled: 'bg-green-100 text-green-800',
  cancelled: 'bg-gray-100 text-gray-800',
}

const sideColors = {
  buy: 'text-green-600',
  sell: 'text-red-600',
}

function formatPrice(value) {
  if (!value) return '0.00'
  return parseFloat(value).toFixed(8)
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleString()
}

async function cancelOrder(orderId) {
  try {
    loading.value = true
    error.value = null

    await orderAPI.cancelOrder(orderId)
    emit('order-cancelled')
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="order-history bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">Order History</h2>

    <!-- Error Alert -->
    <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-red-800 text-sm">
      {{ error }}
    </div>

    <!-- Orders Table -->
    <div v-if="orders.length > 0" class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="border-b border-gray-200">
          <tr>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Symbol</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Type</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Price</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Amount</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Status</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Created</th>
            <th class="text-left py-3 px-4 font-semibold text-gray-700">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id" class="border-b border-gray-100 hover:bg-gray-50">
            <td class="py-3 px-4 font-medium">{{ order.symbol }}</td>
            <td class="py-3 px-4">
              <span :class="['font-medium', sideColors[order.side]]">
                {{ order.side.toUpperCase() }}
              </span>
            </td>
            <td class="py-3 px-4">{{ formatPrice(order.price) }}</td>
            <td class="py-3 px-4">{{ formatPrice(order.amount) }}</td>
            <td class="py-3 px-4">
              <span :class="['px-2 py-1 rounded text-xs font-medium', statusColors[order.status]]">
                {{ order.status.charAt(0).toUpperCase() + order.status.slice(1) }}
              </span>
            </td>
            <td class="py-3 px-4 text-gray-600">{{ formatDate(order.created_at) }}</td>
            <td class="py-3 px-4">
              <button
                v-if="order.status === 'open'"
                @click="cancelOrder(order.id)"
                :disabled="loading"
                class="text-red-600 hover:text-red-800 font-medium disabled:text-gray-400"
              >
                Cancel
              </button>
              <span v-else class="text-gray-400">-</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-8 text-gray-500">
      <p>No orders yet</p>
    </div>
  </div>
</template>

<style scoped>
.order-history {
  transition: all 0.3s ease;
}
</style>
