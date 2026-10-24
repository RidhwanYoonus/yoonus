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
    Route::post('/checkout/map-link', [CheckoutController::class, 'mapLink'])->middleware('throttle:20,1')->name('checkout.map-link');
    Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');
});
Route::get('/checkout/complete/{order}', [CheckoutController::class, 'complete'])->name('checkout.complete');

Route::get('/payments/mock/{order}', [PaymentController::class, 'mock'])->middleware('signed')->name('payments.mock');
Route::post('/payments/mock/{order}', [PaymentController::class, 'mockApprove'])->middleware('signed')->name('payments.mock.approve');
Route::get('/pay/{order}/card', [\App\Http\Controllers\StripeCardController::class, 'show'])->name('payments.card');
Route::post('/pay/{order}/card/prepare', [\App\Http\Controllers\StripeCardController::class, 'prepare'])->middleware('throttle:20,1')->name('payments.card.prepare');
Route::post('/pay/{order}/card/saved', [\App\Http\Controllers\StripeCardController::class, 'saved'])->middleware('throttle:10,1')->name('payments.card.saved');
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
    Route::get('/login/code', [\App\Http\Controllers\Auth\LoginCodeController::class, 'show'])->name('login.code');
    Route::post('/login/code', [\App\Http\Controllers\Auth\LoginCodeController::class, 'send'])->middleware('throttle:6,10')->name('login.code.send');
    Route::post('/login/code/verify', [\App\Http\Controllers\Auth\LoginCodeController::class, 'verify'])->middleware('throttle:10,10')->name('login.code.verify');
    Route::post('/login/code/reset', fn (\Illuminate\Http\Request $r) => tap(redirect()->route('login.code'), fn () => $r->session()->forget(['login_code_target', 'login_code_test'])))->name('login.code.reset');
    Route::get('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'send'])->middleware('throttle:5,10')->name('password.email');
    Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\PasswordResetController::class, 'form'])->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'reset'])->middleware('throttle:10,10')->name('password.update');
    Route::post('/register/check', [AuthController::class, 'checkRegister'])->middleware('throttle:30,1')->name('register.check');
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
    Route::delete('/account/cards/{card}', [AccountController::class, 'removeCard'])->name('account.cards.remove');
    Route::post('/account/id', [AccountController::class, 'storeId'])->middleware('throttle:10,10')->name('account.id.store');
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
Route::post('/id-upload', \App\Http\Controllers\IdUploadController::class)->middleware('throttle:20,10')->name('id.upload');
Route::get('/video-call/{call}', [\App\Http\Controllers\VideoCallController::class, 'show'])->name('video-call.show');
Route::get('/video-call/{call}/status', [\App\Http\Controllers\VideoCallController::class, 'status'])->name('video-call.status');
Route::middleware('auth')->group(function () {
    Route::get('/studio/products/{product}', [\App\Http\Controllers\Admin\PhotoStudioController::class, 'show'])->name('admin.photo-studio');
    Route::post('/studio/products/{product}/save', [\App\Http\Controllers\Admin\PhotoStudioController::class, 'save'])->name('admin.photo-studio.save');
    Route::post('/studio/backgrounds', [\App\Http\Controllers\Admin\PhotoStudioController::class, 'addBackground'])->name('admin.photo-studio.bg.add');
    Route::post('/studio/backgrounds/remove', [\App\Http\Controllers\Admin\PhotoStudioController::class, 'removeBackground'])->name('admin.photo-studio.bg.remove');
    Route::post('/studio/models', [\App\Http\Controllers\Admin\PhotoStudioController::class, 'addModel'])->name('admin.photo-studio.model.add');
    Route::post('/studio/models/remove', [\App\Http\Controllers\Admin\PhotoStudioController::class, 'removeModel'])->name('admin.photo-studio.model.remove');
    Route::post('/studio/products/{product}/add', [\App\Http\Controllers\Admin\PhotoStudioController::class, 'addPhoto'])->name('admin.photo-studio.add');
    Route::post('/studio/products/{product}/restore', [\App\Http\Controllers\Admin\PhotoStudioController::class, 'restore'])->name('admin.photo-studio.restore');
});
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
    Route::get('/affiliate/report', [\App\Http\Controllers\AffiliateController::class, 'report'])->name('affiliate.report');
    Route::get('/affiliate/share', [\App\Http\Controllers\AffiliateController::class, 'share'])->name('affiliate.share');
    Route::post('/affiliate/page', [\App\Http\Controllers\AffiliateController::class, 'update'])->middleware('throttle:10,1')->name('affiliate.update');
    Route::post('/affiliate/lock', [\App\Http\Controllers\AffiliateController::class, 'lock'])->middleware('throttle:20,60')->name('affiliate.lock.create');
});

// Admin "log in as": back to the admin's own account.
Route::post('/login-as/leave', function () {
    \App\Support\Impersonation::stop();

    return redirect()->to(url('/admin/affiliates'));
})->middleware('auth')->name('impersonate.leave');
