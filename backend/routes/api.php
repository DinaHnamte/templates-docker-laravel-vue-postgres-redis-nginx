<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AssignmentTrackingController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DriverAvailabilityController;
use App\Http\Controllers\DriverBiddingController;
use App\Http\Controllers\OrderBidController;
use App\Http\Controllers\OrderLifecycleController;
use App\Http\Controllers\CustomerTrackingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->get('me', [AuthController::class, 'me']);
    Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/ping', function () {
    return [
        'status' => 'ok',
    ];
});

Route::get('/catalog/vendors', [VendorController::class, 'indexPublic']);
Route::get('/catalog/vendors/{vendor}/products', [ProductController::class, 'listByVendor']);
Route::get('/catalog/vendors/{vendor}/products/{product}', [ProductController::class, 'showPublic']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/vendors', [VendorController::class, 'store'])->middleware('role:vendor|admin');
    Route::get('/vendors/me', [VendorController::class, 'showMine'])->middleware('role:vendor|admin');
    Route::patch('/vendors/{vendor}', [VendorController::class, 'update'])->middleware('role:vendor|admin');

    Route::post('/vendors/{vendor}/products', [ProductController::class, 'store'])->middleware('role:vendor|admin');
    Route::patch('/vendors/{vendor}/products/{product}', [ProductController::class, 'update'])->middleware('role:vendor|admin');
    Route::delete('/vendors/{vendor}/products/{product}', [ProductController::class, 'destroy'])->middleware('role:vendor|admin');

    // Cart
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/items/{item}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{item}', [CartController::class, 'removeItem']);
    Route::post('/cart/fulfillment', [CartController::class, 'setFulfillment']);
    Route::post('/cart/clear', [CartController::class, 'clear']);

    // Checkout
    Route::post('/checkout', [CheckoutController::class, 'placeOrder']);

    // Addresses
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::patch('/addresses/{address}', [AddressController::class, 'update']);
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
    Route::get('/addresses/distance/vendor/{vendor}', [AddressController::class, 'distanceToVendor']);

    // Orders lifecycle (vendor/admin)
    Route::patch('/orders/{order}/confirm', [OrderLifecycleController::class, 'vendorConfirm'])
        ->middleware('role:vendor|admin');
    Route::patch('/orders/{order}/ready', [OrderLifecycleController::class, 'markReady'])
        ->middleware('role:vendor|admin');

    // Driver bidding visibility
    Route::get('/driver/open-orders', [DriverBiddingController::class, 'openOrders'])
        ->middleware('role:driver|admin');
    Route::get('/orders/{order}/bidding/eligibility', [DriverBiddingController::class, 'eligibility'])
        ->middleware('role:driver|admin');
    Route::post('/orders/{order}/bids', [DriverBiddingController::class, 'submitBid'])
        ->middleware('role:driver|admin');
    Route::patch('/driver/availability', [DriverAvailabilityController::class, 'update'])
        ->middleware('role:driver|admin');

    // Customer bid handling
    Route::get('/orders/{order}/bids', [OrderBidController::class, 'listBids'])
        ->middleware('role:customer|admin');
    Route::post('/orders/{order}/bids/{bid}/accept', [OrderBidController::class, 'accept'])
        ->middleware('role:customer|admin');

    // Tracking
    Route::post('/assignments/{assignment}/location', [AssignmentTrackingController::class, 'updateLocation'])
        ->middleware('role:driver|admin');
    Route::post('/assignments/{assignment}/picked-up', [AssignmentTrackingController::class, 'markPickedUp'])
        ->middleware('role:driver|admin');
    Route::post('/assignments/{assignment}/delivered', [AssignmentTrackingController::class, 'markDelivered'])
        ->middleware('role:driver|admin');
    Route::get('/assignments/{assignment}/nav', [AssignmentTrackingController::class, 'navigationLinks'])
        ->middleware('role:driver|admin');
    Route::get('/assignments/{assignment}/tracking', [CustomerTrackingController::class, 'show'])
        ->middleware('auth:sanctum');

    // Delivery verification
    Route::post('/orders/{order}/verification/otp', [VerificationController::class, 'createOtp'])
        ->middleware('role:customer|admin');
    Route::post('/assignments/{assignment}/verify', [VerificationController::class, 'verify'])
        ->middleware('role:driver|admin');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
});

// Admin
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::patch('/admin/users/{user}', [AdminUserController::class, 'update']);
    Route::get('/admin/disputes', [DisputeController::class, 'index']);
    Route::patch('/admin/disputes/{dispute}', [DisputeController::class, 'update']);
});

// Disputes create for participants
Route::middleware('auth:sanctum')->post('/orders/{order}/disputes', [DisputeController::class, 'store']);

