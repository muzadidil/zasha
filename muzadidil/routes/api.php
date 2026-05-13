<?php

use App\Http\Controllers\Api\Admin\OrderMonitorController;
use App\Http\Controllers\Api\Admin\PartnerVerificationController;
use App\Http\Controllers\Api\Admin\TopupApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Api\Customer\RatingController as CustomerRatingController;
use App\Http\Controllers\Api\Partner\LocationController as PartnerLocationController;
use App\Http\Controllers\Api\Partner\OrderController as PartnerOrderController;
use App\Http\Controllers\Api\Partner\ProfileController as PartnerProfileController;
use App\Http\Controllers\Api\Partner\RatingController as PartnerRatingController;
use App\Http\Controllers\Api\Partner\WalletController as PartnerWalletController;
use App\Http\Controllers\Api\ServiceCategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('verify-phone', [AuthController::class, 'verifyPhone']);
    });
});

Route::get('service-categories', [ServiceCategoryController::class, 'index']);

Route::middleware(['auth:sanctum', 'role:customer'])
    ->prefix('customer')
    ->group(function () {
        Route::get('orders', [CustomerOrderController::class, 'index']);
        Route::post('orders', [CustomerOrderController::class, 'store'])->middleware('throttle:order-create');
        Route::get('orders/{id}', [CustomerOrderController::class, 'show']);
        Route::patch('orders/{id}/increase-price', [CustomerOrderController::class, 'increasePrice'])
            ->middleware('throttle:order-price-increase');
        Route::delete('orders/{id}', [CustomerOrderController::class, 'cancel']);
        Route::delete('orders/{id}/cancel', [CustomerOrderController::class, 'cancel']);
        Route::post('orders/{id}/rate', [CustomerRatingController::class, 'store']);
    });

Route::middleware(['auth:sanctum', 'role:partner'])
    ->prefix('partner')
    ->group(function () {
        Route::get('orders/available', [PartnerOrderController::class, 'available']);
        Route::get('orders/history', [PartnerOrderController::class, 'history']);
        Route::get('orders/{id}', [PartnerOrderController::class, 'show']);
        Route::post('orders/{id}/claim', [PartnerOrderController::class, 'claim'])
            ->middleware('throttle:partner-claim');
        Route::patch('orders/{id}/start', [PartnerOrderController::class, 'start']);
        Route::patch('orders/{id}/complete', [PartnerOrderController::class, 'complete']);
        Route::post('orders/{id}/rate', [PartnerRatingController::class, 'store']);

        Route::get('wallet', [PartnerWalletController::class, 'show']);
        Route::post('wallet/topup-request', [PartnerWalletController::class, 'topupRequest']);
        Route::get('wallet/transactions', [PartnerWalletController::class, 'transactions']);

        Route::patch('location', [PartnerLocationController::class, 'update']);
        Route::patch('online-status', [PartnerLocationController::class, 'setOnline']);

        Route::get('profile', [PartnerProfileController::class, 'show']);
        Route::put('profile', [PartnerProfileController::class, 'upsert']);
    });

Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('topup-requests', [TopupApprovalController::class, 'index']);
        Route::post('topup-requests/{id}/approve', [TopupApprovalController::class, 'approve']);
        Route::post('topup-requests/{id}/reject', [TopupApprovalController::class, 'reject']);
        Route::get('orders', [OrderMonitorController::class, 'index']);
        Route::get('partners', [PartnerVerificationController::class, 'index']);
        Route::post('partners/{id}/verify', [PartnerVerificationController::class, 'verify']);
    });
