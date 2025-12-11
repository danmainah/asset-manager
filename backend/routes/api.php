<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'balance' => $request->user()->balance,
            'assets' => $request->user()->assets,
        ]);
    });
    
    Route::get('/orders', function (Request $request) {
        return response()->json([
            'orders' => $request->user()->orders,
        ]);
    });
    
    Route::post('/orders', function (Request $request) {
        // This will be implemented later
        return response()->json(['message' => 'Order creation endpoint']);
    });
});