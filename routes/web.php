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
Route::post('/orders/{order}/pay', [CheckoutController::class, 'retry'])->middleware('throttle:10,10')->name('order.pay');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Mobile number verification (6-digit code by WhatsApp) and "Continue with Google".
Route::post('/verify-phone/send', [\App\Http\Controllers\PhoneVerificationController::class, 'send'])->middleware('throttle:6,10')->name('phone.send');
Route::post('/verify-phone/check', [\App\Http\Controllers\PhoneVerificationController::class, 'check'])->middleware('throttle:20,10')->name('phone.check');
Route::get('/account/verify-phone', [\App\Http\Controllers\PhoneVerificationController::class, 'show'])->middleware('auth')->name('phone.verify');
Route::get('/auth/google', [\App\Http\Controllers\Auth\GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [\App\Http\Controllers\Auth\GoogleController::class, 'callback'])->name('google.callback');

Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::post('/account/addresses', [AccountController::class, 'storeAddress'])->name('account.addresses.store');
    Route::delete('/account/addresses/{address}', [AccountController::class, 'deleteAddress'])->name('account.addresses.delete');
    Route::delete('/account/id', [AccountController::class, 'forgetId'])->name('account.id.forget');
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

// One-click "artisan migrate" for the admin banner shown when an update added new migrations.
Route::post('/admin/update-database', \App\Http\Controllers\Admin\DatabaseUpdateController::class)
    ->middleware(['auth', \App\Http\Middleware\AdminSecurity::class])->name('admin.update-database');

// Live video call with staff (per product, switchable in the admin).
Route::post('/product/{product}/video-call', [\App\Http\Controllers\VideoCallController::class, 'store'])->middleware('throttle:5,10')->name('video-call.store');
Route::get('/video-call/{call}', [\App\Http\Controllers\VideoCallController::class, 'show'])->name('video-call.show');
Route::get('/video-call/{call}/status', [\App\Http\Controllers\VideoCallController::class, 'status'])->name('video-call.status');
Route::get('/admin/video-calls/{id}/join', \App\Http\Controllers\Admin\VideoCallJoinController::class)
    ->middleware(['auth', \App\Http\Middleware\AdminSecurity::class])->whereNumber('id')->name('admin.video-call.join');
Route::get('/admin/id-documents/{owner}/{id}/{side}', \App\Http\Controllers\Admin\IdDocumentController::class)
    ->middleware(['auth', \App\Http\Middleware\AdminSecurity::class])->whereIn('owner', ['order', 'user'])->whereNumber('id')->whereIn('side', ['front', 'back'])->name('admin.id-document');

// Printable copy of a list from the admin "Print / Export" button.
Route::get('/admin/print/{token}', \App\Http\Controllers\Admin\PrintController::class)
    ->middleware(['auth', \App\Http\Middleware\AdminSecurity::class])->where('token', '[A-Za-z0-9]{40}')->name('admin.print');

// Admin two-step sign-in (Settings → Security). Outside /admin so the panel's own check doesn't loop.
Route::middleware('auth')->prefix('admin-verify')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\TwoFactorController::class, 'show'])->name('admin.two-factor');
    Route::post('/', [\App\Http\Controllers\Admin\TwoFactorController::class, 'verify'])->middleware('throttle:10,10')->name('admin.two-factor.verify');
    Route::post('/resend', [\App\Http\Controllers\Admin\TwoFactorController::class, 'resend'])->middleware('throttle:3,10')->name('admin.two-factor.resend');
});

// Terms, no refund / no exchange, privacy and delivery policies (Settings → Policies to edit).
Route::get('/{slug}', [\App\Http\Controllers\PolicyController::class, 'show'])
    ->whereIn('slug', array_column(\App\Support\Policies::PAGES, 'slug'))->name('policy.show');

// Affiliates: white page (/w/CODE), dashboard for the affiliate.
Route::get('/w/{code}', [\App\Http\Controllers\AffiliateController::class, 'page'])->where('code', '[A-Za-z0-9]{2,32}')->name('affiliate.page');
Route::get('/l/{token}', [\App\Http\Controllers\AffiliateController::class, 'openLock'])->where('token', '[A-Za-z0-9]{32}')->name('affiliate.lock');
Route::post('/visit', [\App\Http\Controllers\AffiliateController::class, 'visit'])->middleware('throttle:5,10')->name('store-visit.store');
Route::get('/affiliate/join', [\App\Http\Controllers\AffiliateController::class, 'applyForm'])->name('affiliate.apply');
Route::post('/affiliate/join', [\App\Http\Controllers\AffiliateController::class, 'apply'])->middleware('throttle:3,10')->name('affiliate.apply.store');
Route::middleware('auth')->group(function () {
    Route::get('/affiliate', [\App\Http\Controllers\AffiliateController::class, 'dashboard'])->name('affiliate.dashboard');
    Route::post('/affiliate/page', [\App\Http\Controllers\AffiliateController::class, 'update'])->middleware('throttle:10,1')->name('affiliate.update');
    Route::post('/affiliate/lock', [\App\Http\Controllers\AffiliateController::class, 'lock'])->middleware('throttle:20,60')->name('affiliate.lock.create');
});
