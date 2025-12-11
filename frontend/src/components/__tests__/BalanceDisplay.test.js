import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BalanceDisplay from '../BalanceDisplay.vue'

describe('BalanceDisplay.vue', () => {
    const defaultProfile = {
        balance: '1000.50',
        assets: [
            { symbol: 'BTC', amount: '1.23456789', locked_amount: '0' },
            { symbol: 'ETH', amount: '10.00000000', locked_amount: '2.50000000' }
        ]
    }

    it('renders USD balance correctly', () => {
        const wrapper = mount(BalanceDisplay, {
            props: {
                profile: defaultProfile
            }
        })

        expect(wrapper.text()).toContain('$1000.50')
    })

    it('renders assets list correctly', () => {
        const wrapper = mount(BalanceDisplay, {
            props: {
                profile: defaultProfile
            }
        })

        const assets = wrapper.findAll('.bg-gray-50')
        expect(assets).toHaveLength(2)

        expect(assets[0].text()).toContain('BTC')
        expect(assets[0].text()).toContain('1.23456789')

        expect(assets[1].text()).toContain('ETH')
        expect(assets[1].text()).toContain('Locked: 2.50000000')
    })

    it('handles empty assets', () => {
        const wrapper = mount(BalanceDisplay, {
            props: {
                profile: {
                    balance: '0',
                    assets: []
                }
            }
        })

        expect(wrapper.text()).toContain('No assets yet')
        expect(wrapper.findAll('.bg-gray-50')).toHaveLength(0)
    })

    it('formats values correctly', () => {
        const wrapper = mount(BalanceDisplay, {
            props: {
                profile: {
                    balance: '10', // Should be 10.00
                    assets: [
                        { symbol: 'BTC', amount: '1', locked_amount: '0' } // Should be 1.00000000
                    ]
                }
            }
        })

        expect(wrapper.text()).toContain('$10.00')
        expect(wrapper.text()).toContain('1.00000000')
    })
})
