# Asset Manager Trading Platform

A comprehensive asset management and trading platform built with Laravel 11 (Backend) and Vue 3 (Frontend).

## 🚀 Features

- **User Management**: Secure authentication and profile management
- **Asset Management**: Real-time tracking of crypto and fiat assets
- **Trading Engine**: High-performance matching engine for limit orders
- **Real-time Updates**: Live orderbook and balance updates via Pusher
- **Order Management**: Create, cancel, and track limit orders
- **Audit Logging**: Comprehensive logging for all sensitive operations

## 🛠️ Tech Stack

- **Backend**: Laravel 11, PHP 8.2
- **Frontend**: Vue 3, Vite, TailwindCSS
- **Database**: MySQL 8.0
- **Caching/Queues**: Redis
- **Real-time**: Pusher
- **Containerization**: Docker & Docker Compose

## 📋 Prerequisites

- Docker Desktop
- Git
- Node.js (for local development without Docker)
- PHP 8.2+ (for local development without Docker)

## 🚀 Getting Started

Choose one of the following methods to run the application:

### Option 1: Run Locally with Docker (Recommended)

This is the easiest way to get started.

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd asset-manager
   ```

2. **Setup Environment Variables**
   ```bash
   # Backend
   cp backend/.env.example backend/.env
   
   # Frontend
   cp frontend/.env.example frontend/.env
   ```

3. **Configure Environment**
   - Update `backend/.env` with your database credentials (if changing defaults) and Pusher keys (optional).
   - Update `frontend/.env` with the same Pusher keys (optional).

4. **Start the Application**
   
   **Windows (Automatic):**
   ```batch
   .\setup.bat
   ```

   **Linux/Mac (Manual):**
   ```bash
   docker-compose up -d --build
   docker-compose exec app php artisan migrate --force
   docker-compose exec app php artisan cache:clear
   ```

5. **Access the App**
   - Frontend: [http://localhost:3000](http://localhost:3000)
   - Backend API: [http://localhost:8000](http://localhost:8000)

---

### Option 2: Run Locally (Manual Setup)

Use this if you don't want to use Docker.

**Prerequisites:**
- PHP 8.2+
- Composer
- Node.js 20+
- MySQL 8.0+
- Redis (Optional, for caching/queues)

**1. Database Setup**
- Create a MySQL database named `asset_manager`.
- Make sure Redis is running (or change `CACHE_STORE=file` in `.env`).

**2. Backend Setup**
   ```bash
   cd backend
   cp .env.example .env
   composer install
   php artisan key:generate
   php artisan migrate
   php artisan db:seed --class=AddTestAssetsSeeder
   php artisan serve
   ```
   *Backend will run at http://localhost:8000*

**3. Frontend Setup**
   ```bash
   cd frontend
   cp .env.example .env
   npm install
   npm run dev
   ```
   *Frontend will run at http://localhost:3000*

---

### Option 3: Deployment (Production)

For deploying to a VPS (e.g., DigitalOcean, AWS EC2, Linode).

1. **Server Prerequisites**
   - Install Docker & Docker Compose on your server.
   - Set up a domain pointing to your server IP.

2. **Environment Configuration**
   - **Backend**: Set `APP_ENV=production`, `APP_DEBUG=false`.
   - **Database**: Use a strong password for `DB_PASSWORD`.
   - **Frontend**: In `frontend/.env`, set `VITE_API_URL` to your production domain API (e.g., `https://api.yourdomain.com`).

3. **Production Build**
   
   Update `docker-compose.yml` (or create `docker-compose.prod.yml`) to use production builds:
   - **Frontend**: Change the build command to `npm run build` and serve static files via Nginx.
   - **Backend**: Optimize PHP config (opcache).

4. **SSL / HTTPS**
   - Use a reverse proxy (Nginx/Traefik) handling SSL (Let's Encrypt) in front of the application containers.
   - Ensure the backend accepts requests from the frontend domain (configure CORS in `config/cors.php`).

5. **Deploy Commands**
   ```bash
   # Pull latest code
   git pull origin main

   # Build and start
   docker-compose up -d --build

   # Run production optimizations
   docker-compose exec app php artisan config:cache
   docker-compose exec app php artisan route:cache
   docker-compose exec app php artisan view:cache
   docker-compose exec app php artisan migrate --force
   ```

## 💰 How to Trade

**New users start with:**
- **$10,000 USD** (for buying more crypto)
- **1.0 BTC** (ready to trade!)
- **10.0 ETH** (ready to trade!)

### Trading Workflow:

1. **Buy or Sell Crypto**
   - Select BTC or ETH
   - Choose "Buy" or "Sell" side
   - Enter price and amount
   - Example: Buy 0.1 BTC at $50,000/BTC
   - Example: Sell 0.5 ETH at $3,000/ETH

2. **Order Matching**
   - Orders automatically match when buy/sell prices align
   - Get instant notifications on matches
   - View orderbook to see other traders' orders

### Adding Assets to Existing Users

If you registered before this update and have 0 crypto, run this command:

```bash
docker-compose exec app php artisan db:seed --class=AddTestAssetsSeeder
```

This will add **1 BTC** and **10 ETH** to all existing users!


## 🔧 API Documentation

### Authentication

**Register New User**
- `POST /api/register`
- Body: 
  ```json
  {
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }
  ```
- Response: User object, authentication token, and initial $10,000 USD balance

**Login**
- `POST /api/login`
- Body:
  ```json
  {
    "email": "john@example.com",
    "password": "password123"
  }
  ```
- Response: User object and authentication token

**Logout** (Requires Authentication)
- `POST /api/logout`
- Headers: `Authorization: Bearer {token}`
- Response: Success message

**Get Current User** (Requires Authentication)
- `GET /api/me`
- Headers: `Authorization: Bearer {token}`
- Response: Current user object

### User Profile
- `GET /api/profile`: Get user profile and balance (Requires Authentication)

### Orders
- `POST /api/orders`: Create new order (Requires Authentication)
  - Body: `symbol` (BTC, ETH), `side` (buy, sell), `price`, `amount`
- `GET /api/orders`: List user orders (Requires Authentication)
- `POST /api/orders/{id}/cancel`: Cancel open order (Requires Authentication)
- `GET /api/orderbook`: Get orderbook for symbol (Requires Authentication)


## 🧪 Running Tests

**Backend (inside container):**
```bash
docker-compose exec app php artisan test
```

**Frontend:**
```bash
cd frontend
npm install
npx vitest run
```

## ⚠️ Troubleshooting

**Database Connection Issue:**
- Ensure MySQL container is running (`docker-compose ps`)
- Check logs: `docker-compose logs db`

**Real-time Updates Not Working:**
- Verify Pusher credentials in both `.env` files
- Check browser console for connection errors

**Permission Issues:**
- If backend throws permission errors:
  ```bash
  docker-compose exec app chown -R www-data:www-data /var/www/storage
  ```
