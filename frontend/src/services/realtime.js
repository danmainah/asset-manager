import Pusher from 'pusher-js'

let pusher = null
let channel = null

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
  })

  // Subscribe to user's private channel
  channel = pusher.subscribe(`private-user.${userId}`)

  return pusher
}

export function subscribeToOrderMatched(callback) {
  if (!channel) {
    console.warn('Pusher channel not initialized')
    return
  }

  channel.bind('OrderMatched', (data) => {
    callback(data)
  })
}

export function unsubscribeFromOrderMatched(callback) {
  if (!channel) {
    return
  }

  channel.unbind('OrderMatched', callback)
}

export function disconnectPusher() {
  if (pusher) {
    pusher.disconnect()
    pusher = null
    channel = null
  }
}
