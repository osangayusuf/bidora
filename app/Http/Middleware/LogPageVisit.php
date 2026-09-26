<?php

namespace App\Http\Middleware;

use App\Enums\ActivityType;
use App\Services\ActivityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs a "page visited" activity row for every visitor — guest or logged
 * in — so both the admin Activity Log and the Daily Reports page can count
 * real traffic. Guests are logged with a null user_id (shown as "Non users"
 * in the Activity Log); logged-in visits carry the real user, same as any
 * other account activity.
 *
 * Deliberately opt-in per route (attach it to the specific routes you want
 * tracked) rather than global on the `web` group, so this can't quietly
 * start logging every route — including ones added later — without a
 * conscious decision.
 *
 * To track a new page: add a route-name => label entry to PAGE_MAP below,
 * a matching ActivityType case, and attach this middleware to that route.
 */
class LogPageVisit
{
    /** Route name => [ActivityType, human-readable page label]. */
    private const PAGE_MAP = [
        'home' => [ActivityType::HOME_VIEWED, 'Home'],
        'winners' => [ActivityType::WINNERS_VIEWED, 'Winners'],
        'open-bids' => [ActivityType::OPEN_BIDS_VIEWED, 'Open Bids'],
        'auctions.show' => [ActivityType::AUCTION_VIEWED, 'Auction Details'],
    ];

    /** Minutes to suppress repeat log rows for the same visitor + page. */
    private const DEDUPE_MINUTES = 15;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $routeName = $request->route()?->getName();

        if (! $routeName || ! isset(self::PAGE_MAP[$routeName])) {
            return $response;
        }

        [$type, $label] = self::PAGE_MAP[$routeName];

        $user = Auth::user();

        // One row per visitor per page per window, so refreshing/browsing
        // back and forth doesn't flood the log with duplicate rows. Keyed by
        // user id when logged in (survives session changes across devices
        // less relevantly, but stays stable per account) or session id for
        // guests.
        $visitorKey = $user?->getKey() ?? $request->session()->getId();
        $dedupeKey = 'page_visit:'.$visitorKey.':'.$type->value;

        if (Cache::has($dedupeKey)) {
            return $response;
        }

        Cache::put($dedupeKey, true, now()->addMinutes(self::DEDUPE_MINUTES));

        $metadata = ['page' => $label, 'path' => $request->path()];

        // For auction detail pages, capture which auction was viewed so the
        // Details/Context column shows something more useful than the type.
        $auction = $request->route('auction');
        if ($auction) {
            $metadata['auction'] = is_object($auction)
                ? ($auction->name ?? $auction->getKey())
                : $auction;
        }

        app(ActivityService::class)->log(type: $type, user: $user, metadata: $metadata);

        return $response;
    }
}
