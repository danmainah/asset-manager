import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import OrderbookDisplay from '../OrderbookDisplay.vue'

describe('OrderbookDisplay.vue', () => {
    const defaultProps = {
        symbol: 'BTC',
        orderbook: {
            buy: [
                { price: '49000.00', amount: '0.5' },
                { price: '48000.00', amount: '1.0' },
            ],
            sell: [
                { price: '51000.00', amount: '0.2' },
                { price: '52000.00', amount: '0.8' },
            ],
        },
    }

    it('renders correct title', () => {
        const wrapper = mount(OrderbookDisplay, {
            props: defaultProps,
        })

        expect(wrapper.find('h2').text()).toBe('BTC Orderbook')
    })

    it('renders buy orders correctly', () => {
        const wrapper = mount(OrderbookDisplay, {
            props: defaultProps,
        })

        const buyOrders = wrapper.findAll('.bg-green-50')
        expect(buyOrders).toHaveLength(2)

        expect(buyOrders[0].text()).toContain('49000.00000000')
        expect(buyOrders[0].text()).toContain('0.50000000')
    })

    it('renders sell orders correctly', () => {
        const wrapper = mount(OrderbookDisplay, {
            props: defaultProps,
        })

        const sellOrders = wrapper.findAll('.bg-red-50')
        expect(sellOrders).toHaveLength(2)

        expect(sellOrders[0].text()).toContain('51000.00000000')
        expect(sellOrders[0].text()).toContain('0.20000000')
    })

    it('handles empty orderbook', () => {
        const wrapper = mount(OrderbookDisplay, {
            props: {
                symbol: 'ETH',
                orderbook: { buy: [], sell: [] },
            },
        })

        expect(wrapper.text()).toContain('No buy orders')
        expect(wrapper.text()).toContain('No sell orders')
        expect(wrapper.findAll('.bg-green-50')).toHaveLength(0)
        expect(wrapper.findAll('.bg-red-50')).toHaveLength(0)
    })

    it('formats partial data correctly', () => {
        const wrapper = mount(OrderbookDisplay, {
            props: {
                symbol: 'BTC',
                orderbook: {
                    buy: [{ price: '100', amount: '1' }],
                    sell: []
                },
            },
        })

        expect(wrapper.text()).toContain('100.00000000')
        expect(wrapper.text()).toContain('1.00000000')
        expect(wrapper.text()).toContain('No sell orders')
    })
})
