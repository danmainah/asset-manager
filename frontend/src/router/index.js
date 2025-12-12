import { createRouter, createWebHistory } from 'vue-router'
import { getAuthToken } from '../services/api'
import Login from '../views/Login.vue'
import Register from '../views/Register.vue'
import Dashboard from '../views/Dashboard.vue'

const routes = [
    {
        path: '/login',
        name: 'Login',
        component: Login,
        meta: { requiresGuest: true }
    },
    {
        path: '/register',
        name: 'Register',
        component: Register,
        meta: { requiresGuest: true }
    },
    {
        path: '/',
        name: 'Dashboard',
        component: Dashboard,
        meta: { requiresAuth: true }
    }
]

const router = createRouter({
    history: createWebHistory(),
    routes
})

// Navigation guard
router.beforeEach((to, from, next) => {
    const isAuthenticated = !!getAuthToken()

    if (to.meta.requiresAuth && !isAuthenticated) {
        // Redirect to login if not authenticated
        next({ name: 'Login' })
    } else if (to.meta.requiresGuest && isAuthenticated) {
        // Redirect to dashboard if already authenticated
        next({ name: 'Dashboard' })
    } else {
        next()
    }
})

export default router
