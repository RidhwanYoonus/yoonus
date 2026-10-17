<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopController::class, 'home'])->name('home');
Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/category/{category}', [ShopController::class, 'category'])->name('category.show');
Route::get('/product/{product}', [ProductController::class, 'show'])->name('product.show');
Route::get('/placeholder/{product}.svg', [ProductController::class, 'placeholder'])->name('placeholder');
Route::post('/product/{product}/reviews', [ProductController::class, 'review'])->middleware('throttle:5,10')->name('reviews.store');
Route::get('/api/rates', [ShopController::class, 'rates'])->name('api.rates');

Route::get('/wishlist', [ShopController::class, 'wishlist'])->name('wishlist');
Route::post('/wishlist/{product}', [ShopController::class, 'toggleWishlist'])->name('wishlist.toggle');
Route::get('/compare', [ShopController::class, 'compare'])->name('compare');
Route::post('/compare/{product}', [ShopController::class, 'toggleCompare'])->name('compare.toggle');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart/add/{product}', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{productId}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{productId}', [CartController::class, 'remove'])->name('cart.remove');
Route::get('/cart/recover/{token}', [CartController::class, 'recover'])->middleware('signed')->name('cart.recover');

Route::middleware('throttle:checkout')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->name('checkout.quote');
    Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');
});
Route::get('/checkout/complete/{order}', [CheckoutController::class, 'complete'])->name('checkout.complete');

Route::get('/payments/mock/{order}', [PaymentController::class, 'mock'])->middleware('signed')->name('payments.mock');
Route::post('/payments/mock/{order}', [PaymentController::class, 'mockApprove'])->middleware('signed')->name('payments.mock.approve');
Route::get('/payments/{gateway}/return/{order}', [PaymentController::class, 'return'])->name('payments.return');
Route::post('/webhooks/{gateway}', [PaymentController::class, 'webhook'])->name('payments.webhook');

Route::get('/track', [OrderController::class, 'track'])->name('order.track');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('order.show');
Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->name('order.invoice');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::post('/account/addresses', [AccountController::class, 'storeAddress'])->name('account.addresses.store');
    Route::delete('/account/addresses/{address}', [AccountController::class, 'deleteAddress'])->name('account.addresses.delete');
});

Route::post('/newsletter', [MarketingController::class, 'subscribe'])->middleware('throttle:5,1')->name('newsletter.subscribe');
Route::get('/newsletter/confirm/{subscriber}', [MarketingController::class, 'confirm'])->middleware('signed')->name('newsletter.confirm');
Route::get('/newsletter/unsubscribe/{subscriber}', [MarketingController::class, 'unsubscribe'])->middleware('signed')->name('newsletter.unsubscribe');
Route::post('/alerts', [MarketingController::class, 'alert'])->middleware('throttle:5,1')->name('alerts.store');
Route::get('/gift-cards', [MarketingController::class, 'giftCards'])->name('gift-cards');

Route::get('/sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [FeedController::class, 'robots']);
Route::get('/feed/google.xml', [FeedController::class, 'google'])->name('feed.google');
Route::get('/feed/meta.xml', [FeedController::class, 'meta'])->name('feed.meta');
