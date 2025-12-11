<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
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
const pusherInitialized = ref(false)

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
  // Update profile with new balance and assets from the event
  if (data.user_balance !== undefined && profile.value) {
    profile.value.balance = data.user_balance
  }

  if (data.user_assets && profile.value) {
    profile.value.assets = data.user_assets
  }

  // Show notification with trade details
  const tradeInfo = data.trade
  notification.value = `Order matched! ${tradeInfo.symbol} at $${parseFloat(tradeInfo.price).toFixed(8)}`

  // Reload orders and orderbook to reflect the matched order
  loadOrders()
  loadOrderbook()

  // Clear notification after 5 seconds
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

function initializeRealtime() {
  if (profile.value?.user?.id && !pusherInitialized.value) {
    try {
      initializePusher(profile.value.user.id)
      subscribeToOrderMatched(handleOrderMatched)
      pusherInitialized.value = true
      console.log('Real-time updates initialized')
    } catch (err) {
      console.error('Failed to initialize real-time updates:', err)
    }
  }
}

// Watch for profile changes to initialize Pusher when user data is available
watch(
  () => profile.value?.user?.id,
  (userId) => {
    if (userId && !pusherInitialized.value) {
      initializeRealtime()
    }
  }
)

onMounted(async () => {
  await loadProfile()
  await loadOrders()
  await loadOrderbook()
  startAutoRefresh()

  // Initialize real-time updates after profile is loaded
  initializeRealtime()
})

onUnmounted(() => {
  stopAutoRefresh()
  disconnectPusher()
  pusherInitialized.value = false
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
