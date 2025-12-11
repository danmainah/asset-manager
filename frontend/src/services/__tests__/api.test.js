import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { setAuthToken, getAuthToken, profileAPI, orderAPI } from '../api'

// Mock global fetch
global.fetch = vi.fn()

describe('api.js', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        localStorage.clear()
        // Reset global token state if possible, though module state persists.
        // We can rely on setAuthToken to manage it.
        setAuthToken(null)
    })

    describe('Auth Token Management', () => {
        it('sets and gets auth token correctly', () => {
            const token = 'test-token-123'
            setAuthToken(token)
            expect(getAuthToken()).toBe(token)
            expect(localStorage.getItem('auth_token')).toBe(token)
        })
    })

    describe('API Calls', () => {
        const mockSuccessResponse = (data) => {
            return Promise.resolve({
                ok: true,
                json: () => Promise.resolve(data),
            })
        }

        const mockErrorResponse = (status, message) => {
            return Promise.resolve({
                ok: false,
                status,
                json: () => Promise.resolve({ message }),
            })
        }

        it('adds auth header when token exists', async () => {
            setAuthToken('secret-token')
            fetch.mockReturnValue(mockSuccessResponse({}))

            await profileAPI.getProfile()

            const headers = fetch.mock.calls[0][1].headers
            expect(headers['Authorization']).toBe('Bearer secret-token')
        })

        it('throws error on failed response', async () => {
            fetch.mockReturnValue(mockErrorResponse(400, 'Bad Request'))

            await expect(profileAPI.getProfile()).rejects.toThrow('Bad Request')
        })
    })

    describe('profileAPI', () => {
        it('calls getProfile endpoint', async () => {
            fetch.mockReturnValue(Promise.resolve({
                ok: true,
                json: () => Promise.resolve({ id: 1 }),
            }))

            await profileAPI.getProfile()

            expect(fetch).toHaveBeenCalledWith(
                expect.stringContaining('/profile'),
                expect.any(Object)
            )
        })
    })

    describe('orderAPI', () => {
        it('calls createOrder endpoint with correct body', async () => {
            fetch.mockReturnValue(Promise.resolve({
                ok: true,
                json: () => Promise.resolve({}),
            }))

            await orderAPI.createOrder('BTC', 'buy', '50000', '1.0')

            expect(fetch).toHaveBeenCalledWith(
                expect.stringContaining('/orders'),
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({ symbol: 'BTC', side: 'buy', price: '50000', amount: '1.0' }),
                })
            )
        })

        it('calls getOrders endpoint with params', async () => {
            fetch.mockReturnValue(Promise.resolve({
                ok: true,
                json: () => Promise.resolve([]),
            }))

            await orderAPI.getOrders('open')

            expect(fetch).toHaveBeenCalledWith(
                expect.stringContaining('/orders?status=open'),
                expect.any(Object)
            )
        })

        it('calls getOrderbook endpoint', async () => {
            fetch.mockReturnValue(Promise.resolve({
                ok: true,
                json: () => Promise.resolve({}),
            }))

            await orderAPI.getOrderbook('ETH')

            expect(fetch).toHaveBeenCalledWith(
                expect.stringContaining('/orderbook?symbol=ETH'),
                expect.any(Object)
            )
        })

        it('calls cancelOrder endpoint', async () => {
            fetch.mockReturnValue(Promise.resolve({
                ok: true,
                json: () => Promise.resolve({}),
            }))

            await orderAPI.cancelOrder(123)

            expect(fetch).toHaveBeenCalledWith(
                expect.stringContaining('/orders/123/cancel'),
                expect.objectContaining({
                    method: 'POST',
                })
            )
        })
    })
})
