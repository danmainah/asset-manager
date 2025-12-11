const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

let authToken = localStorage.getItem('auth_token');

export function setAuthToken(token) {
  authToken = token;
  localStorage.setItem('auth_token', token);
}

export function getAuthToken() {
  return authToken;
}

async function apiCall(endpoint, options = {}) {
  const headers = {
    'Content-Type': 'application/json',
    ...options.headers,
  };

  if (authToken) {
    headers['Authorization'] = `Bearer ${authToken}`;
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...options,
    headers,
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message || `API error: ${response.status}`);
  }

  return response.json();
}

export const profileAPI = {
  getProfile() {
    return apiCall('/profile');
  },
};

export const orderAPI = {
  createOrder(symbol, side, price, amount) {
    return apiCall('/orders', {
      method: 'POST',
      body: JSON.stringify({ symbol, side, price, amount }),
    });
  },

  getOrders(status = null) {
    const params = new URLSearchParams();
    if (status) params.append('status', status);
    return apiCall(`/orders?${params.toString()}`);
  },

  getOrderbook(symbol) {
    return apiCall(`/orderbook?symbol=${symbol}`);
  },

  cancelOrder(orderId) {
    return apiCall(`/orders/${orderId}/cancel`, {
      method: 'POST',
    });
  },
};
