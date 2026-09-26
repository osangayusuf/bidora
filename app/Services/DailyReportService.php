<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\SubscriptionStatus;
use App\Models\PointSubscription;
use App\Models\User;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DailyReportService
{
    /**
     * Data is stored in UTC; "today"/"this week"/"this month" boundaries for
     * reporting must be computed in the business's real timezone and then
     * converted to UTC, or the last hour of the WAT day gets bucketed into
     * the wrong day. Matches Admin\MetricsController.
     */
    private const BUSINESS_TIMEZONE = 'Africa/Lagos';

    /** Page-visit types — what counts as a "visit" for the Daily Visits figure. */
    private const VISIT_TYPES = [
        ActivityType::HOME_VIEWED,
        ActivityType::WINNERS_VIEWED,
        ActivityType::OPEN_BIDS_VIEWED,
        ActivityType::AUCTION_VIEWED,
    ];

    /**
     * Engagement types, per stakeholder sign-off: bids placed, page views,
     * logins, and rewards claimed.
     */
    private const ENGAGEMENT_TYPES = [
        ActivityType::BID_PLACED,
        ActivityType::HOME_VIEWED,
        ActivityType::WINNERS_VIEWED,
        ActivityType::OPEN_BIDS_VIEWED,
        ActivityType::AUCTION_VIEWED,
        ActivityType::LOGIN_SUCCESS,
        ActivityType::REWARDS_CLAIMED,
    ];

    /** How many rows to include in the PDF's user tables before truncating. */
    public const PDF_LIST_LIMIT = 200;

    /**
     * Build every figure for the report. Visits/Engagement are scoped to the
     * given period; Active/Non-Active Subscribers are NOT — subscription
     * status is a current snapshot (Paystack doesn't give us a history of
     * what a subscription's status was on any given past day), so that
     * figure is always "as of right now" regardless of which period is
     * selected.
     *
     * @return array{
     *     period: string,
     *     range: array{start: string, end: string},
     *     visits: int,
     *     engagement: int,
     *     active_count: int,
     *     non_active_count: int,
     * }
     */
    public function summary(string $period, ?string $date = null): array
    {
        [$start, $end] = $this->resolveRange($period, $date);
        $activeIds = $this->activeSubscriberIds();
        $allIds = $this->allSubscriberIds();

        return [
            'period' => $period,
            'range' => [
                'start' => $start->copy()->setTimezone(self::BUSINESS_TIMEZONE)->toDateString(),
                'end' => $end->copy()->setTimezone(self::BUSINESS_TIMEZONE)->subSecond()->toDateString(),
            ],
            'visits' => $this->typedActivityCount(self::VISIT_TYPES, $start, $end),
            'engagement' => $this->typedActivityCount(self::ENGAGEMENT_TYPES, $start, $end),
            'active_count' => $activeIds->count(),
            'non_active_count' => $allIds->diff($activeIds)->count(),
        ];
    }

    /** Paginated list of users with a currently-active subscription. */
    public function activeUsers(int $page, int $perPage = 20): LengthAwarePaginator
    {
        return User::whereIn('id', $this->activeSubscriberIds())
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'email'], 'active_page', $page);
    }

    /** Paginated list of subscribers (they have a subscription record) with no currently-active one. */
    public function nonActiveUsers(int $page, int $perPage = 20): LengthAwarePaginator
    {
        $nonActiveIds = $this->allSubscriberIds()->diff($this->activeSubscriberIds());

        return User::whereIn('id', $nonActiveIds)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'email'], 'inactive_page', $page);
    }

    /**
     * Everything needed to render the PDF: the summary figures plus capped
     * (not paginated — PDF rendering doesn't page) subscriber lists.
     */
    public function forExport(string $period, ?string $date = null): array
    {
        $activeIds = $this->activeSubscriberIds();
        $nonActiveIds = $this->allSubscriberIds()->diff($activeIds);

        $activeQuery = User::whereIn('id', $activeIds)->select('id', 'name', 'email')->orderBy('name');
        $nonActiveQuery = User::whereIn('id', $nonActiveIds)->select('id', 'name', 'email')->orderBy('name');

        return array_merge($this->summary($period, $date), [
            'active_users' => $activeQuery->limit(self::PDF_LIST_LIMIT)->get(),
            'active_users_truncated' => $activeIds->count() > self::PDF_LIST_LIMIT,
            'non_active_users' => $nonActiveQuery->limit(self::PDF_LIST_LIMIT)->get(),
            'non_active_users_truncated' => $nonActiveIds->count() > self::PDF_LIST_LIMIT,
        ]);
    }

    /**
     * @param  ActivityType[]  $types
     */
    private function typedActivityCount(array $types, Carbon $start, Carbon $end): int
    {
        return UserActivity::whereIn('type', array_map(fn (ActivityType $t) => $t->value, $types))
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /** Distinct user IDs with a subscription currently in ACTIVE status. */
    private function activeSubscriberIds(): Collection
    {
        return PointSubscription::where('status', SubscriptionStatus::ACTIVE->value)
            ->distinct()
            ->pluck('user_id');
    }

    /** Distinct user IDs with any subscription record at all, active or not — i.e. everyone who is or was a subscriber. */
    private function allSubscriberIds(): Collection
    {
        return PointSubscription::distinct()->pluck('user_id');
    }

    /**
     * @return array{0: Carbon, 1: Carbon} UTC [start, end) bounds.
     */
    private function resolveRange(string $period, ?string $date): array
    {
        $anchor = $date
            ? Carbon::parse($date, self::BUSINESS_TIMEZONE)
            : Carbon::now(self::BUSINESS_TIMEZONE);

        return match ($period) {
            'weekly' => [
                $anchor->copy()->startOfWeek()->utc(),
                $anchor->copy()->startOfWeek()->addWeek()->utc(),
            ],
            'monthly' => [
                $anchor->copy()->startOfMonth()->utc(),
                $anchor->copy()->startOfMonth()->addMonthNoOverflow()->utc(),
            ],
            default => [
                $anchor->copy()->startOfDay()->utc(),
                $anchor->copy()->startOfDay()->addDay()->utc(),
            ],
        };
    }
}
