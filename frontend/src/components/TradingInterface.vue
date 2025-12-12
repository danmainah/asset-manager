<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { profileAPI, orderAPI } from '../services/api'
import { initializePusher, subscribeToOrderMatched, disconnectPusher } from '../services/realtime'
import OrderForm from './OrderForm.vue'
import BalanceDisplay from './BalanceDisplay.vue'
import OrderHistory from './OrderHistory.vue'
import OrderbookDisplay from './OrderbookDisplay.vue'

const profile = ref(null)
const orders = ref([])
const orderbook = ref({ buy_orders: [], sell_orders: [] })
const selectedSymbol = ref('BTC')
const initialLoading = ref(true) // Only show loading spinner on first load
const orderbookLoading = ref(false)
const error = ref(null)
const refreshInterval = ref(null)
const notification = ref(null)
const pusherInitialized = ref(false)

const symbols = ['BTC', 'ETH']

async function loadProfile(showLoading = false) {
  try {
    if (showLoading) {
      initialLoading.value = true
    }
    error.value = null
    profile.value = await profileAPI.getProfile()
  } catch (err) {
    error.value = err.message
  } finally {
    if (showLoading) {
      initialLoading.value = false
    }
  }
}

async function loadOrders() {
  try {
    error.value = null
    const response = await orderAPI.getOrders()
    orders.value = response.orders || []
  } catch (err) {
    error.value = err.message
  }
}

async function loadOrderbook(background = false) {
  try {
    if (!background) {
      orderbookLoading.value = true
    }
    error.value = null
    // Pass nothing to fetch all orders
    const response = await orderAPI.getOrderbook()
    orderbook.value = response
  } catch (err) {
    error.value = err.message
  } finally {
    if (!background) {
      orderbookLoading.value = false
    }
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
  // Show loading spinner only on initial load
  await loadProfile(true)
  await loadOrders()
  await loadOrderbook()

  // Initialize real-time updates after profile is loaded
  initializeRealtime()
})

onUnmounted(() => {
  disconnectPusher()
  pusherInitialized.value = false
})
</script>

<template>
  <div class="trading-interface">
    <!-- Main Content -->
    <main class="content-wrapper">
      <!-- Notification Alert -->
      <div v-if="notification" class="notification-alert">
        <div class="notification-icon">✓</div>
        <p>{{ notification }}</p>
      </div>

      <!-- Error Alert -->
      <div v-if="error" class="error-alert">
        <div class="error-icon">⚠️</div>
        <p>{{ error }}</p>
      </div>

      <!-- Loading State -->
      <div v-if="initialLoading" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Loading...</p>
      </div>

      <!-- Main Grid -->
      <div v-else class="trading-grid">
        <!-- Left Column: Balance and Order Form -->
        <div class="left-column">
          <!-- Balance Display -->
          <BalanceDisplay v-if="profile" :profile="profile" />

          <!-- Order Form -->
          <OrderForm
            :symbol="selectedSymbol"
            :symbols="symbols"
            @order-created="handleOrderCreated"
            @symbol-changed="(symbol) => {
              selectedSymbol = symbol
              // No need to reload orderbook on symbol change as we now show all orders
            }"
          />
        </div>

        <!-- Right Column: Orderbook and Order History -->
        <div class="right-column">
          <!-- Orderbook -->
          <OrderbookDisplay
            :symbol="selectedSymbol"
            :orderbook="orderbook"
            :loading="orderbookLoading"
          />

          <!-- Order History -->
          <OrderHistory
            :orders="orders"
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
  min-height: 100vh;
}

.content-wrapper {
  max-width: 1400px;
  margin: 0 auto;
  padding: 32px 24px;
}

.notification-alert {
  margin-bottom: 24px;
  padding: 16px 20px;
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.15) 100%);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(16, 185, 129, 0.3);
  border-radius: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  color: white;
  font-weight: 500;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
  animation: slideDown 0.4s ease-out;
}

.notification-icon {
  width: 32px;
  height: 32px;
  background: rgba(16, 185, 129, 0.3);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}

.error-alert {
  margin-bottom: 24px;
  padding: 16px 20px;
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.15) 100%);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  color: white;
  font-weight: 500;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
  animation: shake 0.5s ease-in-out;
}

.error-icon {
  width: 32px;
  height: 32px;
  background: rgba(239, 68, 68, 0.3);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-8px); }
  75% { transform: translateX(8px); }
}

.loading-state {
  text-align: center;
  padding: 80px 20px;
  color: white;
}

.loading-spinner {
  width: 48px;
  height: 48px;
  border: 4px solid rgba(255, 255, 255, 0.2);
  border-top-color: white;
  border-radius: 50%;
  margin: 0 auto 16px;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.loading-state p {
  font-size: 16px;
  font-weight: 500;
  margin: 0;
}

.trading-grid {
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 24px;
}

.left-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.right-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

@media (max-width: 1024px) {
  .trading-grid {
    grid-template-columns: 1fr;
  }
  
  .content-wrapper {
    padding: 20px 16px;
  }
}
</style>

