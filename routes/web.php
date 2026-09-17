<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\AuctionController as AdminAuctionController;
use App\Http\Controllers\Admin\MetricsController;
use App\Http\Controllers\Admin\PaystackTransactionController;
use App\Http\Controllers\Admin\PointSubscriptionController;
use App\Http\Controllers\Admin\PointTransactionController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RewardsConfigController;
use App\Http\Controllers\Admin\SubscriptionPackageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WinnerController as AdminWinnerController;
use App\Http\Controllers\AuctionController;
use App\Http\Controllers\AuctionReviewController;
use App\Http\Controllers\AuctionTimelineController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\EventItemsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HowToPlayController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OpenBidsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecommendedController;
use App\Http\Controllers\RewardClaimController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\SpinController;
use App\Http\Controllers\TaskCenterController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\TrendingController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletPackageSubscriptionController;
use App\Http\Controllers\WalletSubscriptionController;
use App\Http\Controllers\WinnersController;
use App\Http\Middleware\EnsureTermsAccepted;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('open-bids', [OpenBidsController::class, 'index'])->name('open-bids');
Route::get('how-to-play', [HowToPlayController::class, 'index'])->name('how-to-play');
Route::get('about', [AboutController::class, 'index'])->name('about');
Route::get('terms', [TermsController::class, 'index'])->name('terms.index');
Route::get('terms/{slug}', [TermsController::class, 'show'])->name('terms.show');
Route::get('auctions/{auction}', [AuctionController::class, 'show'])->name('auctions.show');
Route::get('auctions/{auction}/timeline', [AuctionTimelineController::class, 'index'])->name('auctions.timeline');
Route::get('search', [SearchController::class, 'index'])->name('search');
Route::get('trending', [TrendingController::class, 'index'])->name('trending');

Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->name('auth.social.redirect')
    ->whereIn('provider', ['google', 'facebook']);

Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->name('auth.social.callback')
    ->whereIn('provider', ['google', 'facebook']);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('terms/accept', [TermsController::class, 'accept'])->name('terms.accept');

    Route::get('winners', [WinnersController::class, 'index'])->name('winners');
    Route::get('leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard');
    Route::get('recommended', [RecommendedController::class, 'index'])->name('recommended');
    Route::get('event-items', [EventItemsController::class, 'index'])->name('event-items');

    Route::get('onboarding', [OnboardingController::class, 'index'])->name('onboarding');
    Route::post('onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');

    Route::get('wallet', [WalletController::class, 'index'])->name('wallet');
    Route::get('wallet/payment/callback', [WalletController::class, 'paymentCallback'])->name('wallet.payment.callback');
    Route::post('wallet/deposit', [WalletController::class, 'deposit'])->name('wallet.deposit');
    Route::post('wallet/subscriptions', [WalletSubscriptionController::class, 'store'])->name('wallet.subscriptions.store');
    Route::delete('wallet/subscriptions/{subscription}', [WalletSubscriptionController::class, 'destroy'])->name('wallet.subscriptions.destroy');
    Route::get('wallet/subscriptions/{subscription}/manage-card', [WalletSubscriptionController::class, 'manageCard'])->name('wallet.subscriptions.manage-card');
    Route::post('wallet/packages/subscribe', [WalletPackageSubscriptionController::class, 'store'])->name('wallet.packages.subscribe');
    Route::get('tasks', [TaskCenterController::class, 'index'])->name('tasks');
    Route::post('checkin', [CheckinController::class, 'store'])->name('checkin');
    Route::get('profile', [ProfileController::class, 'index'])->name('profile');
    Route::get('profile/leaderboard', [ProfileController::class, 'leaderboard'])->name('profile.leaderboard');
    Route::get('security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::post('auctions/{auction}/reviews', [AuctionReviewController::class, 'store'])->name('auctions.reviews.store');

    Route::post('spin', SpinController::class)->name('spin');

    Route::middleware([EnsureTermsAccepted::class])->group(function () {
        Route::post('wallet/claim-bonus', [WalletController::class, 'claimBonus'])->name('wallet.claim-bonus');
        Route::post('rewards/claim', RewardClaimController::class)->name('rewards.claim');
    });
});

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::resource('auctions', AdminAuctionController::class)
            ->except(['show', 'create', 'edit']);
        Route::post('auctions/{auction}/publish', [AdminAuctionController::class, 'publish'])
            ->name('auctions.publish');
        Route::post('auctions/{auction}/close', [AdminAuctionController::class, 'close'])
            ->name('auctions.close');
        Route::patch('auctions/{auction}/toggle-enabled', [AdminAuctionController::class, 'toggleEnabled'])
            ->name('auctions.toggleEnabled');
        Route::patch('auctions/{auction}/toggle-event', [AdminAuctionController::class, 'toggleEvent'])
            ->name('auctions.toggleEvent');

        // Winners Ledger
        Route::get('winners', [AdminWinnerController::class, 'index'])->name('winners.index');

        // Users Management
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::post('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');

        // Agent Management (Approvals)
        Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
        Route::post('agents/{user}/approve', [AgentController::class, 'approve'])->name('agents.approve');
        Route::post('agents/{user}/reject', [AgentController::class, 'reject'])->name('agents.reject');

        // Bids Audit
        Route::get('bids', [App\Http\Controllers\Admin\BidController::class, 'index'])->name('bids.index');

        // Transactions Management (Point vs Paystack)
        Route::get('point-transactions', [PointTransactionController::class, 'index'])->name('point-transactions.index');
        Route::get('paystack-transactions', [PaystackTransactionController::class, 'index'])->name('paystack-transactions.index');
        Route::post('paystack-transactions/{transaction}/requery', [PaystackTransactionController::class, 'requery'])->name('paystack-transactions.requery');

        // Recurring Point Subscriptions
        Route::get('point-subscriptions', [PointSubscriptionController::class, 'index'])->name('point-subscriptions.index');
        Route::post('point-subscriptions/{subscription}/cancel', [PointSubscriptionController::class, 'cancel'])->name('point-subscriptions.cancel');
        Route::post('point-subscriptions/{subscription}/resync', [PointSubscriptionController::class, 'resync'])->name('point-subscriptions.resync');

        // Subscription Packages (fixed-tier catalog)
        Route::get('subscription-packages', [SubscriptionPackageController::class, 'index'])->name('subscription-packages.index');
        Route::post('subscription-packages', [SubscriptionPackageController::class, 'store'])->name('subscription-packages.store');
        Route::patch('subscription-packages/{package}', [SubscriptionPackageController::class, 'update'])->name('subscription-packages.update');
        Route::post('subscription-packages/{package}/toggle-active', [SubscriptionPackageController::class, 'toggleActive'])->name('subscription-packages.toggle-active');
        Route::delete('subscription-packages/{package}', [SubscriptionPackageController::class, 'destroy'])->name('subscription-packages.destroy');

        // Reviews Moderation
        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/{review}/toggle-visibility', [ReviewController::class, 'toggleVisibility'])->name('reviews.toggle-visibility');
        Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

        // Rewards Config Management
        Route::get('rewards-config', [RewardsConfigController::class, 'index'])->name('rewards-config.index');
        Route::post('rewards-config', [RewardsConfigController::class, 'update'])->name('rewards-config.update');

        // Metrics & Analytics
        Route::get('metrics', [MetricsController::class, 'index'])->name('metrics.index');

        // Activity Log & Spam Protection
        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
    });

Route::redirect('admin', '/admin/auctions', 301)->name('admin.dashboard');

Route::middleware(['auth', 'verified', EnsureTermsAccepted::class, 'throttle:bid'])->group(function () {
    Route::post('auctions/{auction}/bids', [BidController::class, 'store'])
        ->name('auctions.bids.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/support.php';
