<template>
  <div class="dashboard-container">
    <nav class="navbar">
      <div class="nav-brand">
        <div class="logo">₿</div>
        <h1>Asset Manager</h1>
      </div>
      <div class="nav-user">
        <span class="user-name">{{ userName }}</span>
        <button @click="handleLogout" class="btn-logout">
          <span>Logout</span>
          <span class="logout-icon">🚪</span>
        </button>
      </div>
    </nav>

    <main class="dashboard-main">
      <TradingInterface />
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { authAPI } from '../services/api'
import TradingInterface from '../components/TradingInterface.vue'

const router = useRouter()
const userName = ref('User')

const handleLogout = async () => {
  try {
    await authAPI.logout()
    router.push('/login')
  } catch (error) {
    console.error('Logout failed:', error)
    // Even if API call fails, redirect to login
    router.push('/login')
  }
}

onMounted(async () => {
  try {
    const response = await authAPI.getCurrentUser()
    userName.value = response.user.name
  } catch (error) {
    console.error('Failed to fetch user:', error)
  }
})
</script>

<style scoped>
.dashboard-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #0ea5e9 0%, #3b82f6 50%, #6366f1 100%);
  background-attachment: fixed;
  display: flex;
  flex-direction: column;
  position: relative;
}

.dashboard-container::before {
  content: '';
  position: absolute;
  inset: 0;
  background: 
    radial-gradient(circle at 20% 50%, rgba(14, 165, 233, 0.2) 0%, transparent 50%),
    radial-gradient(circle at 80% 50%, rgba(99, 102, 241, 0.2) 0%, transparent 50%);
  pointer-events: none;
}

.navbar {
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
  padding: 16px 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  position: relative;
  z-index: 10;
}

.nav-brand {
  display: flex;
  align-items: center;
  gap: 16px;
}

.logo {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #ea580c 100%);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
  box-shadow: 
    0 8px 24px rgba(251, 191, 36, 0.4),
    inset 0 2px 4px rgba(255, 255, 255, 0.3);
  position: relative;
  overflow: hidden;
  animation: logoFloat 3s ease-in-out infinite;
}

.logo::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
  animation: shimmer 3s infinite;
}

@keyframes logoFloat {
  0%, 100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-4px);
  }
}

@keyframes shimmer {
  0% {
    transform: translateX(-100%) translateY(-100%) rotate(45deg);
  }
  100% {
    transform: translateX(100%) translateY(100%) rotate(45deg);
  }
}

.nav-brand h1 {
  font-size: 24px;
  font-weight: 800;
  margin: 0;
  color: white;
  text-shadow: 
    0 2px 10px rgba(0, 0, 0, 0.2),
    0 0 20px rgba(255, 255, 255, 0.3);
  letter-spacing: -0.5px;
}

.nav-user {
  display: flex;
  align-items: center;
  gap: 16px;
}

.user-name {
  color: white;
  font-weight: 600;
  font-size: 14px;
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.btn-logout {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.9) 0%, rgba(220, 38, 38, 0.9) 100%);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: white;
  padding: 10px 20px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  box-shadow: 
    0 4px 12px rgba(239, 68, 68, 0.3),
    inset 0 1px 2px rgba(255, 255, 255, 0.2);
  position: relative;
  overflow: hidden;
}

.btn-logout::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.2);
  transition: left 0.5s ease;
}

.btn-logout:hover::before {
  left: 100%;
}

.btn-logout:hover {
  transform: translateY(-2px);
  box-shadow: 
    0 6px 20px rgba(239, 68, 68, 0.5),
    inset 0 1px 2px rgba(255, 255, 255, 0.3);
}

.btn-logout:active {
  transform: translateY(0);
}

.logout-icon {
  font-size: 16px;
}

.dashboard-main {
  flex: 1;
  overflow: auto;
  position: relative;
  z-index: 1;
}

@media (max-width: 768px) {
  .navbar {
    padding: 12px 16px;
  }

  .nav-brand h1 {
    font-size: 20px;
  }

  .user-name {
    display: none;
  }

  .btn-logout span:first-child {
    display: none;
  }
}
</style>

