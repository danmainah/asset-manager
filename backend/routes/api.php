<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrderController;

// Public authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    // Authentication endpoints
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Profile endpoints
    Route::get('/profile', [ProfileController::class, 'show']);
    
    // Order endpoints
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancel']);
    Route::get('/orderbook', [OrderController::class, 'getOrderbook']);
});

// Internal matching endpoint (should be protected by internal-only middleware in production)
Route::post('/orders/match', [OrderController::class, 'match']);