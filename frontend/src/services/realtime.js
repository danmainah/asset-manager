import Pusher from 'pusher-js'

let pusher = null
let channel = null
let connectionRetries = 0
const MAX_RETRIES = 5
const RETRY_DELAY = 2000

export function initializePusher(userId) {
  if (pusher) {
    return pusher
  }

  const pusherKey = import.meta.env.VITE_PUSHER_KEY
  const pusherCluster = import.meta.env.VITE_PUSHER_CLUSTER || 'mt1'

  if (!pusherKey) {
    console.warn('Pusher key not configured')
    return null
  }

  pusher = new Pusher(pusherKey, {
    cluster: pusherCluster,
    encrypted: true,
    enableLogging: false,
  })

  // Handle connection errors with retry logic
  pusher.connection.bind('error', (error) => {
    console.error('Pusher connection error:', error)
    if (connectionRetries < MAX_RETRIES) {
      connectionRetries++
      console.log(`Retrying Pusher connection (${connectionRetries}/${MAX_RETRIES})...`)
      setTimeout(() => {
        pusher.connect()
      }, RETRY_DELAY)
    }
  })

  pusher.connection.bind('connected', () => {
    console.log('Pusher connected successfully')
    connectionRetries = 0
  })

  // Subscribe to user's private channel
  subscribeToUserChannel(userId)

  return pusher
}

export function subscribeToUserChannel(userId) {
  if (!pusher) {
    console.warn('Pusher not initialized')
    return
  }

  // Unsubscribe from old channel if exists
  if (channel) {
    pusher.unsubscribe(`user.${channel.name.split('.')[1]}`)
  }

  // Subscribe to new channel
  channel = pusher.subscribe(`user.${userId}`)

  channel.bind('pusher:subscription_succeeded', () => {
    console.log(`Subscribed to user channel: user.${userId}`)
  })

  channel.bind('pusher:subscription_error', (error) => {
    console.error('Pusher subscription error:', error)
  })
}

export function subscribeToOrderMatched(callback) {
  if (!channel) {
    console.warn('Pusher channel not initialized')
    return
  }

  // Bind to the correct event name from backend
  channel.bind('order.matched', (data) => {
    console.log('Order matched event received:', data)
    callback(data)
  })
}

export function unsubscribeFromOrderMatched(callback) {
  if (!channel) {
    return
  }

  channel.unbind('order.matched', callback)
}

export function disconnectPusher() {
  if (channel) {
    pusher.unsubscribe(channel.name)
    channel = null
  }

  if (pusher) {
    pusher.disconnect()
    pusher = null
  }

  connectionRetries = 0
}
