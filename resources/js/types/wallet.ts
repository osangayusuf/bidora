import type { LengthAwarePaginator } from '@/types/auction';

export type WalletBalances = {
    points_balance: number;
    bonus_points: number;
};

export type WalletConfig = {
    points_per_naira: number;
    bonus_conversion_rate: number;
    min_deposit_naira: number;
    max_deposit_naira: number;
    deposit_presets: number[];
    paystack_public_key: string;
    top_ups_enabled: boolean;
    recurring_enabled: boolean;
    packages_enabled: boolean;
};

export type SubscriptionFrequency = 'weekly' | 'monthly';

export type PaystackPlanListing = {
    id: number;
    amount_naira: number;
    frequency: SubscriptionFrequency;
    frequency_label: string;
};

export type SubscriptionStatus =
    | 'pending'
    | 'active'
    | 'attention'
    | 'non_renewing'
    | 'completed'
    | 'cancelled';

export type PackageRenewalCycle =
    | 'one_off'
    | 'daily'
    | 'weekly'
    | 'bi_weekly'
    | 'monthly';

export type SubscriptionPackageListing = {
    id: number;
    name: string;
    slug: string;
    renewal_cycle: PackageRenewalCycle;
    renewal_cycle_label: string;
    points_allocated: number;
    price_naira: number;
    is_active: boolean;
    sort_order: number;
};

export type PointSubscriptionListing = {
    id: number;
    kind: 'plan' | 'package';
    amount_naira: number;
    frequency: SubscriptionFrequency | null;
    frequency_label: string;
    package_name: string | null;
    status: SubscriptionStatus;
    status_label: string;
    is_cancellable: boolean;
    authorization_last4: string | null;
    authorization_brand: string | null;
    next_payment_date: string | null;
    next_charge_at: string | null;
    last_charged_at: string | null;
    failure_count: number;
    created_at: string;
};

export type PointTransactionListing = {
    id: number;
    type: string;
    type_label: string;
    direction: 'credit' | 'debit';
    amount: number;
    naira_amount: number | null;
    exchange_rate: number;
    status: string;
    provider_reference: string | null;
    auction_id: number | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
};

export type PaystackInit = {
    reference: string;
    access_code: string | null;
    amount_kobo: number;
    email: string;
    public_key: string;
    plan_code?: string | null;
    channels?: string[] | null;
    subscription_id?: number | null;
};

export type WalletTransactionsPaginator =
    LengthAwarePaginator<PointTransactionListing>;
