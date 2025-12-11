<script setup>
defineProps({
  profile: {
    type: Object,
    required: true,
  },
})

function formatBalance(value) {
  if (!value) return '0.00'
  return parseFloat(value).toFixed(2)
}

function formatAsset(value) {
  if (!value) return '0.00000000'
  return parseFloat(value).toFixed(8)
}
</script>

<template>
  <div class="balance-display bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">Account Balance</h2>

    <!-- USD Balance -->
    <div class="mb-6 pb-6 border-b border-gray-200">
      <div class="flex justify-between items-center mb-2">
        <span class="text-gray-600">USD Balance</span>
        <span class="text-2xl font-bold text-green-600">
          ${{ formatBalance(profile.balance) }}
        </span>
      </div>
    </div>

    <!-- Assets -->
    <div class="space-y-4">
      <h3 class="text-sm font-semibold text-gray-700 uppercase">Cryptocurrency Holdings</h3>
      <div v-if="profile.assets && profile.assets.length > 0" class="space-y-3">
        <div v-for="asset in profile.assets" :key="asset.symbol" class="flex justify-between items-center p-3 bg-gray-50 rounded">
          <div>
            <p class="font-medium text-gray-900">{{ asset.symbol }}</p>
            <p class="text-sm text-gray-600">
              Available: {{ formatAsset(asset.amount) }}
            </p>
            <p v-if="asset.locked_amount > 0" class="text-sm text-orange-600">
              Locked: {{ formatAsset(asset.locked_amount) }}
            </p>
          </div>
          <div class="text-right">
            <p class="font-semibold text-gray-900">{{ formatAsset(asset.amount) }}</p>
          </div>
        </div>
      </div>
      <div v-else class="text-gray-500 text-sm">
        No assets yet
      </div>
    </div>
  </div>
</template>

<style scoped>
.balance-display {
  transition: all 0.3s ease;
}
</style>
