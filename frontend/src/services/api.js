const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

let authToken = localStorage.getItem('auth_token');

export function setAuthToken(token) {
  authToken = token;
  localStorage.setItem('auth_token', token);
}

export function getAuthToken() {
  return authToken;
}

export function clearAuthToken() {
  authToken = null;
  localStorage.removeItem('auth_token');
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

    // Handle validation errors (422)
    if (response.status === 422 && error.messages) {
      // Extract all validation error messages
      const messages = Object.values(error.messages).flat();
      throw new Error(messages.join(', '));
    }

    // Handle other errors
    throw new Error(error.message || error.error || `API error: ${response.status}`);
  }

  return response.json();
}

// Authentication API
export const authAPI = {
  async login(email, password) {
    const response = await apiCall('/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
    if (response.token) {
      setAuthToken(response.token);
    }
    return response;
  },

  async register(name, email, password, password_confirmation) {
    const response = await apiCall('/register', {
      method: 'POST',
      body: JSON.stringify({ name, email, password, password_confirmation }),
    });
    if (response.token) {
      setAuthToken(response.token);
    }
    return response;
  },

  async logout() {
    try {
      await apiCall('/logout', { method: 'POST' });
    } finally {
      clearAuthToken();
    }
  },

  async getCurrentUser() {
    return apiCall('/me');
  },
};

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
    const params = new URLSearchParams();
    if (symbol && symbol !== 'null') params.append('symbol', symbol);
    return apiCall(`/orderbook?${params.toString()}`);
  },

  cancelOrder(orderId) {
    return apiCall(`/orders/${orderId}/cancel`, {
      method: 'POST',
    });
  },
};
