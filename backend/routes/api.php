<?php

use App\Enums\TokenAbility;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\QcmController;
use App\Http\Controllers\QuizController;

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


  // Qcm routes
  Route::post('/quizzes/{id}/qcms', [QcmController::class, 'store']);
  Route::put('/qcms/{id}', [QcmController::class, 'update']);
  Route::delete('/qcms/{id}', [QcmController::class, 'destroy']);
});

// Qcm routes
Route::get('/qcms/{id}', [QcmController::class, 'show']);

// Quiz routes

Route::post('/quizzes', [QuizController::class, 'store']);