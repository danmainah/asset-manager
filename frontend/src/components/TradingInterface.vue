<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { profileAPI, orderAPI } from '../services/api'
import { initializePusher, subscribeToOrderMatched, disconnectPusher } from '../services/realtime'
import OrderForm from './OrderForm.vue'
import BalanceDisplay from './BalanceDisplay.vue'
import OrderHistory from './OrderHistory.vue'
import OrderbookDisplay from './OrderbookDisplay.vue'

const profile = ref(null)
const orders = ref([])
const orderbook = ref({ buy: [], sell: [] })
const selectedSymbol = ref('BTC')
const loading = ref(false)
const error = ref(null)
const refreshInterval = ref(null)
const notification = ref(null)

const symbols = ['BTC', 'ETH']

const filteredOrders = computed(() => {
  return orders.value.filter(order => order.symbol === selectedSymbol.value)
})

async function loadProfile() {
  try {
    loading.value = true
    error.value = null
    profile.value = await profileAPI.getProfile()
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

async function loadOrders() {
  try {
    const response = await orderAPI.getOrders()
    orders.value = response.orders || []
  } catch (err) {
    error.value = err.message
  }
}

async function loadOrderbook() {
  try {
    const response = await orderAPI.getOrderbook(selectedSymbol.value)
    orderbook.value = response
  } catch (err) {
    error.value = err.message
  }
}

async function handleOrderCreated() {
  await loadProfile()
  await loadOrders()
  await loadOrderbook()
}

async function handleOrderCancelled() {
  await loadProfile()
  await loadOrders()
  await loadOrderbook()
}

function handleOrderMatched(data) {
  notification.value = `Order matched! New balance: $${data.balance}`
  loadProfile()
  loadOrders()
  loadOrderbook()

  setTimeout(() => {
    notification.value = null
  }, 5000)
}

function startAutoRefresh() {
  refreshInterval.value = setInterval(async () => {
    await loadProfile()
    await loadOrderbook()
  }, 5000)
}

function stopAutoRefresh() {
  if (refreshInterval.value) {
    clearInterval(refreshInterval.value)
  }
}

onMounted(() => {
  loadProfile()
  loadOrders()
  loadOrderbook()
  startAutoRefresh()

  // Initialize real-time updates
  if (profile.value?.user?.id) {
    initializePusher(profile.value.user.id)
    subscribeToOrderMatched(handleOrderMatched)
  }
})

onUnmounted(() => {
  stopAutoRefresh()
  disconnectPusher()
})
</script>

<template>
  <div class="trading-interface min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 py-6">
        <h1 class="text-3xl font-bold text-gray-900">Asset Manager Trading</h1>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-8">
      <!-- Notification Alert -->
      <div v-if="notification" class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
        <p class="text-green-800">{{ notification }}</p>
      </div>

      <!-- Error Alert -->
      <div v-if="error" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
        <p class="text-red-800">{{ error }}</p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <p class="text-gray-600">Loading...</p>
      </div>

      <!-- Main Grid -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Balance and Order Form -->
        <div class="lg:col-span-1 space-y-6">
          <!-- Balance Display -->
          <BalanceDisplay v-if="profile" :profile="profile" />

          <!-- Order Form -->
          <OrderForm
            :symbol="selectedSymbol"
            :symbols="symbols"
            @order-created="handleOrderCreated"
            @symbol-changed="(symbol) => {
              selectedSymbol = symbol
              loadOrderbook()
            }"
          />
        </div>

        <!-- Right Column: Orderbook and Order History -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Orderbook -->
          <OrderbookDisplay
            :symbol="selectedSymbol"
            :orderbook="orderbook"
          />

          <!-- Order History -->
          <OrderHistory
            :orders="filteredOrders"
            @order-cancelled="handleOrderCancelled"
          />
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
.trading-interface {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}
</style>
