<?php

use Illuminate\Support\Facades\Route;
use App\Enums\TokenAbility;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\QcmController;

// Auth routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::get('refresh', [AuthController::class, 'refreshToken'])->middleware(['auth:sanctum', 'abilities:' . TokenAbility::ISSUE_ACCESS_TOKEN->value]);
Route::post('logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum', 'abilities:' . TokenAbility::ACCESS_API->value]);

Route::middleware(['auth:sanctum', 'abilities:' . TokenAbility::ACCESS_API->value])->group(function () {
  // User routes
  Route::get('user', [UserController::class, 'show']);
  Route::put('user', [UserController::class, 'update']);
  Route::delete('user', [UserController::class, 'destroy']);

  // Stripe routes
  Route::post('stripe/checkout', [StripeController::class, 'subcriptionCheckout']);
  Route::post('stripe/cancel', [StripeController::class, 'cancel']);
  Route::post('stripe/resume', [StripeController::class, 'resume']);
});

// Qcm routes
Route::post('/qcms', [QcmController::class, 'store']);