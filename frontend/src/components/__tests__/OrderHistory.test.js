import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import OrderHistory from '../OrderHistory.vue'
import { orderAPI } from '../../services/api'

// Mock the API service
vi.mock('../../services/api', () => ({
    orderAPI: {
        cancelOrder: vi.fn(),
    },
}))

describe('OrderHistory.vue', () => {
    const defaultOrders = [
        {
            id: 1,
            side: 'buy',
            price: '50000.00',
            amount: '0.10',
            status: 'open',
            created_at: '2023-01-01T12:00:00Z',
        },
        {
            id: 2,
            side: 'sell',
            price: '55000.00',
            amount: '0.50',
            status: 'filled',
            created_at: '2023-01-02T12:00:00Z',
        },
    ]

    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renders orders list correctly', () => {
        const wrapper = mount(OrderHistory, {
            props: {
                orders: defaultOrders,
            },
        })

        const rows = wrapper.findAll('tbody tr')
        expect(rows).toHaveLength(2)

        // Check first order (Buy)
        expect(rows[0].text()).toContain('BUY')
        expect(rows[0].text()).toContain('50000.00000000')
        expect(rows[0].text()).toContain('Open')

        // Check second order (Sell)
        expect(rows[1].text()).toContain('SELL')
        expect(rows[1].text()).toContain('Filled')
    })

    it('handles empty orders list', () => {
        const wrapper = mount(OrderHistory, {
            props: {
                orders: [],
            },
        })

        expect(wrapper.text()).toContain('No orders yet')
        expect(wrapper.find('table').exists()).toBe(false)
    })

    it('calls cancelOrder when cancel button is clicked', async () => {
        const wrapper = mount(OrderHistory, {
            props: {
                orders: defaultOrders,
            },
        })

        const cancelButton = wrapper.find('button')
        await cancelButton.trigger('click')

        expect(orderAPI.cancelOrder).toHaveBeenCalledWith(1)
        expect(wrapper.emitted('order-cancelled')).toBeTruthy()
    })

    it('handles cancelOrder errors', async () => {
        const wrapper = mount(OrderHistory, {
            props: {
                orders: defaultOrders,
            },
        })

        orderAPI.cancelOrder.mockRejectedValue(new Error('Failed to cancel'))

        const cancelButton = wrapper.find('button')
        await cancelButton.trigger('click')

        expect(wrapper.text()).toContain('Failed to cancel')
        expect(wrapper.emitted('order-cancelled')).toBeFalsy()
    })

    it('displays correct colors for sides and statuses', () => {
        const wrapper = mount(OrderHistory, {
            props: {
                orders: defaultOrders,
            },
        })

        const rows = wrapper.findAll('tbody tr')

        // Check Buy side color
        expect(rows[0].find('.text-green-600').exists()).toBe(true)

        // Check Sell side color
        expect(rows[1].find('.text-red-600').exists()).toBe(true)

        // Check Open status color
        expect(rows[0].find('.bg-blue-100').exists()).toBe(true)

        // Check Filled status color
        expect(rows[1].find('.bg-green-100').exists()).toBe(true)
    })
})
