<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Points per Naira
    |--------------------------------------------------------------------------
    | The number of spendable points awarded for every ₦1 deposited via Paystack.
    | Example: 100 means ₦1 → 100 points.
    */
    'points_per_naira' => env('POINTS_PER_NAIRA', 10),

    /*
    |--------------------------------------------------------------------------
    | Bonus Conversion Rate
    |--------------------------------------------------------------------------
    | The multiplier applied when a user claims their bonus_points balance.
    | 1.0 means 1 bonus point → 1 spendable point (1-to-1 conversion).
    */
    'bonus_conversion_rate' => env('BONUS_CONVERSION_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Minimum Bid Increment
    |--------------------------------------------------------------------------
    | The minimum number of points required for a single bid placement.
    */
    'min_bid_increment' => env('MIN_BID_INCREMENT', 10),

    /*
    |--------------------------------------------------------------------------
    | Deposit Limits (Naira)
    |--------------------------------------------------------------------------
    */
    'min_deposit_naira' => env('MIN_DEPOSIT_NAIRA', 100),

    'max_deposit_naira' => env('MAX_DEPOSIT_NAIRA', 500_000),

    /*
    |--------------------------------------------------------------------------
    | Deposit Presets (Naira)
    |--------------------------------------------------------------------------
    */
    'deposit_presets' => [100, 200, 500, 1000, 5_000, 10_000],

    /*
    |--------------------------------------------------------------------------
    | Referral Program
    |--------------------------------------------------------------------------
    */
    'referral' => [
        'enabled' => env('REFERRAL_ENABLED', true),
        'referrer_signup_points' => env('REFERRAL_REFERRER_SIGNUP_POINTS', 10),
        'referrer_first_deposit_points' => env('REFERRAL_REFERRER_FIRST_DEPOSIT_POINTS', 20),
        'referee_signup_points' => env('REFERRAL_REFEREE_SIGNUP_POINTS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration Points
    |--------------------------------------------------------------------------
    | The number of points allocated to a user upon email verification.
    |
    */
    'registration_points' => env('REGISTRATION_POINTS', 50),

    /*
    |--------------------------------------------------------------------------
    | One-off Deposits
    |--------------------------------------------------------------------------
    | The original single-charge "buy points" flow. Kept fully functional
    | (routes, controller, services) so it can be re-enabled without a code
    | change, but hidden from the Wallet page for regular users while the
    | platform is subscription-only. Does not affect admin screens.
    */
    'one_off_deposits_enabled' => env('ONE_OFF_DEPOSITS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Recurring Point Subscriptions (legacy)
    |--------------------------------------------------------------------------
    | Paystack Plans/Subscriptions-backed recurring top-ups. Superseded by the
    | fixed-tier Subscription Packages below — kept fully functional (routes,
    | controller, services, `php artisan paystack:sync-plans`) so it can be
    | re-enabled without a code change, but hidden from the Wallet page while
    | the platform is packages-only. Does not affect admin screens.
    */
    'recurring' => [
        'enabled' => env('RECURRING_DEPOSITS_ENABLED', false),
        'frequencies' => ['weekly', 'monthly'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription Packages
    |--------------------------------------------------------------------------
    | Fixed-tier packages (e.g. Solo, Jara, Awoof) with a set points bundle,
    | price, and renewal cycle. Managed via the admin Subscription Packages
    | screen. Charged locally against a saved card authorization rather than
    | a native Paystack Subscription (see PackageSubscriptionService). This is
    | the platform's primary points-purchase flow.
    */
    'packages' => [
        'enabled' => env('SUBSCRIPTION_PACKAGES_ENABLED', true),
        'max_charge_failures' => env('SUBSCRIPTION_PACKAGE_MAX_FAILURES', 3),
    ],

];
