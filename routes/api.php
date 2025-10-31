<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\StaffController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class,'register']);
    Route::post('login',    [AuthController::class,'login']);
    Route::post('logout',   [AuthController::class,'logout'])->middleware('auth:sanctum');
    Route::get('me',        [AuthController::class,'me'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {

    // Staff management (admin only)
    Route::apiResource('staff', StaffController::class);

    // extras
    Route::post('/staff/bulk-status', [StaffController::class, 'bulkStatus']);
    Route::post('/staff/role',        [StaffController::class, 'setRole']); // promote/demote

    // People management
    Route::apiResource('person', PersonController::class);

    // extras
    Route::post('/person/bulk-status', [PersonController::class, 'bulkStatus']);
    Route::post('/person/role',        [PersonController::class, 'setRole']); // promote/demote

    // Bus management
    Route::apiResource('buses', BusController::class);

    // actions
    Route::post('/buses/{bus}/status',         [BusController::class, 'setStatus']);
    Route::post('/buses/{bus}/assign-driver',  [BusController::class, 'assignDriver']);
    Route::post('/buses/{bus}/assign-conductor',  [BusController::class, 'assignConductor']);
    Route::post('/buses/{bus}/set-operator',   [BusController::class, 'setOperator']);
    Route::post('/buses/bulk-status',          [BusController::class, 'bulkStatus']);

    // Reservations management
    Route::apiResource('/reservations', ReservationController::class);

    // soft-delete restore
    Route::post  ('/reservations/{reservation}/restore', [ReservationController::class, 'restore']);

    // actions
    Route::post('/reservations/{reservation}/status',      [ReservationController::class, 'setStatus']);
    Route::post('/reservations/{reservation}/sync-buses',  [ReservationController::class, 'syncBuses']);
    Route::post('/reservations/{reservation}/attach-bus',  [ReservationController::class, 'attachBus']);
    Route::post('/reservations/{reservation}/detach-bus',  [ReservationController::class, 'detachBus']);
    Route::post('/reservations/bulk-status',               [ReservationController::class, 'bulkStatus']);

    // Quote endpoint(Pricing engine)
    Route::post('/quote', QuoteController::class);

    // Dashboard
    Route::get('/dash/cards',  [DashboardController::class, 'cards']);   // KPIs
    Route::get('/dash/series', [DashboardController::class, 'series']);  // time series

});



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
