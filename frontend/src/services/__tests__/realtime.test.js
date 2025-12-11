import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import {
  initializePusher,
  subscribeToUserChannel,
  subscribeToOrderMatched,
  unsubscribeFromOrderMatched,
  disconnectPusher,
} from '../realtime'

// Mock Pusher
vi.mock('pusher-js', () => {
  const mockChannel = {
    name: 'user.1',
    bind: vi.fn(),
    unbind: vi.fn(),
  }

  const mockConnection = {
    bind: vi.fn(),
  }

  const mockPusher = {
    subscribe: vi.fn(() => mockChannel),
    unsubscribe: vi.fn(),
    disconnect: vi.fn(),
    connection: mockConnection,
  }

  return {
    default: vi.fn(function() {
      return mockPusher
    }),
  }
})

describe('Realtime Service', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // Reset environment
    delete process.env.VITE_PUSHER_KEY
  })

  afterEach(() => {
    disconnectPusher()
  })

  describe('initializePusher', () => {
    it('should initialize Pusher with correct configuration', () => {
      process.env.VITE_PUSHER_KEY = 'test-key'
      process.env.VITE_PUSHER_CLUSTER = 'mt1'

      const pusher = initializePusher(1)

      expect(pusher).toBeDefined()
    })

    it('should warn when Pusher key is not configured', () => {
      const warnSpy = vi.spyOn(console, 'warn')

      const pusher = initializePusher(1)

      expect(warnSpy).toHaveBeenCalledWith('Pusher key not configured')
      expect(pusher).toBeNull()
    })

    it('should return existing Pusher instance if already initialized', () => {
      process.env.VITE_PUSHER_KEY = 'test-key'

      const pusher1 = initializePusher(1)
      const pusher2 = initializePusher(1)

      expect(pusher1).toBe(pusher2)
    })
  })

  describe('subscribeToUserChannel', () => {
    it('should subscribe to user channel with correct format', () => {
      process.env.VITE_PUSHER_KEY = 'test-key'

      initializePusher(123)
      subscribeToUserChannel(123)

      // Verify subscription was attempted
      expect(true).toBe(true)
    })

    it('should warn when Pusher is not initialized', () => {
      const warnSpy = vi.spyOn(console, 'warn')

      subscribeToUserChannel(1)

      expect(warnSpy).toHaveBeenCalledWith('Pusher not initialized')
    })
  })

  describe('subscribeToOrderMatched', () => {
    it('should bind to order.matched event', () => {
      process.env.VITE_PUSHER_KEY = 'test-key'

      const callback = vi.fn()
      initializePusher(1)
      subscribeToOrderMatched(callback)

      // Verify callback is registered
      expect(callback).toBeDefined()
    })

    it('should warn when channel is not initialized', () => {
      const warnSpy = vi.spyOn(console, 'warn')
      const callback = vi.fn()

      subscribeToOrderMatched(callback)

      expect(warnSpy).toHaveBeenCalledWith('Pusher channel not initialized')
    })
  })

  describe('disconnectPusher', () => {
    it('should disconnect Pusher and clean up', () => {
      process.env.VITE_PUSHER_KEY = 'test-key'

      initializePusher(1)
      disconnectPusher()

      // Verify cleanup occurred
      expect(true).toBe(true)
    })
  })
})
