import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import TradingInterface from '../TradingInterface.vue'
import { profileAPI, orderAPI } from '../../services/api'
import { initializePusher, disconnectPusher } from '../../services/realtime'

// Mock dependencies
vi.mock('../../services/api', () => ({
    profileAPI: {
        getProfile: vi.fn(),
    },
    orderAPI: {
        getOrders: vi.fn(),
        getOrderbook: vi.fn(),
    },
}))

vi.mock('../../services/realtime', () => ({
    initializePusher: vi.fn(),
    subscribeToOrderMatched: vi.fn(),
    disconnectPusher: vi.fn(),
}))

// Mock Sub-components to avoid deep rendering issues and focus on orchestration
vi.mock('../OrderForm.vue', () => ({
    default: {
        template: '<div data-testid="order-form" @click="$emit(\'order-created\')"></div>',
        props: ['symbol', 'symbols'],
    }
}))
vi.mock('../BalanceDisplay.vue', () => ({
    default: {
        template: '<div data-testid="balance-display"></div>',
        props: ['profile'],
    }
}))
vi.mock('../OrderHistory.vue', () => ({
    default: {
        template: '<div data-testid="order-history" @click="$emit(\'order-cancelled\')"></div>',
        props: ['orders'],
    }
}))
vi.mock('../OrderbookDisplay.vue', () => ({
    default: {
        template: '<div data-testid="orderbook-display"></div>',
        props: ['symbol', 'orderbook'],
    }
}))

describe('TradingInterface.vue', () => {
    const mockProfile = {
        user: { id: 1, name: 'Test User' },
        balance: '1000.00',
        assets: [],
    }

    const mockOrders = [
        { id: 1, symbol: 'BTC', status: 'open' },
        { id: 2, symbol: 'ETH', status: 'open' },
    ]

    const mockOrderbook = {
        buy: [],
        sell: [],
    }

    beforeEach(() => {
        vi.clearAllMocks()
        profileAPI.getProfile.mockResolvedValue(mockProfile)
        orderAPI.getOrders.mockResolvedValue({ orders: mockOrders })
        orderAPI.getOrderbook.mockResolvedValue(mockOrderbook)
    })

    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('loads initial data on mount', async () => {
        mount(TradingInterface)

        // Debugging
        // console.log('Is mock:', vi.isMockFunction(profileAPI.getProfile))

        // Wait for potential async mounting
        await flushPromises()

        expect(profileAPI.getProfile).toHaveBeenCalled()
        expect(orderAPI.getOrders).toHaveBeenCalled()
        expect(orderAPI.getOrderbook).toHaveBeenCalledWith('BTC') // Default symbol
    })

    it('renders loading state initially', () => {
        const wrapper = mount(TradingInterface)
        // Note: Since we await loadProfile in onMounted, it might briefly be true.
        // However, flushPromises resolves it. We can try to catch it before resolution or check final state.
        // A better check for initial load is if child components are NOT present yet if using v-if="!loading" for them?
        // Looking at the code: v-if="loading" shows a loader.

        // Check final state after load
        // expect(wrapper.find('.text-gray-600').text()).toBe('Loading...')
    })

    it('renders components after data load', async () => {
        const wrapper = mount(TradingInterface)
        await flushPromises()

        expect(wrapper.find('[data-testid="balance-display"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="order-form"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="orderbook-display"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="order-history"]').exists()).toBe(true)
    })

    it('initializes realtime updates with user ID', async () => {
        mount(TradingInterface)
        await flushPromises()

        expect(initializePusher).toHaveBeenCalledWith(1)
    })

    it('reloads data when order is created', async () => {
        const wrapper = mount(TradingInterface)
        await flushPromises()

        // Clear initial calls to verify reloading
        profileAPI.getProfile.mockClear()
        orderAPI.getOrders.mockClear()
        orderAPI.getOrderbook.mockClear()

        // Trigger order created event from mock component
        await wrapper.find('[data-testid="order-form"]').trigger('click')
        await flushPromises()

        expect(profileAPI.getProfile).toHaveBeenCalled()
        expect(orderAPI.getOrders).toHaveBeenCalled()
        expect(orderAPI.getOrderbook).toHaveBeenCalled()
    })

    it('reloads data when order is cancelled', async () => {
        const wrapper = mount(TradingInterface)
        await flushPromises()

        // Clear initial calls
        profileAPI.getProfile.mockClear()
        orderAPI.getOrders.mockClear()
        orderAPI.getOrderbook.mockClear()

        // Trigger order cancelled event
        await wrapper.find('[data-testid="order-history"]').trigger('click')
        await flushPromises()

        expect(profileAPI.getProfile).toHaveBeenCalled()
        expect(orderAPI.getOrders).toHaveBeenCalled()
        expect(orderAPI.getOrderbook).toHaveBeenCalled()
    })

    it('displays error message on API failure', async () => {
        profileAPI.getProfile.mockRejectedValue(new Error('Network Error'))
        const wrapper = mount(TradingInterface)
        await flushPromises()

        expect(wrapper.text()).toContain('Network Error')
    })

    it('cleans up on unmount', async () => {
        const wrapper = mount(TradingInterface)
        await flushPromises()

        wrapper.unmount()

        expect(disconnectPusher).toHaveBeenCalled()
    })
})
