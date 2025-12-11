import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import OrderForm from '../OrderForm.vue'
import { orderAPI } from '../../services/api'

// Mock the API service
vi.mock('../../services/api', () => ({
    orderAPI: {
        createOrder: vi.fn(),
    },
}))

describe('OrderForm.vue', () => {
    const defaultProps = {
        symbol: 'BTC',
        symbols: ['BTC', 'ETH'],
    }

    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('renders correctly', () => {
        const wrapper = mount(OrderForm, {
            props: defaultProps,
        })

        expect(wrapper.find('h2').text()).toBe('Place Order')
        expect(wrapper.find('select').element.value).toBe('BTC')
        expect(wrapper.findAll('option')).toHaveLength(2)
    })

    it('toggles order side correctly', async () => {
        const wrapper = mount(OrderForm, {
            props: defaultProps,
        })

        // Default should be buy
        const buttons = wrapper.findAll('button[type="button"]')
        const buyButton = buttons[0]
        const sellButton = buttons[1]

        expect(buyButton.classes()).toContain('bg-green-500')
        expect(sellButton.classes()).toContain('bg-gray-100')

        // Click sell
        await sellButton.trigger('click')

        expect(wrapper.vm.form.side).toBe('sell')
        expect(sellButton.classes()).toContain('bg-red-500')
        expect(buyButton.classes()).toContain('bg-gray-100')
    })

    it('validates empty inputs', async () => {
        const wrapper = mount(OrderForm, {
            props: defaultProps,
        })

        await wrapper.find('form').trigger('submit.prevent')

        expect(wrapper.text()).toContain('Please fill in all fields')
        expect(orderAPI.createOrder).not.toHaveBeenCalled()
    })

    it('validates negative values', async () => {
        const wrapper = mount(OrderForm, {
            props: defaultProps,
        })

        await wrapper.find('input[type="number"]').setValue(-1) // Price
        const inputs = wrapper.findAll('input[type="number"]')
        await inputs[1].setValue(1) // Amount

        await wrapper.find('form').trigger('submit.prevent')

        expect(wrapper.text()).toContain('Price and amount must be greater than 0')
        expect(orderAPI.createOrder).not.toHaveBeenCalled()
    })

    it('submits order successfully', async () => {
        const wrapper = mount(OrderForm, {
            props: defaultProps,
        })

        // Fill form
        const inputs = wrapper.findAll('input[type="number"]')
        await inputs[0].setValue(50000) // Price
        await inputs[1].setValue(0.1)   // Amount

        // Mock API success
        orderAPI.createOrder.mockResolvedValue({ id: 1 })

        await wrapper.find('form').trigger('submit.prevent')

        // Check API call
        expect(orderAPI.createOrder).toHaveBeenCalledWith('BTC', 'buy', '50000', '0.1')

        // Check loading state (briefly)
        // Note: since we await the trigger, the function has already completed by now in this test structure

        // Check success message
        expect(wrapper.text()).toContain('Order created successfully!')

        // Check emitted event
        expect(wrapper.emitted('order-created')).toBeTruthy()

        // Check form reset
        expect(inputs[0].element.value).toBe('')
        expect(inputs[1].element.value).toBe('')
    })

    it('handles API errors', async () => {
        const wrapper = mount(OrderForm, {
            props: defaultProps,
        })

        // Fill form
        const inputs = wrapper.findAll('input[type="number"]')
        await inputs[0].setValue(50000)
        await inputs[1].setValue(0.1)

        // Mock API error
        orderAPI.createOrder.mockRejectedValue(new Error('Insufficient funds'))

        await wrapper.find('form').trigger('submit.prevent')

        expect(orderAPI.createOrder).toHaveBeenCalled()
        expect(wrapper.text()).toContain('Insufficient funds')
        expect(wrapper.emitted('order-created')).toBeFalsy()
    })

    it('emits symbol-changed event', async () => {
        const wrapper = mount(OrderForm, {
            props: defaultProps,
        })

        const select = wrapper.find('select')
        await select.setValue('ETH')

        expect(wrapper.emitted('symbol-changed')).toBeTruthy()
        expect(wrapper.emitted('symbol-changed')[0]).toEqual(['ETH'])
    })
})
