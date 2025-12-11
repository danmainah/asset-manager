<script setup>
import { ref } from 'vue'
import { orderAPI } from '../services/api'

const props = defineProps({
  symbol: {
    type: String,
    required: true,
  },
  symbols: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits(['order-created', 'symbol-changed'])

const form = ref({
  symbol: props.symbol,
  side: 'buy',
  price: '',
  amount: '',
})

const loading = ref(false)
const error = ref(null)
const success = ref(null)

const sides = ['buy', 'sell']

async function submitOrder() {
  try {
    loading.value = true
    error.value = null
    success.value = null

    // Validate form
    if (!form.value.price || !form.value.amount) {
      error.value = 'Please fill in all fields'
      return
    }

    const price = parseFloat(form.value.price)
    const amount = parseFloat(form.value.amount)

    if (price <= 0 || amount <= 0) {
      error.value = 'Price and amount must be greater than 0'
      return
    }

    // Submit order
    await orderAPI.createOrder(
      form.value.symbol,
      form.value.side,
      price.toString(),
      amount.toString()
    )

    success.value = 'Order created successfully!'
    form.value.price = ''
    form.value.amount = ''

    emit('order-created')

    // Clear success message after 3 seconds
    setTimeout(() => {
      success.value = null
    }, 3000)
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

function handleSymbolChange(newSymbol) {
  form.value.symbol = newSymbol
  emit('symbol-changed', newSymbol)
}
</script>

<template>
  <div class="order-form bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">Place Order</h2>

    <!-- Success Alert -->
    <div v-if="success" class="mb-4 p-3 bg-green-50 border border-green-200 rounded text-green-800 text-sm">
      {{ success }}
    </div>

    <!-- Error Alert -->
    <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-red-800 text-sm">
      {{ error }}
    </div>

    <form @submit.prevent="submitOrder" class="space-y-4">
      <!-- Symbol Selection -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Symbol
        </label>
        <select
          v-model="form.symbol"
          @change="handleSymbolChange(form.symbol)"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option v-for="sym in symbols" :key="sym" :value="sym">
            {{ sym }}
          </option>
        </select>
      </div>

      <!-- Side Selection -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Order Type
        </label>
        <div class="flex gap-2">
          <button
            v-for="s in sides"
            :key="s"
            type="button"
            @click="form.side = s"
            :class="[
              'flex-1 py-2 px-3 rounded-md font-medium transition-colors',
              form.side === s
                ? s === 'buy'
                  ? 'bg-green-500 text-white'
                  : 'bg-red-500 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            ]"
          >
            {{ s.charAt(0).toUpperCase() + s.slice(1) }}
          </button>
        </div>
      </div>

      <!-- Price Input -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Price (USD)
        </label>
        <input
          v-model="form.price"
          type="number"
          step="0.00000001"
          min="0"
          placeholder="0.00"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <!-- Amount Input -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Amount
        </label>
        <input
          v-model="form.amount"
          type="number"
          step="0.00000001"
          min="0"
          placeholder="0.00"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <!-- Submit Button -->
      <button
        type="submit"
        :disabled="loading"
        :class="[
          'w-full py-2 px-4 rounded-md font-medium transition-colors',
          loading
            ? 'bg-gray-300 text-gray-600 cursor-not-allowed'
            : 'bg-blue-600 text-white hover:bg-blue-700'
        ]"
      >
        {{ loading ? 'Creating Order...' : 'Create Order' }}
      </button>
    </form>
  </div>
</template>

<style scoped>
.order-form {
  transition: all 0.3s ease;
}
</style>
